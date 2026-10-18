<?php

namespace Vmt\PayFast\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vmt\PayFast\Contracts\MerchantResolver;
use Vmt\PayFast\Data\CheckoutRequest;
use Vmt\PayFast\Data\CheckoutResponse;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentTransaction;

final class CheckoutService
{
    public function __construct(private MerchantResolver $merchants, private SignatureService $signatures) {}

    public function create(CheckoutRequest $request): CheckoutResponse
    {
        if ($request->amountCents < 1 || $request->amountCents > 9999999999 || $request->currency !== 'ZAR' || $request->reference === '' || strlen($request->reference) > 255 || trim($request->itemName) === '' || strlen($request->itemName) > 100 || strlen($request->itemDescription) > 255 || strlen($request->firstName) > 100 || strlen($request->lastName) > 100 || strlen($request->email) > 100 || ($request->email !== '' && ! filter_var($request->email, FILTER_VALIDATE_EMAIL))) {
            throw new PaymentException('Invalid checkout request. Supply an authorized server-side ZAR amount and valid item/customer fields.');
        }
        foreach ([$request->tenantId, $request->orderReference, $request->merchantContext] as $value) {
            if ($value !== null && strlen($value) > 255) {
                throw new PaymentException('Checkout context is too long.');
            }
        }
        $merchant = $this->merchants->resolve($request->merchantContext, $request->tenantId);
        $merchant->validate();
        if ($merchant->tenantId !== $request->tenantId) {
            throw new PaymentException('Merchant tenant mismatch.');
        }
        $reference = (string) Str::uuid();
        // Explicit hosted checkout order; metadata is never forwarded to PayFast.
        $fields = [
            'merchant_id' => $merchant->merchantId,
            'merchant_key' => $merchant->merchantKey,
            'return_url' => $merchant->returnUrl.(str_contains($merchant->returnUrl, '?') ? '&' : '?').http_build_query(['payfast_reference' => $reference]),
            'cancel_url' => $merchant->cancelUrl.(str_contains($merchant->cancelUrl, '?') ? '&' : '?').http_build_query(['payfast_reference' => $reference]),
            'notify_url' => $merchant->notifyUrl,
            'name_first' => $request->firstName,
            'name_last' => $request->lastName,
            'email_address' => $request->email,
            'm_payment_id' => $reference,
            'amount' => intdiv($request->amountCents, 100).'.'.str_pad((string) ($request->amountCents % 100), 2, '0', STR_PAD_LEFT),
            'item_name' => $request->itemName,
            'item_description' => $request->itemDescription,
        ];
        $fields = array_filter(array_map(trim(...), $fields), static fn ($value) => $value !== '');
        $fields['signature'] = $this->signatures->checkout($fields, $merchant->passphrase);
        DB::transaction(function () use ($request, $merchant, $reference) {
            $transaction = new PaymentTransaction;
            $transaction->forceFill([
                'id' => (string) Str::uuid(), 'reference' => $reference,
                'application_reference' => $request->reference, 'order_reference' => $request->orderReference,
                'merchant_identity' => $merchant->identity, 'tenant_id' => $merchant->tenantId,
                'amount_cents' => $request->amountCents, 'currency' => 'ZAR', 'mode' => $merchant->mode,
                'status' => PaymentStatus::Created, 'metadata' => $request->metadata,
                'credential_snapshot' => ['merchant_id' => $merchant->merchantId, 'passphrase' => $merchant->passphrase],
            ])->save();
            $transaction->status = PaymentStatus::Pending;
            $transaction->save();
        });

        return new CheckoutResponse($reference, $merchant->baseUrl().'/eng/process', $fields);
    }
}
