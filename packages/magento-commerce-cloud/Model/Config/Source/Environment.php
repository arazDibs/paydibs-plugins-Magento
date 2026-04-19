<?php
/**
 * Copyright 2026 Paydibs
 * SPDX-License-Identifier: Apache-2.0
 */
namespace Paydibs\PaymentGateway\Model\Config\Source;

class Environment implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'test', 'label' => __('Testing')],
            ['value' => 'production', 'label' => __('Production')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'test' => __('Testing'),
            'production' => __('Production')
        ];
    }
}
