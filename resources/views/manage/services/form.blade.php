@extends('layouts.app')

@section('title', $service->exists ? 'Edit service' : 'Add service')

@section('content')
    <div class="page-head"><div><h1>{{ $service->exists ? 'Edit service' : 'Add service' }}</h1></div></div>

    <form class="card form-card" method="POST" action="{{ $service->exists ? route('manage.services.update', $service) : route('manage.services.store') }}">
        @csrf
        @if ($service->exists) @method('PUT') @endif

        <div class="field">
            <label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $service->name) }}" maxlength="120" required autofocus>
            @error('name') <span class="err">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description" maxlength="1000">{{ old('description', $service->description) }}</textarea>
            @error('description') <span class="err">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label for="duration_minutes">Duration (minutes)</label>
            <input id="duration_minutes" type="number" name="duration_minutes" min="15" max="480" step="15" value="{{ old('duration_minutes', $service->duration_minutes) }}" required>
            @error('duration_minutes') <span class="err">{{ $message }}</span> @enderror
        </div>
        <label class="check">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->exists ? $service->is_active : true))>
            Clients can book this service
        </label>

        <div class="actions">
            <button class="btn primary" type="submit">Save</button>
            <a class="btn" href="{{ route('manage.services.index') }}">Cancel</a>
        </div>
    </form>
@endsection
