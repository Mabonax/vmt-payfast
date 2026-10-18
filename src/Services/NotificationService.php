<?php

namespace Vmt\PayFast\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vmt\PayFast\Data\PaymentResult;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentEvent;
use Vmt\PayFast\Models\PaymentTransaction;

final class NotificationService
{
    public function __construct(private ItnParser $parser, private ItnValidationService $validation) {}

    public function process(string $body, string $ip): PaymentResult
    {
        $received = now();
        $payment = null;
        $fields = [];
        try {
            $fields = $this->parser->parse($body);
            $payment = PaymentTransaction::where('reference', $fields['m_payment_id'] ?? '')->first();
            if (! $payment) {
                throw new PaymentException('unknown_transaction');
            }
            // Network validation precedes locks. All mutable state is checked again under lock.
            $this->validation->validate($fields, $payment, $ip);

            return DB::transaction(function () use ($payment, $fields, $received) {
                $locked = PaymentTransaction::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $providerId = $fields['pf_payment_id'];
                if ($locked->provider_payment_id !== null && $locked->provider_payment_id !== $providerId) {
                    throw new PaymentException('provider_reference_conflict');
                }
                if ($locked->status === PaymentStatus::Completed) {
                    $outcome = 'duplicate';
                } elseif (! $locked->status->allows(PaymentStatus::Completed)) {
                    $outcome = 'ignored_terminal';
                } else {
                    $locked->forceFill(['status' => PaymentStatus::Completed, 'provider_payment_id' => $providerId, 'payment_method' => null, 'completed_at' => now()])->save();
                    DB::table('payfast_outbox')->insert(['id' => (string) Str::uuid(), 'transaction_id' => $locked->id, 'status' => PaymentStatus::Completed->value]);
                    $outcome = 'completed';
                }
                $this->audit($locked->id, 'COMPLETE', $outcome, null, $received);

                return new PaymentResult($locked->reference, $outcome);
            }, 3);
        } catch (PaymentException $exception) {
            $this->audit($payment?->id, null, 'rejected', $exception->getMessage(), $received);
            throw $exception;
        } catch (\Throwable $exception) {
            // Keep infrastructure failures retryable. A broken database may also prevent this audit.
            try {
                $this->audit($payment?->id, null, 'retryable_failure', 'processing_unavailable', $received);
            } catch (\Throwable) {
                // Host infrastructure monitoring must capture redacted 5xx incidents.
            }
            throw $exception;
        }
    }

    private function audit(?string $id, ?string $status, string $outcome, ?string $error, mixed $received): void
    {
        (new PaymentEvent)->forceFill(['transaction_id' => $id, 'provider_status' => $status, 'outcome' => $outcome, 'error_category' => $error, 'received_at' => $received, 'processed_at' => now()])->save();
    }
}
