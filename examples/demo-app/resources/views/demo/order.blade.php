@extends('demo.layout')
@section('title', 'Test order and payment audit')
@section('content')
<section><p class="reference">{{ $order->reference }}</p><div class="metrics"><p>Amount<br><strong>R1.05</strong></p><p>Payment state<br><strong>{{ $payment?->status->value ?? 'No payment attempt' }}</strong></p><p>Order state<br><strong>{{ $order->status }}</strong></p><p>Completion count<br><strong>{{ $order->completion_count }}</strong></p></div>
@if($payment?->status->value === 'PENDING')<p role="status">Pending verification: awaiting a verified PayFast notification. Returning or cancelling in the browser cannot complete this payment.</p>@endif
@if($payment?->status->value === 'COMPLETED' && $order->status !== 'COMPLETED')<p role="status">Payment verified. Queued order fulfilment is pending; run the outbox dispatcher and queue worker.</p>@endif
<a href="/orders/{{ $order->reference }}">Refresh current state</a></section>
<section><h2>Redacted payment audit</h2><p class="muted">Only processing outcomes and timestamps are displayed. No raw callback, credentials or customer details.</p><div class="scroll"><table><thead><tr><th>Provider state</th><th>Outcome</th><th>Error category</th><th>Received</th><th>Processed</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $event->provider_status ?? '—' }}</td><td>{{ $event->outcome }}</td><td>{{ $event->error_category ?? '—' }}</td><td>{{ $event->received_at }}</td><td>{{ $event->processed_at }}</td></tr>@empty<tr><td colspan="5">No provider notifications recorded for this order.</td></tr>@endforelse</tbody></table></div></section>
@endsection
