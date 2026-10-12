@extends('layouts.app')

@section('title', 'Support')

@section('content')
    <div class="page-head">
        <div>
            <h1>Support request</h1>
            <p>Ask for a special appointment arrangement or help with your account.</p>
        </div>
    </div>

    <div class="grid2">
        <form class="card" method="POST" action="{{ route('client.support.store') }}">
            @csrf
            <div class="field">
                <label for="subject">Subject</label>
                <input id="subject" type="text" name="subject" value="{{ old('subject') }}" maxlength="150" required>
                @error('subject') <span class="err">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="appointment_id">Related appointment (optional)</label>
                <select id="appointment_id" name="appointment_id">
                    <option value="">None</option>
                    @foreach ($appointments as $item)
                        <option value="{{ $item->id }}" @selected((int) old('appointment_id') === $item->id)>{{ $item->reference }} · {{ $item->service->name }} · {{ $item->dateLabel() }}</option>
                    @endforeach
                </select>
                @error('appointment_id') <span class="err">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="message">Message</label>
                <textarea id="message" name="message" maxlength="2000" required>{{ old('message') }}</textarea>
                @error('message') <span class="err">{{ $message }}</span> @enderror
            </div>
            <button class="btn primary" type="submit">Send request</button>
        </form>

        <div class="card">
            <h2 style="font-size:1.1rem;margin-bottom:8px">Your requests</h2>
            @forelse ($requests as $item)
                <div class="notice-row">
                    <div>
                        <strong>{{ $item->subject }}</strong>
                        <p class="muted" style="font-size:13px">{{ $item->created_at?->format('M j, Y') }} · {{ ucfirst($item->status) }}</p>
                        <p style="margin-top:6px">{{ $item->message }}</p>
                        @if ($item->admin_reply)
                            <p style="margin-top:8px;padding:10px;border-radius:8px;background:var(--surface-2)"><strong>Reply:</strong> {{ $item->admin_reply }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted">No requests yet.</p>
            @endforelse
        </div>
    </div>
@endsection
