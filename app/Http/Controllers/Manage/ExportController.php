<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\Scheduling;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV download and a printable list that browsers can save as PDF. */
class ExportController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        $query = Appointment::with(['user', 'service'])
            ->filter($filters)
            ->orderBy('date')
            ->orderBy('start_time');

        $filename = 'appointments-'.Scheduling::now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // Byte order mark so Excel reads the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Reference', 'Date', 'Start', 'End', 'Client', 'Email', 'Service', 'Status', 'Reason', 'Admin note', 'Requested on'], ',', '"', '');

            $query->chunk(500, function ($appointments) use ($out) {
                foreach ($appointments as $appointment) {
                    fputcsv($out, array_map(self::safe(...), [
                        $appointment->reference,
                        $appointment->date->format('Y-m-d'),
                        substr($appointment->start_time, 0, 5),
                        substr($appointment->end_time, 0, 5),
                        $appointment->user->name,
                        $appointment->user->email,
                        $appointment->service->name,
                        $appointment->status->label(),
                        (string) $appointment->reason,
                        (string) $appointment->admin_note,
                        $appointment->created_at?->format('Y-m-d H:i') ?? '',
                    ]), ',', '"', '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request): View
    {
        $filters = $this->filters($request);

        return view('manage.export.print', [
            'appointments' => Appointment::with(['user', 'service'])
                ->filter($filters)
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(1000)
                ->get(),
            'filters' => $filters,
            'generatedAt' => Scheduling::now(),
        ]);
    }

    /** Stops spreadsheet programs from running text that starts like a formula. */
    public static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }

    /**
     * @return array{status?: ?string, q?: ?string, from?: ?string, to?: ?string}
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }
}
