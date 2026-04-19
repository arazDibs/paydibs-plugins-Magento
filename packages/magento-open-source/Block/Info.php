<?php
/**
 * Copyright 2026 Paydibs
 * SPDX-License-Identifier: Apache-2.0
 */
namespace Paydibs\PaymentGateway\Block;

use Magento\Framework\View\Element\Template\Context;

class Info extends \Magento\Payment\Block\Info
{
    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Prepare payment information
     *
     * @param \Magento\Framework\DataObject|array|null $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $payment = $this->getInfo();
        
        $order = $payment->getOrder();
        if (!$order) {
            return $transport;
        }
        $methodTitle = $this->getMethod()->getConfigData('title', $order->getStoreId());
        if ($methodTitle) {
            $transport->addData([
                'Method Title' => $methodTitle
            ]);
        }
        
        $transactionId = $payment->getLastTransId();
        if ($transactionId) {
            $transport->addData([
                'Paydibs Transaction ID' => $transactionId
            ]);
        }
        
        $additionalInfo = $payment->getAdditionalInformation();
        
        if (isset($additionalInfo['MerchantTxnAmt'])) {
            $amount = str_replace('_', '.', $additionalInfo['MerchantTxnAmt']);
            $transport->addData([
                'Paydibs Amount' => $amount
            ]);
        }
        
        if (isset($additionalInfo['MerchantCurrCode'])) {
            $transport->addData([
                'Paydibs Currency Code' => $additionalInfo['MerchantCurrCode']
            ]);
        }
        
        return $transport;
    }
}
