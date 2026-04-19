<?php
/**
 * Copyright © Paydibs. All rights reserved.
 */
namespace Paydibs\PaymentGateway\Model\Log;

/**
 * Reduces PII and secrets in diagnostic logs (gateway request/response arrays).
 */
class GatewayParamsSanitizer
{
    private const GATEWAY_KEYS = [
        'TxnType',
        'MerchantID',
        'MerchantPymtID',
        'MerchantOrdID',
        'PTxnID',
        'PTxnStatus',
        'PTxnMsg',
        'MerchantTxnAmt',
        'MerchantCurrCode',
        'MerchantRURL',
        'CustIP',
        'PageTimeout',
        'MerchantCallbackURL',
        'AuthCode',
    ];

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function sanitizeGatewayParams(array $params): array
    {
        $out = [];
        foreach (self::GATEWAY_KEYS as $key) {
            if (\array_key_exists($key, $params)) {
                $out[$key] = $params[$key];
            }
        }
        if (\array_key_exists('Sign', $params)) {
            $out['Sign'] = '[redacted]';
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function sanitizeQueryResponse(array $row): array
    {
        return self::sanitizeGatewayParams($row);
    }
}
