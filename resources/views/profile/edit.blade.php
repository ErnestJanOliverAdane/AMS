@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <div class="page-head">
        <div>
            <h1>Profile</h1>
            <p>Update your name, email or password.</p>
        </div>
    </div>

    <div class="card form-card">
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                @error('name') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                @error('email') <span class="err">{{ $message }}</span> @enderror
            </div>

            <h2 style="font-size:1.05rem;margin:26px 0 14px">Change password</h2>
            <p class="muted" style="margin-bottom:14px">Leave these blank to keep your current password.</p>

            <div class="field">
                <label for="current_password">Current password</label>
                <input id="current_password" type="password" name="current_password" autocomplete="current-password">
                @error('current_password') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="row2">
                <div class="field">
                    <label for="password">New password</label>
                    <input id="password" type="password" name="password" autocomplete="new-password">
                    @error('password') <span class="err">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
                </div>
            </div>

            <button class="btn primary" type="submit">Save changes</button>
        </form>
    </div>
@endsection
