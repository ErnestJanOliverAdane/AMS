@php $editing = $account !== null; @endphp

<div class="field">
    <label for="name">Name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $account?->name) }}" required autofocus autocomplete="off">
    @error('name') <span class="err">{{ $message }}</span> @enderror
</div>

<div class="field">
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email', $account?->email) }}" required autocomplete="off">
    @error('email') <span class="err">{{ $message }}</span> @enderror
</div>

<div class="row2">
    <div class="field">
        <label for="password">{{ $editing ? 'New password' : 'Password' }}</label>
        <input id="password" type="password" name="password" @required(! $editing) autocomplete="new-password">
        <span class="hint">{{ $editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.' }}</span>
        @error('password') <span class="err">{{ $message }}</span> @enderror
    </div>
    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" @required(! $editing) autocomplete="new-password">
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<label class="check">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account?->is_active ?? true))>
    Account is active (deactivated accounts cannot sign in)
</label>
