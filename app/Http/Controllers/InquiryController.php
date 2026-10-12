<?php

namespace App\Http\Controllers;

use App\Models\GuestInquiry;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Public form on the landing page. Nobody needs an account to send it. */
class InquiryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'services' => ['nullable', 'array', 'max:12'],
            'services.*' => ['string', 'max:80'],
            'website' => ['nullable', 'string'], // hidden field: bots fill it in
        ], [
            'phone.regex' => 'Please enter a valid contact number.',
        ]);

        $back = redirect()->to(route('home').'#get-started');

        // A filled honeypot means a bot. Answer as if it worked and store nothing.
        if (! empty($data['website'])) {
            return $back->with('inquiry_sent', true);
        }

        $inquiry = GuestInquiry::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'services' => isset($data['services']) ? Str::limit(implode(', ', $data['services']), 480, '') : null,
            'message' => $data['message'],
        ]);

        Notifier::send(
            User::activeStaff()->get(),
            'New message from '.$inquiry->name,
            Str::limit($inquiry->message, 120),
            'manage.inquiries.index',
            [],
            false,
        );

        return $back->with('inquiry_sent', true);
    }
}
