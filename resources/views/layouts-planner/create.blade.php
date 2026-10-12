@extends('layouts.app')

@section('title', 'New layout')

@section('content')
    <div class="page-head">
        <div>
            <h1>New site layout</h1>
            <p>Pick the services, upload a floor plan PDF (optional) and the layout is generated for you.</p>
        </div>
    </div>

    <form class="card form-card" method="POST" action="{{ route('layouts.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="field">
            <label for="name">Layout name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="120" placeholder="e.g. Main office, 2nd floor">
            @error('name')<small class="err">{{ $message }}</small>@enderror
        </div>

        <div class="field">
            <label for="plan">Floor plan PDF <span class="muted">(optional, max 15 MB, first page is used)</span></label>
            <input id="plan" name="plan" type="file" accept="application/pdf,.pdf">
            @error('plan')<small class="err">{{ $message }}</small>@enderror
        </div>

        <div class="field">
            <label for="width_m">Real width of the plan, in meters</label>
            <input id="width_m" name="width_m" type="number" step="0.5" min="5" max="500" value="{{ old('width_m', 30) }}" required>
            <small class="muted">Used to space cameras, access points and cabling to scale. You can change it later.</small>
            @error('width_m')<small class="err">{{ $message }}</small>@enderror
        </div>

        <fieldset class="field" style="border:0;padding:0;margin:0 0 16px">
            <legend style="font-weight:600;margin-bottom:8px">Services to include</legend>
            <div class="checks">
                @foreach ($layers as $key => $label)
                    <label class="check"><input type="checkbox" name="layers[]" value="{{ $key }}" @checked(in_array($key, old('layers', $preset ?: array_keys($layers)), true))> {{ $label }}</label>
                @endforeach
            </div>
            @error('layers')<small class="err">{{ $message }}</small>@enderror
        </fieldset>

        <button class="btn primary" type="submit">Generate layout</button>
    </form>
@endsection
