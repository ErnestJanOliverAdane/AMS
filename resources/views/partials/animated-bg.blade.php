{{-- Animated background for every signed-in page and the login pages. Needs public/css/portal-bg.css and public/js/portal-bg.js --}}
<div class="pbg" aria-hidden="true">
    <div class="pbg-blob pbg-blue"></div>
    <div class="pbg-blob pbg-green"></div>
    <div class="pbg-grid"></div>
    <div class="pbg-logo" style="background-image:url('{{ asset('images/logo-bg.png') }}')"></div>
    <canvas id="pbg-net"></canvas>
</div>
