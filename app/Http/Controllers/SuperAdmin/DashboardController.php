<?php

namespace App\Http\Controllers\SuperAdmin;

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
        $stats = [];

        foreach (Role::cases() as $role) {
            $stats[$role->value] = [
                'label' => $role->label().'s',
                'total' => User::withRole($role)->count(),
                'active' => User::withRole($role)->where('is_active', true)->count(),
            ];
        }

        return view('dashboards.super-admin', [
            'analytics' => DashboardAnalytics::build(),
            'vault' => DashboardAnalytics::vault(),
            'counts' => AppointmentStats::counts(),
            'pending' => AppointmentStats::pending(),
            'today' => AppointmentStats::today(),
            'stats' => $stats,
            'recent' => User::latest()->take(6)->get(),
        ]);
    }
}
