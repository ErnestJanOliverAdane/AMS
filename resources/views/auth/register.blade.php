@extends('layouts.guest')

@section('title', 'Create account')

@section('content')
    <div class="card guest-card">
        <h1>Create account</h1>
        <p class="muted">New accounts start as a standard User.</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="field">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                @error('email') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
                <span class="hint">At least 8 characters.</span>
                @error('password') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>

            <button class="btn primary block" type="submit">Create account</button>
        </form>
    </div>

    <p class="guest-foot">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
