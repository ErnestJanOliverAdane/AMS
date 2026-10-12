<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('manage.services.index', ['services' => Service::withCount('appointments')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('manage.services.form', ['service' => new Service(['duration_minutes' => 60, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Service::create($this->data($request));

        return redirect()->route('manage.services.index')->with('status', 'Service added.');
    }

    public function edit(Service $service): View
    {
        return view('manage.services.form', ['service' => $service]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->data($request, $service));

        return redirect()->route('manage.services.index')->with('status', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        if ($service->appointments()->exists()) {
            $service->update(['is_active' => false]);

            return back()->with('status', 'Service has appointments, so it was deactivated instead of deleted.');
        }

        $service->delete();

        return back()->with('status', 'Service deleted.');
    }

    private function data(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')->ignore($service?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'between:15,480'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
