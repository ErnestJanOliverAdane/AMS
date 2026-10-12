@props(['value'])
@php
    $key = $value instanceof \BackedEnum ? $value->value : (string) $value;
    $label = $value instanceof \App\Enums\AppointmentStatus ? $value->label() : ucfirst(str_replace('_', ' ', $key));
@endphp
<span class="st st-{{ $key }}">{{ $label }}</span>
