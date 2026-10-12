<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Client\AppointmentController as ClientAppointmentController;
use App\Http\Controllers\Client\SupportController as ClientSupportController;
use App\Http\Controllers\Manage\AppointmentController as ManageAppointmentController;
use App\Http\Controllers\Manage\CalendarController;
use App\Http\Controllers\Manage\ClientController;
use App\Http\Controllers\Manage\ExportController;
use App\Http\Controllers\Manage\ReportController;
use App\Http\Controllers\Manage\ScheduleController;
use App\Http\Controllers\Manage\ServiceController;
use App\Http\Controllers\Manage\SupportController as ManageSupportController;
use App\Http\Controllers\Manage\UserController as ManageUserController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\Manage\InquiryController as ManageInquiryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SiteLayoutController;
use App\Http\Controllers\SlotSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AdminController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
})->name('home');

/*
|--------------------------------------------------------------------------
| Guests
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
});

// Anyone can check open slots before signing in.
Route::get('/slots', [SlotSearchController::class, 'index'])->name('slots.search');

Route::post('/inquiries', [InquiryController::class, 'store'])->middleware('throttle:5,1')->name('inquiries.store');

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Any signed-in role
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:super_admin,admin,user'])->group(function () {
    // Sends each role to its own dashboard.
    Route::get('/dashboard', fn (Request $request) => redirect()->route($request->user()->role->dashboardRoute()))
        ->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/layouts', [SiteLayoutController::class, 'index'])->name('layouts.index');
    Route::get('/layouts/new', [SiteLayoutController::class, 'create'])->name('layouts.create');
    Route::post('/layouts', [SiteLayoutController::class, 'store'])->name('layouts.store');
    Route::post('/layouts/camera', [SiteLayoutController::class, 'quick'])->name('layouts.quick');
    Route::get('/layouts/{layout}', [SiteLayoutController::class, 'show'])->name('layouts.show');
    Route::put('/layouts/{layout}', [SiteLayoutController::class, 'update'])->name('layouts.update');
    Route::get('/layouts/{layout}/plan', [SiteLayoutController::class, 'plan'])->name('layouts.plan');
    Route::delete('/layouts/{layout}', [SiteLayoutController::class, 'destroy'])->name('layouts.destroy');
});

/*
|--------------------------------------------------------------------------
| Super Admin only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('admins', AdminController::class)
        ->parameters(['admins' => 'account'])
        ->except('show');
});

/*
|--------------------------------------------------------------------------
| Admin only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| User only
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:user'])->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Clients: their own appointments
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:user'])->prefix('my')->name('client.')->group(function () {
    Route::get('/appointments', [ClientAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/new', [ClientAppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [ClientAppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/{appointment}', [ClientAppointmentController::class, 'show'])->name('appointments.show');
    Route::post('/appointments/{appointment}/cancel', [ClientAppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('/appointments/{appointment}/reschedule', [ClientAppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::put('/appointments/{appointment}/reschedule', [ClientAppointmentController::class, 'updateSchedule'])->name('appointments.reschedule.update');
    Route::get('/appointments/{appointment}/slip', [ClientAppointmentController::class, 'slip'])->name('appointments.slip');
    Route::post('/appointments/{appointment}/feedback', [ClientAppointmentController::class, 'feedback'])->name('appointments.feedback');

    Route::get('/support', [ClientSupportController::class, 'index'])->name('support.index');
    Route::post('/support', [ClientSupportController::class, 'store'])->name('support.store');
});

/*
|--------------------------------------------------------------------------
| Admin and Super Admin: manage "User" accounts
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:super_admin,admin'])->prefix('manage')->name('manage.')->group(function () {
    Route::resource('users', ManageUserController::class)
        ->parameters(['users' => 'account'])
        ->except('show');
});

/*
|--------------------------------------------------------------------------
| Admin and Super Admin: appointments
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:super_admin,admin'])->prefix('manage')->name('manage.')->group(function () {
    Route::get('/appointments', [ManageAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{appointment}', [ManageAppointmentController::class, 'show'])->name('appointments.show');
    Route::post('/appointments/{appointment}/approve', [ManageAppointmentController::class, 'approve'])->name('appointments.approve');
    Route::post('/appointments/{appointment}/reject', [ManageAppointmentController::class, 'reject'])->name('appointments.reject');
    Route::post('/appointments/{appointment}/complete', [ManageAppointmentController::class, 'complete'])->name('appointments.complete');
    Route::post('/appointments/{appointment}/no-show', [ManageAppointmentController::class, 'noShow'])->name('appointments.no-show');
    Route::post('/appointments/{appointment}/cancel', [ManageAppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('/appointments/{appointment}/reschedule', [ManageAppointmentController::class, 'rescheduleForm'])->name('appointments.reschedule');
    Route::put('/appointments/{appointment}/reschedule', [ManageAppointmentController::class, 'reschedule'])->name('appointments.reschedule.update');

    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/export/csv', [ExportController::class, 'csv'])->name('export.csv');
    Route::get('/export/print', [ExportController::class, 'print'])->name('export.print');

    Route::get('/schedule', [ScheduleController::class, 'edit'])->name('schedule.edit');
    Route::put('/schedule/hours', [ScheduleController::class, 'hours'])->name('schedule.hours');
    Route::post('/schedule/blocked', [ScheduleController::class, 'storeBlocked'])->name('schedule.blocked.store');
    Route::delete('/schedule/blocked/{blocked}', [ScheduleController::class, 'destroyBlocked'])->name('schedule.blocked.destroy');

    Route::resource('services', ServiceController::class)->except('show');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients/{client}/restrict', [ClientController::class, 'restrict'])->name('clients.restrict');
    Route::post('/clients/{client}/lift', [ClientController::class, 'lift'])->name('clients.lift');

    Route::get('/inquiries', [ManageInquiryController::class, 'index'])->name('inquiries.index');
    Route::post('/inquiries/{inquiry}/handled', [ManageInquiryController::class, 'handle'])->name('inquiries.handle');

    Route::get('/support', [ManageSupportController::class, 'index'])->name('support.index');
    Route::post('/support/{supportRequest}/reply', [ManageSupportController::class, 'reply'])->name('support.reply');
});
