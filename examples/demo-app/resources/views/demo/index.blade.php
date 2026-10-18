@extends('demo.layout')
@section('title', 'Sandbox acceptance workspace')
@section('content')
<section><h2>Start a test payment</h2><p>The server creates a new order for <strong>R1.05</strong>. Browser-supplied amounts are ignored.</p><form action="/checkout" method="post">@csrf<button>Create sandbox checkout</button></form></section>
<section><h2>My test orders</h2><p class="muted">Payment completion depends on a verified provider notification and queued fulfilment.</p><div class="scroll"><table><thead><tr><th>Order reference</th><th>Amount</th><th>Order state</th><th>Completions</th></tr></thead><tbody>@forelse($orders as $order)<tr><td><a class="reference" href="/orders/{{ $order->reference }}">{{ $order->reference }}</a></td><td>R1.05</td><td>{{ $order->status }}</td><td>{{ $order->completion_count }}</td></tr>@empty<tr><td colspan="4">No test orders yet. Create your first checkout above.</td></tr>@endforelse</tbody></table></div></section>
@endsection
