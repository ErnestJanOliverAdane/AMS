@extends('layouts.app')

@section('title', $layout->name)

@section('content')
@php
    $config = [
        'id' => $layout->id,
        'name' => $layout->name,
        'width' => $layout->width_m,
        'layers' => $layout->layers,
        'allLayers' => $layerLabels,
        'settings' => (object) ($layout->settings ?? []),
        'devices' => $layout->devices ?? [],
        'planUrl' => $layout->plan_path ? route('layouts.plan', $layout) : null,
        'planName' => $layout->plan_name,
        'saveUrl' => route('layouts.update', $layout),
        'csrf' => csrf_token(),
        'auto' => $auto,
        'camera' => request()->boolean('camera'),
    ];
@endphp
<div class="pl" id="planner" data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}">
    <div class="pl-bar no-print">
        <input class="pl-name" id="pl-name" type="text" maxlength="120" aria-label="Layout name" value="{{ $layout->name }}">
        <div class="pl-actions">
            <button class="btn" type="button" id="pl-gen">Auto-generate</button>
            <button class="btn" type="button" id="pl-png">Download PNG</button>
            <button class="btn" type="button" id="pl-print">Print / PDF</button>
            <button class="btn primary" type="button" id="pl-save">Save</button>
            <span class="pl-status" id="pl-status" role="status"></span>
        </div>
    </div>

    <div class="pl-grid">
        <aside class="pl-side no-print">
            <section>
                <h3>Services</h3>
                <div id="pl-layers" class="pl-layers"></div>
            </section>
            <section>
                <h3>Plan</h3>
                <label>Width (m)<input type="number" id="pl-width" min="5" max="500" step="0.5"></label>
                <label>Floors in the building<input type="number" id="pl-floors" min="1" max="40" step="1"></label>
                <label id="pl-height-wrap">Height (m)<input type="number" id="pl-height" min="3" max="500" step="0.5"></label>
                <label id="pl-page-wrap" hidden>PDF page for this floor<input type="number" id="pl-page" min="1" max="50" step="1"></label>
                <label>Density
                    <select id="pl-density"><option value="1">Low</option><option value="2">Normal</option><option value="3">High</option></select>
                </label>
                <label>Floor height (m)<input type="number" id="pl-ceiling" min="2" max="10" step="0.1"></label>
                <label>Camera range (m)<input type="number" id="pl-range" min="3" max="60" step="1"></label>
                <label>Camera view angle (°)<input type="number" id="pl-fov" min="30" max="180" step="5"></label>
            </section>
            <section>
                <h3>Building shape</h3>
                <div class="pl-tools">
                    <button class="btn small" type="button" id="pl-detect" hidden>Detect shape from PDF</button>
                    <button class="btn small" type="button" id="pl-draw">Draw outline by hand</button>
                    <button class="btn small" type="button" id="pl-rect">Use full rectangle</button>
                </div>
                <label id="pl-sens-wrap" hidden>Line sensitivity<input type="range" id="pl-sens" min="0" max="100" step="5"></label>
                <label class="chk" id="pl-same-wrap" hidden><input type="checkbox" id="pl-same" checked> Same shape on every floor</label>
                <label class="chk"><input type="checkbox" id="pl-walls3d" checked> Use PDF walls (layout and 3D)</label>
                <p class="pl-hint" id="pl-shape-msg" hidden></p>
            </section>
            <section>
                <h3>Moving objects</h3>
                <div class="pl-tools">
                    <button class="btn small" type="button" id="pl-obj-person"><i style="background:#e64980">P</i>Add person</button>
                    <button class="btn small" type="button" id="pl-obj-vehicle"><i style="background:#212529">V</i>Add vehicle</button>
                    <button class="btn small" type="button" id="pl-obj-play">Pause</button>
                    <button class="btn small" type="button" id="pl-obj-clear">Remove all</button>
                </div>
                <label class="chk"><input type="checkbox" id="pl-track" checked> CCTV cameras turn to face them</label>
                <p class="muted" style="font-size:12.5px">Objects walk around inside the building. Cameras in range turn to the nearest one and show it in the CCTV view. Moving objects: <b id="pl-obj-count">none yet</b>.</p>
            </section>
            <section id="pl-oinspector" hidden>
                <h3>Selected object</h3>
                <p id="pl-oname" style="font-weight:600;margin:0 0 8px"></p>
                <label>Facing direction (°)<input type="number" id="pl-ohead" step="5"></label>
                <div class="pl-tools">
                    <button class="btn small" type="button" id="pl-oface">Face nearest camera</button>
                    <button class="btn small" type="button" id="pl-opause">Stand still</button>
                    <button class="btn small" type="button" id="pl-odel">Remove object</button>
                </div>
            </section>
            <section>
                <h3>Show</h3>
                <label class="chk"><input type="checkbox" id="pl-runs" checked> Cable runs</label>
                <label class="chk"><input type="checkbox" id="pl-cover" checked> Coverage</label>
                <label class="chk"><input type="checkbox" id="pl-labels" checked> Labels</label>
                <label class="chk"><input type="checkbox" id="pl-showoutline" checked> Building outline</label>
                <label class="chk" id="pl-showwalls-wrap" hidden><input type="checkbox" id="pl-showwalls"> Detected walls</label>
            </section>
            <section>
                <h3>Add by hand</h3>
                <div class="pl-tools" id="pl-tools"></div>
                <p class="muted" style="font-size:12.5px">Pick a device, then click the plan. Drag devices to move them.</p>
            </section>
            <section id="pl-inspector" hidden>
                <h3>Selected</h3>
                <label>Label<input type="text" id="pl-ilabel" maxlength="60"></label>
                <label id="pl-irot-wrap">Direction (°)<input type="number" id="pl-irot" step="5"></label>
                <button class="btn small" type="button" id="pl-idel">Delete device</button>
            </section>
        </aside>

        <div class="pl-view">
            <div class="pl-floorbar no-print" id="pl-floorbar" hidden>
                <span>Floor</span>
                <button class="btn small" type="button" id="pl-floor-prev" aria-label="Floor below">▼</button>
                <select id="pl-floor" aria-label="Floor"></select>
                <button class="btn small" type="button" id="pl-floor-next" aria-label="Floor above">▲</button>
            </div>
            <div class="pl-tabs no-print" role="tablist">
                <button type="button" role="tab" class="on" data-view="2d" aria-selected="true">2D plan</button>
                <button type="button" role="tab" data-view="3d" aria-selected="false">3D virtual camera</button>
            </div>
            <div class="pl-3d no-print" id="pl-3d" hidden>
                <div class="pl3-bar">
                    <div class="pl3-modes" id="pl3-modes">
                        <button type="button" class="btn small on" data-mode="orbit">Orbit</button>
                        <button type="button" class="btn small" data-mode="walk">Walk</button>
                        <button type="button" class="btn small" data-mode="cam">CCTV view</button>
                        <button type="button" class="btn small" data-mode="wall">Camera wall</button>
                        <button type="button" class="btn small" data-mode="follow" disabled>Follow object</button>
                        <button type="button" class="btn small accent" data-mode="ar">Open camera (AR)</button>
                    </div>
                    <select id="pl3-cam" aria-label="CCTV camera" hidden></select>
                    <select id="pl3-obj" aria-label="Moving object" hidden></select>
                    <span class="pl3-floors" id="pl3-floor-wrap" hidden>
                        <select id="pl3-floor" aria-label="Floor"></select>
                        <select id="pl3-floors" aria-label="Floors shown"><option value="all">All floors</option><option value="one">This floor only</option></select>
                    </span>
                    <label class="chk"><input type="checkbox" id="pl3-walls" checked> Walls</label>
                    <label class="chk"><input type="checkbox" id="pl3-cover" checked> Coverage</label>
                    <label class="chk"><input type="checkbox" id="pl3-tour"> Auto tour</label>
                    <label class="chk"><input type="checkbox" id="pl3-motion" checked> Phone motion</label>
                    <label class="chk"><input type="checkbox" id="pl3-steps"> Step to move</label>
                    <button type="button" class="btn small" id="pl3-motion-retry" hidden>Enable motion</button>
                    <span class="pl3-sensor" id="pl3-sensor" role="status"></span>
                    <button type="button" class="btn small" id="pl3-snap">Snapshot</button>
                </div>
                <div class="pl3-ar" id="pl3-ar" hidden>
                    <button type="button" class="btn small" id="ar-switch">Switch camera</button>
                    <button type="button" class="btn small" id="ar-cal">Calibrate: face the top wall, then tap</button>
                    <button type="button" class="btn small" id="ar-regen">Regenerate setup</button>
                    <button type="button" class="btn small" id="ar-save">Save to layout</button>
                    <label class="chk">Zoom <input type="range" id="ar-fov" min="45" max="95" value="65"></label>
                    <span class="ar-pad" aria-label="Move">
                        <button type="button" data-key="a" aria-label="Left">◀</button>
                        <button type="button" data-key="w" aria-label="Forward">▲</button>
                        <button type="button" data-key="s" aria-label="Back">▼</button>
                        <button type="button" data-key="d" aria-label="Right">▶</button>
                    </span>
                </div>
                <div class="pl3-wrap" id="pl3-wrap">
                    <video id="pl3-video" playsinline muted autoplay></video>
                    <canvas id="pl3-canvas"></canvas>
                    <div class="pl3-start" id="pl3-start" hidden>
                        <div>
                            <strong>Use your camera</strong>
                            <p>Point it at the room. A complete cabling layout, built to TIA / ISO network cabling standards, is generated and drawn over the live view. Nothing is recorded or uploaded.</p>
                            <button type="button" class="btn primary" id="ar-start">Start camera</button>
                        </div>
                    </div>
                    <div class="pl3-hud" id="pl3-hud"></div>
                    <div class="pl3-tiles" id="pl3-tiles"></div>
                    <div class="pl-msg" id="pl3-msg" hidden></div>
                </div>
                <p class="muted pl-note" id="pl3-help"></p>
            </div>
            <div class="pl-stage" id="pl-stage">
                <canvas id="pl-pdf" aria-hidden="true"></canvas>
                <svg id="pl-svg" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Site layout"></svg>
                <div class="pl-msg" id="pl-msg" hidden></div>
            </div>
            <ul class="pl-legend" id="pl-legend"></ul>
            <p class="muted pl-note no-print">Devices are placed to scale from the plan width you enter. The building outline and long wall lines are read from the PDF (check them with “Detected walls”); scanned or very busy drawings may need “Draw outline by hand”. Drag devices into the exact spots on site.</p>
            <div id="pl-summary" class="pl-summary"></div>
        </div>
    </div>
</div>

@if ($layout->plan_path)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous"></script>
@endif
<link rel="stylesheet" href="{{ asset('css/site-layout.css') }}">
<script src="{{ asset('js/site-layout-shape.js') }}" defer></script>
<script src="{{ asset('js/site-layout.js') }}" defer></script>
<script src="{{ asset('js/site-layout-3d.js') }}" defer></script>
@endsection
