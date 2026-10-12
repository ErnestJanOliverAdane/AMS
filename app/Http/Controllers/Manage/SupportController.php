<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\SupportRequest;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        return view('manage.support.index', [
            'requests' => SupportRequest::with(['user', 'appointment'])
                ->when($request->query('status') === 'closed', fn ($q) => $q->where('status', 'closed'), fn ($q) => $q->where('status', 'open'))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'closed' => $request->query('status') === 'closed',
        ]);
    }

    public function reply(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate(['admin_reply' => ['required', 'string', 'max:2000']]);

        $supportRequest->update(['admin_reply' => $data['admin_reply'], 'replied_at' => now(), 'status' => 'closed']);

        Notifier::send(
            $supportRequest->user,
            'Reply to your support request',
            Str::limit($data['admin_reply'], 140),
            'client.support.index',
        );

        return back()->with('status', 'Reply sent.');
    }
}
