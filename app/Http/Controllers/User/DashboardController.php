<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\Scheduling;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $next = Appointment::with('service')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('date', '>=', Scheduling::now()->format('Y-m-d'))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->first(fn (Appointment $appointment) => $appointment->startsAt()->gte(Scheduling::now()));

        return view('dashboards.user', [
            'user' => $user,
            'vault' => [
                ['label' => 'My appointment reasons', 'count' => $user->appointments()->whereNotNull('reason')->count()],
                ['label' => 'My support messages', 'count' => \App\Models\SupportRequest::where('user_id', $user->id)->count()],
            ],
            'next' => $next,
            'unread' => $user->unreadNotifications()->latest()->take(5)->get(),
        ]);
    }
}
