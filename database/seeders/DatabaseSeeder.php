<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Support\Scheduling;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AppointmentSeeder::class);

        $password = config('portal.seed_password');

        if (! app()->environment('local', 'testing') && $password === 'password') {
            $this->command?->error('Set PORTAL_SEED_PASSWORD in .env before seeding outside local development.');

            return;
        }

        $accounts = [
            ['Super Admin', 'superadmin@konektado.test', Role::SuperAdmin],
            ['Demo Admin', 'admin@konektado.test', Role::Admin],
            ['Demo User', 'user@konektado.test', Role::User],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            // The demo Admin and User are for local development only.
            if ($role !== Role::SuperAdmin && ! app()->environment('local', 'testing')) {
                continue;
            }

            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => $role, 'is_active' => true],
            );
        }

        if (app()->environment('local', 'testing')) {
            $this->seedDemoAppointments();
        }
    }

    /** A few sample appointments so the dashboards and calendar are not empty while developing. */
    private function seedDemoAppointments(): void
    {
        $client = User::where('email', 'user@konektado.test')->first();
        $services = Service::orderBy('id')->take(4)->get();

        if (! $client || $services->count() < 4) {
            return;
        }

        $today = Scheduling::now();

        $samples = [
            ['APT-DEMO01', 1, $today->addDay(), '09:00:00', '10:00:00', AppointmentStatus::Pending],
            ['APT-DEMO02', 2, $today->addDays(2), '10:00:00', '11:30:00', AppointmentStatus::Approved],
            ['APT-DEMO03', 0, $today->subDays(3), '13:00:00', '14:00:00', AppointmentStatus::Completed],
            ['APT-DEMO04', 3, $today->addDays(4), '14:00:00', '15:30:00', AppointmentStatus::Pending],
        ];

        foreach ($samples as [$reference, $serviceIndex, $day, $start, $end, $status]) {
            Appointment::firstOrCreate(
                ['reference' => $reference],
                [
                    'user_id' => $client->id,
                    'service_id' => $services[$serviceIndex]->id,
                    'date' => $day->format('Y-m-d'),
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => $status,
                    'reason' => 'Sample appointment for the demo account.',
                ],
            );
        }
    }
}
