@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <div class="card guest-card">
        <h1>Sign in</h1>
        <p class="muted">Super Admins, Admins and Users all sign in here.</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                @error('password') <span class="err">{{ $message }}</span> @enderror
            </div>

            <label class="check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Keep me signed in
            </label>

            <button class="btn primary block" type="submit">Sign in</button>
        </form>

        @if (app()->environment('local'))
            <div class="demo">
                Local demo accounts (password <code>{{ config('portal.seed_password') }}</code>):<br>
                <code>superadmin@konektado.test</code><br>
                <code>admin@konektado.test</code><br>
                <code>user@konektado.test</code>
            </div>
        @endif
    </div>

    @if (config('portal.allow_registration'))
        <p class="guest-foot">New here? <a href="{{ route('register') }}">Create an account</a></p>
    @endif
@endsection
