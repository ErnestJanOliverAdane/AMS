<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AppointmentStats;
use App\Support\DashboardAnalytics;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboards.admin', [
            'analytics' => DashboardAnalytics::build(),
            'vault' => DashboardAnalytics::vault(),
            'counts' => AppointmentStats::counts(),
            'pending' => AppointmentStats::pending(),
            'today' => AppointmentStats::today(),
            'total' => User::withRole(Role::User)->count(),
            'active' => User::withRole(Role::User)->where('is_active', true)->count(),
            'recent' => User::withRole(Role::User)->latest()->take(6)->get(),
        ]);
    }
}
