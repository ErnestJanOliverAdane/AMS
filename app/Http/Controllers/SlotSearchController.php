<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Support\Scheduling;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public page: anyone can check open dates and times before signing in. */
class SlotSearchController extends Controller
{
    public function index(Request $request, Scheduling $scheduling): View
    {
        $services = Service::active()->orderBy('name')->get();
        $service = $services->firstWhere('id', (int) $request->query('service_id'));
        $date = (string) $request->query('date', '');

        return view('slots.search', [
            'services' => $services,
            'service' => $service,
            'date' => $date,
            'days' => $service ? $scheduling->upcomingAvailability($service) : collect(),
            'slots' => $service && $date !== '' ? $scheduling->slotsFor($service, $date) : collect(),
        ]);
    }
}
