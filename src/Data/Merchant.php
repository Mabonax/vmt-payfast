<?php

namespace Vmt\PayFast\Data;

use Vmt\PayFast\Exceptions\PaymentException;

final readonly class Merchant
{
    public function __construct(
        public string $identity,
        public ?string $tenantId,
        public string $mode,
        public string $merchantId,
        #[\SensitiveParameter] public string $merchantKey,
        #[\SensitiveParameter] public string $passphrase,
        public string $returnUrl,
        public string $cancelUrl,
        public string $notifyUrl,
    ) {}

    public function validate(): void
    {
        if (! in_array($this->mode, ['sandbox', 'production'], true) || $this->identity === '' || strlen($this->identity) > 255 || ! preg_match('/^[0-9]{8}$/D', $this->merchantId) || trim($this->merchantKey) === '' || trim($this->passphrase) === '' || trim($this->passphrase) !== $this->passphrase) {
            throw new PaymentException('Invalid PayFast mode or missing merchant credentials/passphrase.');
        }
        if ($this->mode === 'production' && ($this->merchantId === '10000100' || $this->merchantKey === '46f0cd694581a')) {
            throw new PaymentException('Known sandbox credentials cannot be used in production.');
        }
        foreach ([$this->returnUrl, $this->cancelUrl, $this->notifyUrl] as $url) {
            if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
                throw new PaymentException('PayFast callback URLs must be HTTPS URLs without credentials.');
            }
        }
        if (app()->environment('production') && $this->mode !== 'production') {
            throw new PaymentException('Sandbox payments are disabled in a production application.');
        }
    }

    public function baseUrl(): string
    {
        return $this->mode === 'sandbox' ? 'https://sandbox.payfast.co.za' : 'https://www.payfast.co.za';
    }
}
