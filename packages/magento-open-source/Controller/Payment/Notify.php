<?php
/**
 * Copyright © Paydibs. All rights reserved.
 */
namespace Paydibs\PaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Paydibs\PaymentGateway\Model\PaymentMethod;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory as DbTransactionFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Api\OrderRepositoryInterface;
use Paydibs\PaymentGateway\Model\Service\QuoteManagement;
use Paydibs\PaymentGateway\Model\Log\GatewayParamsSanitizer;
use Psr\Log\LoggerInterface;
use Magento\Framework\Escaper;

class Notify extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var Session
     */
    protected $checkoutSession;
    
    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    
    /**
     * @var BuilderInterface
     */
    protected $transactionBuilder;
    
    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var DbTransactionFactory
     */
    private $dbTransactionFactory;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param BuilderInterface $transactionBuilder
     * @param PaymentMethod $paymentMethod
     * @param LoggerInterface $logger
     * @param InvoiceService $invoiceService
     * @param DbTransactionFactory $dbTransactionFactory
     * @param InvoiceSender $invoiceSender
     * @param QuoteManagement $quoteManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param Escaper $escaper
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        BuilderInterface $transactionBuilder,
        PaymentMethod $paymentMethod,
        LoggerInterface $logger,
        InvoiceService $invoiceService,
        DbTransactionFactory $dbTransactionFactory,
        InvoiceSender $invoiceSender,
        QuoteManagement $quoteManagement,
        OrderRepositoryInterface $orderRepository,
        Escaper $escaper
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->transactionBuilder = $transactionBuilder;
        $this->paymentMethod = $paymentMethod;
        $this->_logger = $logger;
        $this->invoiceService = $invoiceService;
        $this->dbTransactionFactory = $dbTransactionFactory;
        $this->invoiceSender = $invoiceSender;
        $this->quoteManagement = $quoteManagement;
        $this->orderRepository = $orderRepository;
        $this->escaper = $escaper;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }

    /**
     * Process server-to-server notification
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/plain');
        
        $this->paymentMethod->log('Notify: IPN received', [
            'params' => GatewayParamsSanitizer::sanitizeGatewayParams(
                array_merge($this->getRequest()->getParams() ?: [], [])
            ),
        ]);

        try {
            $params = $this->getRequest()->getPostValue();
            if (!is_array($params)) {
                $params = [];
            }
            $this->paymentMethod->log('Notify: POST body (sanitized)', [
                'params' => GatewayParamsSanitizer::sanitizeGatewayParams($params),
            ]);

            if (empty($params['MerchantPymtID']) && empty($params['PTxnStatus'])) {
                foreach ($params as $key => $value) {
                    if (strpos($key, '{') === 0) {
                        try {
                            $decodedParams = json_decode($key, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedParams)) {
                                $this->paymentMethod->log('Notify: Successfully extracted JSON from key');
                                $params = $decodedParams;
                                break;
                            }
                        } catch (\Exception $e) {
                            $this->paymentMethod->log('Notify: Error decoding JSON key: ' . $e->getMessage());
                        }
                    }
                }
            }
            
            if (!isset($params['MerchantPymtID']) || !isset($params['PTxnStatus'])) {
                $this->paymentMethod->log('Notify: Notification missing required parameters after JSON parsing');
                $result->setContents('ERROR: Missing required parameters');
                return $result;
            }
            $order = $this->orderFactory->create()->loadByIncrementId($params['MerchantPymtID']);
            if (!$order->getId()) {
                $this->paymentMethod->log('Notify: Order not found: ' . $params['MerchantPymtID']);
                $result->setContents('ERROR: Order not found');
                return $result;
            }
            
            $payment = $order->getPayment();
            $processedTxnId = $payment->getAdditionalInformation('processed_ptxn_id');
            
            if ($processedTxnId === $params['PTxnID']) {
                $this->paymentMethod->log('Notify: Notification already processed for order ' . $params['MerchantPymtID'] . ' with transaction ID ' . $params['PTxnID']);
                $result->setContents('OK: Notification already processed');
                return $result;
            }
            if (isset($params['Sign']) && !$this->verifySignature($params)) {
                $this->paymentMethod->log('Notify: Invalid signature for order ' . $params['MerchantPymtID']);
                $result->setContents('ERROR: Invalid signature');
                return $result;
            }
            $txnStatus = $params['PTxnStatus'];
            if ($txnStatus === '0') {
                if ($order->getState() === Order::STATE_PROCESSING) {
                    $this->paymentMethod->log('Notify: Order already processed: ' . $params['MerchantPymtID']);
                    $result->setContents('OK: Order already processed');
                    return $result;
                }
                $payment = $order->getPayment();
                $payment->setTransactionId($params['PTxnID']);
                $payment->setLastTransId($params['PTxnID']);
                
                $paymentInfo = [
                    'PTxnID' => $params['PTxnID'],
                    'MerchantPymtID' => $params['MerchantPymtID'],
                    'MerchantTxnAmt' => $params['MerchantTxnAmt'],
                    'MerchantCurrCode' => $params['MerchantCurrCode']
                ];
                
                if (isset($params['AuthCode'])) {
                    $paymentInfo['AuthCode'] = $params['AuthCode'];
                }
                
                if (isset($params['PTxnMsg'])) {
                    $paymentInfo['PTxnMsg'] = $params['PTxnMsg'];
                }
                
                foreach ($paymentInfo as $key => $value) {
                    $payment->setAdditionalInformation($key, $value);
                }
                
                $transaction = $this->transactionBuilder
                    ->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($params['PTxnID'])
                    ->setAdditionalInformation(
                        [Transaction::RAW_DETAILS => $params]
                    )
                    ->setFailSafe(true)
                    ->build(Transaction::TYPE_CAPTURE);
                
                $payment->setParentTransactionId(null);
                $order->setState(Order::STATE_PROCESSING)
                      ->setStatus(Order::STATE_PROCESSING)
                      ->addCommentToStatusHistory(
                          __('Payment successfully processed by Paydibs. Transaction ID: %1', $params['PTxnID']),
                          false
                      );
                
                // Create invoice
                if ($order->canInvoice()) {
                    try {
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        if (!$invoice) {
                            throw new \Magento\Framework\Exception\LocalizedException(
                                __('We can\'t create an invoice right now.')
                            );
                        }
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                        $invoice->register();
                        $this->dbTransactionFactory->create()
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder())
                            ->save();
                        $order->addCommentToStatusHistory(
                            __('Invoice #%1 created.', $invoice->getIncrementId()),
                            false
                        );
                        $this->invoiceSender->send($invoice);
                        $this->paymentMethod->log('Notify: Invoice created for order ' . $params['MerchantPymtID']);
                    } catch (\Exception $e) {
                        $this->paymentMethod->log('Notify: Error creating invoice: ' . $e->getMessage());
                    }
                }

                $payment->setAdditionalInformation('processed_ptxn_id', $params['PTxnID']);
                $this->orderRepository->save($order);
                
                $this->paymentMethod->log('Notify: Payment successful for order ' . $params['MerchantPymtID']);
                $result->setContents('OK');
                
                $this->quoteManagement->deactivateQuote($order->getQuoteId());
                
                return $result;
            } else {
                // Payment failed or other status
                $rawPtMsg = isset($params['PTxnMsg']) ? (string) $params['PTxnMsg'] : '';
                $errorMessage = $rawPtMsg !== ''
                    ? $this->escaper->escapeHtml($rawPtMsg)
                    : (string) __('Payment failed');
                $txnStatus = $params['PTxnStatus'];
                switch ($txnStatus) {
                    case '0': // Payment successful - already handled above
                        break;
                        
                    case '2': // Payment pending - do nothing, keep order as is
                        $order->addCommentToStatusHistory(
                            __('Payment pending at Paydibs. Transaction ID: %1', $params['PTxnID']),
                            Order::STATE_PENDING_PAYMENT
                        );
                        
                        $payment = $order->getPayment();
                        $payment->setAdditionalInformation('processed_ptxn_id', $params['PTxnID']);
                        $this->orderRepository->save($order);
                        $this->paymentMethod->log('Notify: Payment pending for order ' . $params['MerchantPymtID']);
                        $result->setContents('OK');
                        return $result;
                        
                    case '1': // Payment failed
                    case '9': // Payment voided
                    case '17': // Payment cancelled at payment page
                    case '-1': // Transaction not found
                    case '-2': // Internal system error
                    default: // Any other status - cancel order
                        try {
                            if ($order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED) {
                                $this->paymentMethod->log('Notify: Order ' . $params['MerchantPymtID'] . ' already canceled, likely handled by Response.php');
                                if ($this->paymentMethod->isCartRestorationEnabled()) {
                                    $this->quoteManagement->restoreQuoteForCheckout($order->getQuoteId());
                                }
                            } else {
                                foreach ($order->getAllItems() as $item) {
                                    $item->cancel();
                                }
                                $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)
                                    ->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED)
                                    ->addCommentToStatusHistory(
                                        __('Payment failed at Paydibs. Status: %1, Error: %2', $txnStatus, $errorMessage),
                                        false
                                    );
                            }
                            
                            $payment = $order->getPayment();
                            $payment->setAdditionalInformation('processed_ptxn_id', $params['PTxnID']);
                            $this->orderRepository->save($order);
                            $this->paymentMethod->log('Notify: Payment failed for order ' . $params['MerchantPymtID'] . '. Status: ' . $txnStatus . ', Error: ' . $rawPtMsg);
                            
                            if ($this->paymentMethod->isCartRestorationEnabled()) {
                                $this->quoteManagement->restoreQuoteForCheckout($order->getQuoteId());
                                $this->paymentMethod->log('Notify: Cart restored for order ' . $params['MerchantPymtID']);
                            } else {
                                $this->paymentMethod->log('Notify: Cart restoration disabled for order ' . $params['MerchantPymtID']);
                            }
                        } catch (\Exception $e) {
                            $this->paymentMethod->log('Notify: Error canceling order: ' . $e->getMessage());
                        }
                        
                        $result->setContents('OK');
                        return $result;
                }
            }
        } catch (\Exception $e) {
            $this->paymentMethod->log('Notify: Notification error: ' . $e->getMessage());
            $result->setContents('ERROR');
            return $result;
        }
    }

    /**
     * Verify signature from Paydibs response
     *
     * @param array $params
     * @return bool
     */
    protected function verifySignature($params)
    {
        $merchantPassword = $this->paymentMethod->getMerchantPassword();
        
        $txnAmount = $params['MerchantTxnAmt'];
        if (strpos($txnAmount, '_') !== false) {
            $txnAmount = str_replace('_', '.', $txnAmount);
        }
        
        $merchantOrdID = isset($params['MerchantOrdID']) ? $params['MerchantOrdID'] : $params['MerchantPymtID'];
        $authCode = isset($params['AuthCode']) ? $params['AuthCode'] : '';
        
        $signatureString = $merchantPassword . 
                          $params['MerchantID'] . 
                          $params['MerchantPymtID'] . 
                          $params['PTxnID'] . 
                          $merchantOrdID . 
                          $txnAmount . 
                          $params['MerchantCurrCode'] . 
                          $params['PTxnStatus'] . 
                          $authCode;
        
        $calculatedSignature = hash('sha512', $signatureString);
        $result = hash_equals($calculatedSignature, $params['Sign']);

        $this->paymentMethod->log(
            $result ? 'Notify: Signature verification succeeded' : 'Notify: Signature verification failed',
            [
                'merchant_pymt_id' => $params['MerchantPymtID'] ?? '',
                'match' => $result,
            ]
        );

        return $result;
    }
}
