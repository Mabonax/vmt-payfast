<?php

namespace Vmt\PayFast\Services;

use Vmt\PayFast\Exceptions\PaymentException;

final class ItnParser
{
    /** Parse raw form bytes to avoid framework trimming, duplicate-key collapse and key rewriting. */
    public function parse(string $body): array
    {
        if ($body === '' || strlen($body) > 32768) {
            throw new PaymentException('malformed_notification');
        }
        $fields = [];
        foreach (explode('&', $body) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            if (! preg_match('/^[a-z][a-z0-9_]*$/D', $key) || array_key_exists($key, $fields) || isset($fields['signature'])) {
                throw new PaymentException('malformed_notification');
            }
            $fields[$key] = urldecode($value);
        }
        if (! isset($fields['signature']) || ! preg_match('/^[a-f0-9]{32}$/D', $fields['signature'])) {
            throw new PaymentException('invalid_signature');
        }

        return $fields;
    }
}
