<?php
/**
 * Paydibs Payment Gateway
 *
 * Copyright 2026 Paydibs
 * SPDX-License-Identifier: Apache-2.0
 *
 * @category    Paydibs
 * @package     Paydibs_PaymentGateway
 */
namespace Paydibs\PaymentGateway\Cron;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Paydibs\PaymentGateway\Model\Service\PaymentQueryService;
use Paydibs\PaymentGateway\Model\PaymentMethod;

class QueryPendingOrders
{
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var PaymentQueryService
     */
    protected $paymentQueryService;

    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param DateTime $dateTime
     * @param PaymentQueryService $paymentQueryService
     * @param PaymentMethod $paymentMethod
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        DateTime $dateTime,
        PaymentQueryService $paymentQueryService,
        PaymentMethod $paymentMethod
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->dateTime = $dateTime;
        $this->paymentQueryService = $paymentQueryService;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Execute cron job to query pending Paydibs orders
     *
     * @return void
     */
    public function execute()
    {
        $this->paymentMethod->log('Cron: Starting to query pending orders');
        
        try {
            $fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));
            $currentTime = date('Y-m-d H:i:s');
            
            $this->paymentMethod->log('Cron: Searching for orders between ' . $fifteenMinutesAgo . ' and ' . $currentTime);
            
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('status', Order::STATE_PENDING_PAYMENT)
                ->addFilter('created_at', $fifteenMinutesAgo, 'gteq')
                ->addFilter('created_at', $currentTime, 'lteq')
                ->create();
            
            $orderList = $this->orderRepository->getList($searchCriteria);
            $orders = $orderList->getItems();
            
            $paydibsOrders = [];
            foreach ($orders as $order) {
                $payment = $order->getPayment();
                if ($payment && $payment->getMethod() === 'paydibs_payment_gateway') {
                    $paydibsOrders[] = $order;
                }
            }
            
            $this->paymentMethod->log('Cron: Found ' . count($paydibsOrders) . ' pending Paydibs orders to query');
            
            foreach ($paydibsOrders as $order) {
                $this->paymentMethod->log('Cron: Processing order #' . $order->getIncrementId());
                
                $response = $this->paymentQueryService->queryPaymentStatus($order);
                
                if (!empty($response)) {
                    $this->paymentQueryService->processQueryResponse($order, $response);
                }
            }
            
            $this->paymentMethod->log('Cron: Finished querying pending orders');
        } catch (\Exception $e) {
            $this->paymentMethod->log('Cron: Error querying pending orders: ' . $e->getMessage());
        }
    }
}
