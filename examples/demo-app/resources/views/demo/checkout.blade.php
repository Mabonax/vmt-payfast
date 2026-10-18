@extends('demo.layout')
@section('title', 'Continue to PayFast sandbox')
@section('content')
<section><p>Your pending test order has been saved. Submit this form to continue to PayFast's simulated payment flow.</p>{!! $form !!}<p class="muted">Leaving checkout does not establish failure or cancellation.</p></section>
@endsection
