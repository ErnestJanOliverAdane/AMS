<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\SiteLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Visual site layouts: CCTV, WiFi, cabinets, cabling, paging, termination and IP PBX on a floor plan. */
class SiteLayoutController extends Controller
{
    public function index(Request $request): View
    {
        $query = SiteLayout::with('user')->latest('updated_at');

        if (! $this->isStaff($request)) {
            $query->where('user_id', $request->user()->id);
        }

        return view('layouts-planner.index', ['layouts' => $query->paginate(12), 'staff' => $this->isStaff($request)]);
    }

    public function create(Request $request): View
    {
        return view('layouts-planner.create', ['layers' => SiteLayout::LAYERS, 'preset' => (array) $request->query('layers', [])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'width_m' => ['required', 'numeric', 'min:5', 'max:500'],
            'layers' => ['required', 'array', 'min:1'],
            'layers.*' => ['string', Rule::in(array_keys(SiteLayout::LAYERS))],
            'plan' => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
        ], ['layers.required' => 'Choose at least one service.']);

        $layout = new SiteLayout([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'width_m' => $data['width_m'],
            'layers' => array_values($data['layers']),
            'settings' => ['density' => 2, 'ratio' => 0.66, 'camera_range' => 12, 'camera_fov' => 90, 'height_m' => null],
            'devices' => [],
        ]);

        if ($file = $request->file('plan')) {
            $layout->plan_path = $file->store('layouts');
            $layout->plan_name = mb_substr($file->getClientOriginalName(), 0, 200);
        }

        $layout->save();

        return redirect()->route('layouts.show', ['layout' => $layout, 'auto' => 1]);
    }

    /** One tap from the list: a new "camera scan" layout with every service, opened straight in the camera. */
    public function quick(Request $request): RedirectResponse
    {
        $data = $request->validate(['width_m' => ['required', 'numeric', 'min:3', 'max:500']]);

        $layout = SiteLayout::create([
            'user_id' => $request->user()->id,
            'name' => 'Camera scan '.now()->timezone(config('appointments.timezone'))->format('M j, g:i A'),
            'width_m' => $data['width_m'],
            'layers' => array_keys(SiteLayout::LAYERS),
            'settings' => ['density' => 2, 'ratio' => 0.66, 'camera_range' => 12, 'camera_fov' => 90, 'height_m' => null, 'ceiling' => 3],
            'devices' => [],
        ]);

        return redirect()->route('layouts.show', ['layout' => $layout, 'auto' => 1, 'camera' => 1]);
    }

    public function show(Request $request, SiteLayout $layout): View
    {
        $this->authorizeLayout($request, $layout);

        return view('layouts-planner.show', ['layout' => $layout, 'layerLabels' => SiteLayout::LAYERS, 'auto' => $request->boolean('auto')]);
    }

    /** Saves from the editor (JSON). */
    public function update(Request $request, SiteLayout $layout): JsonResponse
    {
        $this->authorizeLayout($request, $layout);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'width_m' => ['required', 'numeric', 'min:5', 'max:500'],
            'layers' => ['required', 'array', 'min:1'],
            'layers.*' => ['string', Rule::in(array_keys(SiteLayout::LAYERS))],
            'settings' => ['nullable', 'array'],
            'settings.density' => ['nullable', 'integer', 'between:1,3'],
            'settings.ratio' => ['nullable', 'numeric', 'between:0.1,10'],
            'settings.camera_range' => ['nullable', 'numeric', 'between:3,60'],
            'settings.camera_fov' => ['nullable', 'numeric', 'between:30,180'],
            'settings.page' => ['nullable', 'integer', 'between:1,50'],
            'settings.ceiling' => ['nullable', 'numeric', 'between:2,10'],
            'settings.floors' => ['nullable', 'integer', 'between:1,40'],
            'settings.sens' => ['nullable', 'integer', 'between:0,100'],
            'settings.walls3d' => ['nullable', 'boolean'],
            'settings.outline' => ['nullable', 'array', 'max:400'],
            'settings.outline.*' => ['array', 'size:2'],
            'settings.outline.*.*' => ['numeric'],
            'settings.outlines' => ['nullable', 'array', 'max:40'],
            'settings.outlines.*' => ['nullable', 'array', 'max:400'],
            'settings.outlines.*.*' => ['array', 'size:2'],
            'settings.outlines.*.*.*' => ['numeric'],
            'settings.floor_pages' => ['nullable', 'array', 'max:41'],
            'settings.floor_pages.*' => ['nullable', 'integer', 'between:1,50'],
            'devices' => ['present', 'array', 'max:6000'],
            'devices.*.id' => ['required', 'string', 'max:40'],
            'devices.*.type' => ['required', Rule::in(['cab', 'cam', 'ap', 'outlet', 'spk', 'phone'])],
            'devices.*.x' => ['required', 'numeric'],
            'devices.*.y' => ['required', 'numeric'],
            'devices.*.rot' => ['nullable', 'numeric'],
            'devices.*.fl' => ['nullable', 'integer', 'between:0,39'],
            'devices.*.label' => ['nullable', 'string', 'max:60'],
        ]);

        $layout->update([
            'name' => $data['name'],
            'width_m' => $data['width_m'],
            'layers' => array_values($data['layers']),
            'settings' => $data['settings'] ?? $layout->settings,
            'devices' => $data['devices'],
        ]);

        return response()->json(['saved' => true, 'at' => now()->toIso8601String()]);
    }

    /** The uploaded PDF, only for its owner and staff. */
    public function plan(Request $request, SiteLayout $layout): StreamedResponse
    {
        $this->authorizeLayout($request, $layout);
        abort_unless($layout->plan_path && Storage::exists($layout->plan_path), 404);

        return Storage::response($layout->plan_path, $layout->plan_name ?: 'plan.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, SiteLayout $layout): RedirectResponse
    {
        $this->authorizeLayout($request, $layout);

        if ($layout->plan_path) {
            Storage::delete($layout->plan_path);
        }

        $layout->delete();

        return redirect()->route('layouts.index')->with('status', 'Layout deleted.');
    }

    private function isStaff(Request $request): bool
    {
        return $request->user()->role !== Role::User;
    }

    private function authorizeLayout(Request $request, SiteLayout $layout): void
    {
        abort_unless($this->isStaff($request) || $layout->user_id === $request->user()->id, 403);
    }
}
