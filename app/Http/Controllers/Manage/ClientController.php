<?php

namespace App\Http\Controllers\Manage;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Notifier;
use App\Support\Restrictions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Booking restrictions for clients who keep cancelling or not showing up. */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $clients = User::where('role', Role::User->value)
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->when($request->boolean('restricted'), fn ($query) => $query->whereNotNull('booking_restricted_at'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $strikes = $clients->getCollection()->mapWithKeys(fn (User $user) => [$user->id => Restrictions::strikes($user)]);

        return view('manage.clients.index', ['clients' => $clients, 'strikes' => $strikes, 'q' => $q]);
    }

    public function restrict(Request $request, User $client): RedirectResponse
    {
        abort_unless($client->role === Role::User, 404);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        Restrictions::restrict($client, $data['reason'] ?? 'Restricted by staff.');
        Notifier::send($client, 'Booking restricted', 'New appointment requests are paused on your account. Send a support request if you think this is a mistake.', 'client.support.index');

        return back()->with('status', "{$client->name} is restricted.");
    }

    public function lift(User $client): RedirectResponse
    {
        abort_unless($client->role === Role::User, 404);

        Restrictions::lift($client);
        Notifier::send($client, 'Booking restored', 'You can request appointments again.', 'client.appointments.create');

        return back()->with('status', "Restriction lifted for {$client->name}.");
    }
}
