<?php

namespace Vmt\PayFast\Services;

use Illuminate\Support\Facades\Http;
use Vmt\PayFast\Contracts\ProviderSource;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentTransaction;

final class ItnValidationService
{
    public function __construct(private SignatureService $signatures, private ProviderSource $source) {}

    public function validate(array $fields, PaymentTransaction $payment, string $ip): void
    {
        $credentials = $payment->credential_snapshot;
        if (! hash_equals($this->signatures->notification($fields, $credentials['passphrase']), $fields['signature'])) {
            throw new PaymentException('invalid_signature');
        }
        if (! $this->source->accepts($ip)) {
            throw new PaymentException('invalid_source');
        }
        if (($fields['merchant_id'] ?? '') !== $credentials['merchant_id']) {
            throw new PaymentException('incorrect_merchant');
        }
        $amount = $fields['amount_gross'] ?? '';
        if (! preg_match('/^(0|[1-9][0-9]{0,8})\.([0-9]{2})$/D', $amount, $parts) || ((int) $parts[1] * 100 + (int) $parts[2]) !== $payment->amount_cents) {
            throw new PaymentException('incorrect_amount');
        }
        if (! preg_match('/^[0-9]{1,64}$/D', $fields['pf_payment_id'] ?? '')) {
            throw new PaymentException('invalid_provider_reference');
        }
        // Phase 1 only establishes completion from the documented COMPLETE notification.
        if (($fields['payment_status'] ?? '') !== 'COMPLETE') {
            throw new PaymentException('unsupported_provider_status');
        }
        $base = $payment->mode === 'sandbox' ? 'https://sandbox.payfast.co.za' : 'https://www.payfast.co.za';
        $response = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()
            ->withBody($this->signatures->notificationString($fields), 'application/x-www-form-urlencoded')
            ->post($base.'/eng/query/validate');
        if (! $response->successful() || trim($response->body()) !== 'VALID') {
            throw new PaymentException('provider_validation_failed');
        }
    }
}
