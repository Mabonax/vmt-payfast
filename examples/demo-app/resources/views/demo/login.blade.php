@extends('demo.layout')
@section('title', 'Sign in to the sandbox demo')
@section('content')
<section><p>Create a local tester with <code>php artisan demo:create-user your-email</code> before signing in.</p>
<form action="/login" method="post">@csrf<label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email') }}"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button>Sign in</button></form></section>
@endsection
