<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;

/** Services and default working hours. Safe to run again, it never overwrites edits. */
class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['Computer Troubleshooting', 'Diagnosis and repair of computer problems.', 60],
            ['End-to-End Terminations', 'Network cable termination and testing.', 90],
            ['Data Cabling', 'Structured data cabling for offices.', 120],
            ['WiFi Setup', 'WiFi planning, installation and configuration.', 90],
            ['IP PBX Installation', 'IP PBX and phone system setup.', 120],
            ['Paging System', 'Customized paging and intercom setup.', 90],
            ['CCTV Installation', 'CCTV camera installation and recorder setup.', 120],
            ['Website Design', 'Planning session for a tailored business website.', 60],
            ['Site Inspection / Consultation', 'Assessment of your IT needs.', 60],
        ];

        foreach ($services as [$name, $description, $minutes]) {
            Service::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'duration_minutes' => $minutes, 'is_active' => true],
            );
        }

        foreach (range(0, 6) as $weekday) {
            WorkingHour::firstOrCreate(
                ['weekday' => $weekday],
                [
                    'is_open' => $weekday !== 0,
                    'opens_at' => '08:00:00',
                    'closes_at' => '17:00:00',
                    'break_starts_at' => '12:00:00',
                    'break_ends_at' => '13:00:00',
                    'capacity' => 1,
                ],
            );
        }
    }
}
