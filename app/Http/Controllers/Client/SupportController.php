<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Clients ask the admins for special appointment considerations. */
class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        return view('client.support.index', [
            'requests' => SupportRequest::with('appointment')->where('user_id', $userId)->latest()->get(),
            'appointments' => Appointment::with('service')->where('user_id', $userId)->orderByDesc('date')->limit(30)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'appointment_id' => ['nullable', 'integer', Rule::exists('appointments', 'id')->where('user_id', $user->id)],
        ]);

        $supportRequest = SupportRequest::create([
            'user_id' => $user->id,
            'appointment_id' => $data['appointment_id'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
        ]);

        Notifier::send(
            User::activeStaff()->get(),
            'Support request: '.$supportRequest->subject,
            "{$user->name}: ".Str::limit($supportRequest->message, 120),
            'manage.support.index',
            [],
            false,
        );

        return back()->with('status', 'Your request was sent. We will reply here.');
    }
}
