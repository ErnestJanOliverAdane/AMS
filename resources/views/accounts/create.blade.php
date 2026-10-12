@extends('layouts.app')

@section('title', 'Add '.$singular)

@section('content')
    <div class="page-head">
        <div>
            <h1>Add {{ $singular }}</h1>
            <p><a href="{{ route($routeBase.'.index') }}">Back to {{ strtolower($title) }}</a></p>
        </div>
    </div>

    <div class="card form-card">
        <form method="POST" action="{{ route($routeBase.'.store') }}">
            @csrf
            @include('accounts.form')
            <div class="actions">
                <button class="btn primary" type="submit">Create {{ $singular }}</button>
                <a class="btn" href="{{ route($routeBase.'.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
