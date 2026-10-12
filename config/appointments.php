<?php

return [

    /*
    | Time zone used for working hours, slots and "is this in the past" checks.
    | Appointments are stored as a plain date plus start and end time in this zone.
    */
    'timezone' => env('APPOINTMENTS_TIMEZONE', 'Asia/Manila'),

    /* Minutes between the start times offered to clients. */
    'slot_interval_minutes' => 30,

    /* Clients cannot book a slot that starts sooner than this many hours from now. */
    'min_notice_hours' => 2,

    /* How far ahead clients can book. */
    'max_days_ahead' => 60,

    /* Pending or approved upcoming appointments one client may hold at once. */
    'max_active_per_client' => 3,

    /* Times a client can move one appointment to a new slot. */
    'max_reschedules' => 2,

    /* The reminder command notifies clients this many hours before the start time. */
    'reminder_hours_before' => 24,

    /*
    | Booking is restricted when a client reaches this many strikes inside the window.
    | A strike is cancelling an approved appointment, or a no-show.
    */
    'restriction' => [
        'threshold' => 3,
        'window_days' => 90,
    ],

];
