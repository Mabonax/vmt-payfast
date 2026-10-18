<?php

namespace Vmt\PayFast\Services;

final class SignatureService
{
    /** CheckoutService supplies only supported fields in checkout protocol order. */
    public function checkout(array $fields, #[\SensitiveParameter] string $passphrase): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            if ($value !== '') {
                $pairs[] = $key.'='.urlencode(trim($value));
            }
        }
        $pairs[] = 'passphrase='.urlencode(trim($passphrase));

        return md5(implode('&', $pairs));
    }

    /** ITN order and empty values are preserved; no checkout filtering or sorting. */
    public function notificationString(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            if ($key === 'signature') {
                break;
            }
            $pairs[] = $key.'='.urlencode(stripslashes($value));
        }

        return implode('&', $pairs);
    }

    public function notification(array $fields, #[\SensitiveParameter] string $passphrase): string
    {
        return md5($this->notificationString($fields).'&passphrase='.urlencode($passphrase));
    }
}
