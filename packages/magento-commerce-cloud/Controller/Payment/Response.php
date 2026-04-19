<?php
/**
 * Copyright © Paydibs. All rights reserved.
 */
namespace Paydibs\PaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Paydibs\PaymentGateway\Model\PaymentMethod;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory as DbTransactionFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Paydibs\PaymentGateway\Model\Service\QuoteManagement;
use Paydibs\PaymentGateway\Model\Log\GatewayParamsSanitizer;
use Psr\Log\LoggerInterface;

class Response extends Action implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
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
     * @var CustomerSession
     */
    protected $customerSession;

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
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param BuilderInterface $transactionBuilder
     * @param PaymentMethod $paymentMethod
     * @param LoggerInterface $logger
     * @param CustomerSession $customerSession
     * @param InvoiceService $invoiceService
     * @param DbTransactionFactory $dbTransactionFactory
     * @param InvoiceSender $invoiceSender
     * @param QuoteManagement $quoteManagement
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        BuilderInterface $transactionBuilder,
        PaymentMethod $paymentMethod,
        LoggerInterface $logger,
        CustomerSession $customerSession,
        InvoiceService $invoiceService,
        DbTransactionFactory $dbTransactionFactory,
        InvoiceSender $invoiceSender,
        QuoteManagement $quoteManagement,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->transactionBuilder = $transactionBuilder;
        $this->paymentMethod = $paymentMethod;
        $this->_logger = $logger;
        $this->customerSession = $customerSession;
        $this->invoiceService = $invoiceService;
        $this->dbTransactionFactory = $dbTransactionFactory;
        $this->invoiceSender = $invoiceSender;
        $this->quoteManagement = $quoteManagement;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null to allow CsrfAwareActionInterface to automatically validate CSRF token
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Disable CSRF validation for payment gateway callbacks
     *
     * @param RequestInterface $request
     * @return bool
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }
    
    public function execute()
    {
        $params = $this->getRequest()->getParams();

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPostValue();
        }

        $this->paymentMethod->log('Response: gateway callback', [
            'params' => GatewayParamsSanitizer::sanitizeGatewayParams(is_array($params) ? $params : []),
        ]);
        
        if (!isset($params['MerchantPymtID']) || !isset($params['PTxnStatus']) || !isset($params['PTxnID'])) {
            $this->messageManager->addErrorMessage(__('Invalid payment response received.'));
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        
        $order = $this->orderFactory->create()->loadByIncrementId($params['MerchantPymtID']);
        
        if (!$order->getId()) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        
        // Check if this transaction has already been processed
        $payment = $order->getPayment();
        $lastTransId = $payment->getLastTransId();
        if ($lastTransId && $lastTransId === $params['PTxnID']) {
            $this->paymentMethod->log('Response: Transaction ' . $params['PTxnID'] . ' for order ' . $params['MerchantPymtID'] . ' already processed');
            
            if ($order->getState() === Order::STATE_PROCESSING) {
                $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                $this->checkoutSession->setLastOrderStatus($order->getStatus());
                
                $this->messageManager->addSuccessMessage(__('Your payment was successful.'));
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            } else if ($order->getState() === Order::STATE_CANCELED) {
                $this->messageManager->addErrorMessage(__('Payment failed: %1', isset($params['PTxnMsg']) ? $params['PTxnMsg'] : 'Payment failed'));
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        }
        
        if (isset($params['Sign']) && !$this->verifySignature($params)) {
            $this->messageManager->addErrorMessage(__('Invalid payment signature.'));
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        
        $txnStatus = $params['PTxnStatus'];

        $this->authenticateCustomerSessionIfNeeded($order);

        // Status '0' means success
        if ($txnStatus === '0') {
            if ($order->getState() === Order::STATE_PROCESSING) {
                $this->paymentMethod->log('Response: Order ' . $params['MerchantPymtID'] . ' already in processing state.');
                $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                $this->checkoutSession->setLastOrderStatus($order->getStatus());
                
                $this->messageManager->addSuccessMessage(__('Your payment was successful.'));
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
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
            
            $payment->addTransactionCommentsToOrder($transaction, __('Payment successfully processed by Paydibs.'));
            $payment->setParentTransactionId(null);
            
            $order->setState(Order::STATE_PROCESSING)
                  ->setStatus(Order::STATE_PROCESSING)
                  ->addCommentToStatusHistory(
                      __('Payment successfully processed by Paydibs. Transaction ID: %1', $params['PTxnID']),
                      true
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
                } catch (\Exception $e) {
                    $this->paymentMethod->log('Response: Error creating invoice: ' . $e->getMessage());
                }
            }
            
            $this->orderRepository->save($order);
            $this->checkoutSession->setLastQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $this->checkoutSession->setLastOrderStatus($order->getStatus());

            $this->quoteManagement->deactivateQuote($order->getQuoteId());
            
            $this->messageManager->addSuccessMessage(__('Your payment was successful.'));
            return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
        } else {
            // Payment failed or other status
            $errorMessage = isset($params['PTxnMsg']) ? $params['PTxnMsg'] : 'Payment failed';
            $txnStatus = $params['PTxnStatus'];
            switch ($txnStatus) {
                case '0': // Payment successful - already handled above
                    break;
                    
                case '2': // Payment pending - do nothing, keep order as is
                    $order->addCommentToStatusHistory(
                        __('Payment pending at Paydibs. Transaction ID: %1', $params['PTxnID']),
                        Order::STATE_PENDING_PAYMENT
                    );
                    $this->orderRepository->save($order);
                    $this->messageManager->addNoticeMessage(__('Your payment is being processed. We will notify you when it completes.'));
                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
                    
                case '1': // Payment failed
                case '9': // Payment voided
                case '17': // Payment cancelled at payment page
                case '-1': // Transaction not found
                case '-2': // Internal system error
                default: // Any other status - cancel order
                    if ($order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED) {
                        $this->paymentMethod->log('Response: Order ' . $params['MerchantPymtID'] . ' already canceled, likely handled by Notify.php');
                        
                        if ($this->paymentMethod->isCartRestorationEnabled()) {
                            $this->quoteManagement->restoreQuoteForCheckout($order->getQuoteId());
                            $this->paymentMethod->log('Response: Cart restored for order ' . $params['MerchantPymtID']);
                        }
                        
                        $this->messageManager->addErrorMessage(__('Payment failed: %1', $errorMessage));
                        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                    }
                    
                    try {
                        foreach ($order->getAllItems() as $item) {
                            $item->cancel();
                        }
                        $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)
                            ->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED)
                            ->addCommentToStatusHistory(
                                __('Payment failed at Paydibs. Status: %1, Error: %2', $txnStatus, $errorMessage),
                                false
                            );
                        
                        $this->orderRepository->save($order);
                        $this->paymentMethod->log('Response: Payment failed for order ' . $params['MerchantPymtID'] . '. Status: ' . $txnStatus . ', Error: ' . $errorMessage);
                        
                        if ($this->paymentMethod->isCartRestorationEnabled()) {
                            $this->quoteManagement->restoreQuoteForCheckout($order->getQuoteId());
                            $this->paymentMethod->log('Response: Cart restored for order ' . $params['MerchantPymtID']);
                        } 
                    } catch (\Exception $e) {
                        $this->paymentMethod->log('Response: Error canceling order: ' . $e->getMessage());
                    }
                    
                    $this->messageManager->addErrorMessage(__('Payment failed: %1', $errorMessage));
                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        }
    }

    /**
     * Restore a logged-in storefront session when the customer completed checkout as guest in the
     * browser but the order belongs to a registered account.
     *
     * Uses only the order's existing customer id; the payment gateway does not pass credentials
     * in this redirect request (validation relies on Paydibs response signature checks above).
     *
     * @param Order $order
     * @return void
     */
    protected function authenticateCustomerSessionIfNeeded(Order $order)
    {
        if (!$order->getCustomerId() || $this->customerSession->isLoggedIn()) {
            return;
        }
        $this->customerSession->loginById((int) $order->getCustomerId());
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
        $merchantOrdID = isset($params['MerchantOrdID']) && !empty($params['MerchantOrdID']) 
            ? $params['MerchantOrdID'] 
            : $params['MerchantPymtID'];
            
        $authCode = isset($params['AuthCode']) ? $params['AuthCode'] : '';
        
        $signatureString = $merchantPassword . 
                          $params['MerchantID'] . 
                          $params['MerchantPymtID'] . 
                          $params['PTxnID'] . 
                          $merchantOrdID . 
                          $params['MerchantTxnAmt'] . 
                          $params['MerchantCurrCode'] . 
                          $params['PTxnStatus'] . 
                          $authCode;
        
        $calculatedSignature = hash('sha512', $signatureString);
        $result = hash_equals($calculatedSignature, $params['Sign']);

        $this->paymentMethod->log(
            $result ? 'Response: Signature verification succeeded' : 'Response: Signature verification failed',
            [
                'merchant_pymt_id' => $params['MerchantPymtID'] ?? '',
                'match' => $result,
            ]
        );

        return $result;
    }
}
