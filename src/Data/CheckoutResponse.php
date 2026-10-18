<?php

namespace Vmt\PayFast\Data;

final readonly class CheckoutResponse
{
    /** Server-only: merchant_key is required by PayFast's hosted HTML form. */
    public function __construct(public string $reference, public string $url, public array $fields) {}

    public function toHtml(): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<form method="post" action="'.$escape($this->url).'">';
        foreach ($this->fields as $key => $value) {
            $html .= '<input type="hidden" name="'.$escape($key).'" value="'.$escape($value).'">';
        }

        return $html.'<button type="submit">Continue to PayFast</button></form>';
    }
}
