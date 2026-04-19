<?php
/**
 * Copyright © Paydibs. All rights reserved.
 */
namespace Paydibs\PaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Paydibs\PaymentGateway\Cron\QueryPendingOrders;
use Paydibs\PaymentGateway\Model\PaymentMethod;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Manual trigger for pending-order query (protected by a configured secret).
 */
class Requery implements HttpGetActionInterface
{
    private const XML_PATH_MANUAL_REQUERY_SECRET = 'payment/paydibs_payment_gateway/manual_requery_secret';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var QueryPendingOrders
     */
    private $queryPendingOrders;

    /**
     * @var PaymentMethod
     */
    private $paymentMethod;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        JsonFactory $resultJsonFactory,
        QueryPendingOrders $queryPendingOrders,
        PaymentMethod $paymentMethod,
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->queryPendingOrders = $queryPendingOrders;
        $this->paymentMethod = $paymentMethod;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $this->assertAuthorized();
        } catch (LocalizedException $e) {
            return $resultJson->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        $response = ['success' => false, 'message' => ''];

        try {
            $this->paymentMethod->log('Requery: Starting manual query of pending orders');
            $this->queryPendingOrders->execute();
            $response = [
                'success' => true,
                'message' => 'Successfully queried pending Paydibs orders',
            ];
            $this->paymentMethod->log('Requery: Manual query completed successfully');
        } catch (\Exception $e) {
            $this->paymentMethod->log('Requery: Error: ' . $e->getMessage());
            $response = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }

        return $resultJson->setData($response);
    }

    /**
     * Require a non-empty configured secret and matching `key` query parameter.
     *
     * @throws LocalizedException
     */
    private function assertAuthorized(): void
    {
        $configured = (string) $this->scopeConfig->getValue(
            self::XML_PATH_MANUAL_REQUERY_SECRET,
            ScopeInterface::SCOPE_STORE
        );
        if ($configured === '') {
            throw new LocalizedException(__('Manual requery is disabled.'));
        }
        $provided = (string) $this->request->getParam('key', '');
        if ($provided === '' || !hash_equals($configured, $provided)) {
            throw new LocalizedException(__('Invalid or missing requery key.'));
        }
    }
}
