<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('portal.name') }}</title>
    <meta name="format-detection" content="telephone=no, email=no, address=no">
    <meta name="description" content="System integrator for CCTV, IP PBX, structured cabling and Wi-Fi. Book an appointment online.">
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@500;600;700&family=Figtree:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <script>
        // Apply the saved theme before the page paints, to avoid a flash.
        try { var t = localStorage.getItem('konektado-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @include('partials.pwa')
</head>
<body>
<div class="bg" aria-hidden="true">
    <div class="blob blob-blue"></div>
    <div class="blob blob-green"></div>
    <div class="dots"></div>
    <canvas id="net"></canvas>
</div>

<header class="nav">
    <div class="wrap nav-in">
        <a class="logo" href="#home">
            <img src="{{ asset('images/logo.png') }}" alt="" width="46" height="46">
            <span>{{ config('portal.name') }}</span>
        </a>
        <nav class="links" id="links" aria-label="Main">
            <a href="#home">Home</a>
            <a href="#solutions">Solutions</a>
            <a href="#highlights">Highlights</a>
            <a href="#about">About Us</a>
            <a href="#features">Features</a>
            <a href="#faq">FAQ's</a>
            <a href="#get-started">Contact</a>
        </nav>
        <div class="nav-actions">
            <button class="icon-btn" id="theme" type="button" aria-label="Switch light or dark theme">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
            @auth
                <a class="btn solid" href="{{ route('dashboard') }}">My portal</a>
            @else
                <a class="btn solid" href="#get-started">Get Started</a>
            @endauth
            <button class="icon-btn menu" id="menu" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="links">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>
</header>

<main>
    <section class="hero" id="home">
        <div class="wrap hero-in">
            <p class="pill"><i></i>System Integrator · CCTV · IP PBX · Cabling</p>
            <h1>Seamless IT Solutions for <span class="grad">Your Business</span></h1>
            <p class="lead">Empowering businesses with innovative IT services for success.</p>
            <div class="cta">
                @auth
                    <a class="btn solid big" href="{{ route('dashboard') }}">Open my portal →</a>
                @else
                    <a class="btn solid big" href="#get-started">Get Started →</a>
                @endauth
                <a class="btn ghost big" href="#solutions">See our solutions</a>
            </div>
            <ul class="chips" aria-label="Services">
                <li>RJ45 terminations</li>
                <li>Cat6 structured cabling</li>
                <li>SIP / IP PBX</li>
                <li>Wi-Fi site setup</li>
                <li>CCTV</li>
            </ul>

            <div class="book">
                <div>
                    <strong>Need a visit or a consultation?</strong>
                    <span>Check open times, then request an appointment online.</span>
                </div>
                <div class="book-actions">
                    <a class="btn ghost" href="{{ route('slots.search') }}">See available slots</a>
                    @guest
                        @if (config('portal.allow_registration'))
                            <a class="btn solid" href="{{ route('register') }}">Create account</a>
                        @endif
                    @else
                        @if (auth()->user()->role === \App\Enums\Role::User)
                            <a class="btn solid" href="{{ route('client.appointments.create') }}">Book appointment</a>
                        @endif
                    @endguest
                </div>
            </div>
        </div>
    </section>

    <section class="sec" id="solutions">
        <div class="wrap">
            <p class="eyebrow">Solutions</p>
            <h2>Everything your network needs, under one roof</h2>
            <div class="grid">
                @foreach ([
                    ['Computer Troubleshooting', 'Diagnosis and repair of computer and software problems.'],
                    ['End-to-End Terminations', 'Clean RJ45 terminations, tested and labelled.'],
                    ['Data Cabling', 'Structured cabling for offices, shops and buildings.'],
                    ['Wi-Fi Setup', 'Site survey, access point placement and configuration.'],
                    ['IP PBX Installation', 'SIP phone systems with extensions and call routing.'],
                    ['Paging System', 'Customized paging and intercom for your premises.'],
                    ['CCTV Installation', 'Cameras, recorders and remote viewing.'],
                    ['Website Design', 'A tailored business website that fits your brand.'],
                ] as [$title, $text])
                    <article class="card">
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="sec alt" id="highlights">
        <div class="wrap">
            <p class="eyebrow">Highlights</p>
            <h2>How a project runs</h2>
            <div class="grid four">
                <article class="card"><h3>Site survey</h3><p>We look at your space and your needs before we quote.</p></article>
                <article class="card"><h3>Clean installation</h3><p>Neat cable runs, proper terminations and tidy racks.</p></article>
                <article class="card"><h3>Testing and labelling</h3><p>Every line is tested and labelled so it is easy to trace later.</p></article>
                <article class="card"><h3>Support after the job</h3><p>Reach us for fixes, changes and follow-up visits.</p></article>
            </div>

            <div class="shots" aria-label="Photos of our work">
                <figure class="shot s-desk">
                    <img src="{{ asset('images/highlights/desk.jpg') }}" alt="Keyboard, mouse and monitor on a wooden desk" width="1050" height="1400" loading="lazy">
                    <figcaption><b>Computer support</b><span>Troubleshooting and workstation setup</span></figcaption>
                </figure>
                <figure class="shot s-panel">
                    <img src="{{ asset('images/highlights/panel.jpg') }}" alt="Technician pointing a tester at a wired electrical panel" width="1400" height="932" loading="lazy">
                    <figcaption><b>Clean terminations</b><span>Neat, labelled and tested connections</span></figcaption>
                </figure>
                <figure class="shot s-rack">
                    <img src="{{ asset('images/highlights/rack.jpg') }}" alt="Technician installing equipment in a network rack" width="1400" height="933" loading="lazy">
                    <figcaption><b>IP PBX and racks</b><span>Installed and configured on site</span></figcaption>
                </figure>
                <figure class="shot s-fiber">
                    <img src="{{ asset('images/highlights/fiber.jpg') }}" alt="Fiber patch cables plugged into rack servers" width="1400" height="931" loading="lazy">
                    <figcaption><b>Data cabling</b><span>Copper and fiber links</span></figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section class="sec" id="about">
        <div class="wrap split">
            <div>
                <p class="eyebrow">About Us</p>
                <h2>Connected networks, built to last</h2>
            </div>
            <div class="prose">
                <p>{{ config('portal.name') }} is a system integrator that designs, installs and supports the networks businesses depend on: cabling, Wi-Fi, IP telephony and CCTV.</p>
                <p>We keep the work simple and documented, so your team always knows what was installed and where.</p>
            </div>
        </div>
    </section>

    <section class="sec alt" id="features">
        <div class="wrap">
            <p class="eyebrow">Features</p>
            <h2>Your client portal</h2>
            <div class="grid">
                <article class="card"><h3>Online appointments</h3><p>Pick a service, a day and a time that is really open.</p></article>
                <article class="card"><h3>Status and reminders</h3><p>See if a request is pending, approved or rejected, and get a reminder before the visit.</p></article>
                <article class="card"><h3>Cancel or reschedule</h3><p>Change your plans from your account without calling.</p></article>
                <article class="card"><h3>Confirmation slip</h3><p>Download your slip once the appointment is approved.</p></article>
                <article class="card"><h3>History and feedback</h3><p>Review past visits and rate the service after it is done.</p></article>
                <article class="card"><h3>Special requests</h3><p>Ask the team for a special arrangement through support.</p></article>
            </div>
        </div>
    </section>

    <section class="sec" id="faq">
        <div class="wrap narrow">
            <p class="eyebrow">FAQ's</p>
            <h2>Common questions</h2>
            <div class="faq">
                <details><summary>How do I book an appointment?</summary><p>Create an account or sign in, choose a service, pick an open day and time, and send the request. The team confirms it and you get a notification.</p></details>
                <details><summary>Can I change or cancel a booking?</summary><p>Yes. Open "My appointments" to cancel or ask for another time. A new time needs approval again. Repeated cancellations or no-shows can pause new bookings.</p></details>
                <details><summary>How far ahead can I book?</summary><p>You can book from a couple of hours ahead up to 60 days in advance, during working hours.</p></details>
                <details><summary>Will I get a reminder?</summary><p>Yes. You get a notification the day before an approved appointment.</p></details>
                <details><summary>Do you provide website design services?</summary><p>Yes, we offer tailored website design services to establish a strong online presence for your business.</p></details>
                <details><summary>Can you help with CCTV installations?</summary><p>Absolutely! We provide cutting-edge CCTV systems to enhance security and surveillance capabilities.</p></details>
                <details><summary>Are your solutions suitable for small businesses?</summary><p>Yes, our comprehensive IT services cater to businesses of all sizes, including small businesses.</p></details>
                <details><summary>How can you improve internal communication and coordination?</summary><p>We offer customized paging solutions to improve internal communication and coordination within organizations.</p></details>
            </div>
        </div>
    </section>

    <section class="sec" id="get-started">
        <div class="wrap">
            <div class="final">
                <p class="eyebrow">Get Started</p>
                <h2>Transform your business with Konektado I.T. Solutions</h2>
                <p class="final-lead">Let us help you navigate the digital landscape with confidence. Contact us today.</p>

                <div class="contacts">
                    <div class="ct"><div><small>Email</small><span translate="no">sales&#64;konektadoitsolutions.com</span></div><button class="copy" type="button" data-copy="sales@konektadoitsolutions.com">Copy</button></div>
                    <div class="ct"><div><small>Mobile</small><span translate="no">0945-701-0094</span></div><button class="copy" type="button" data-copy="0945-701-0094">Copy</button></div>
                    <div class="ct"><div><small>Mobile</small><span translate="no">0917-776-0805</span></div><button class="copy" type="button" data-copy="0917-776-0805">Copy</button></div>
                </div>

                <div class="access">
                    <div>
                        <strong>Use the client portal</strong>
                        <span>Book appointments, track their status and download your slip.</span>
                    </div>
                    <div class="access-actions">
                        @auth
                            <a class="btn solid" href="{{ route('dashboard') }}">Open my portal</a>
                        @else
                            <a class="btn solid" href="{{ route('login') }}">Sign in</a>
                            @if (config('portal.allow_registration'))
                                <a class="btn ghost" href="{{ route('register') }}">Create account</a>
                            @endif
                            <a class="btn ghost" href="#request">Continue as guest</a>
                        @endauth
                    </div>
                </div>

                <form class="qb" id="request" method="POST" action="{{ route('inquiries.store') }}">
                    @csrf
                    <h3>Draft your request</h3>
                    <p class="qb-note">No account needed. "Send via Gmail" opens Gmail with your message ready to send. You can also send it to our team here, or copy it for Facebook or text.</p>

                    @if (session('inquiry_sent'))
                        <div class="notice ok" role="status">Thank you. Your message was sent. We will contact you on the number you gave.</div>
                    @endif
                    @if ($errors->any())
                        <div class="notice bad" role="alert">
                            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                        </div>
                    @endif

                    <div class="qchips" id="qchips" role="group" aria-label="Pick the services you need">
                        @foreach (['Computer Troubleshooting', 'End-to-End Terminations', 'Data Cabling', 'WiFi Setup', 'IP PBX Installations', 'Paging System', 'CCTV Installation', 'Website Design', 'Any I.T. Related Services'] as $service)
                            <button type="button" class="qchip" aria-pressed="false">{{ $service }}</button>
                        @endforeach
                    </div>
                    <div id="qservices"></div>

                    <div class="qrow">
                        <label>Your name<input type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" maxlength="100" autocomplete="name" required></label>
                        <label>Contact number<input type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" inputmode="tel" required></label>
                        <label>Email (optional)<input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" maxlength="255" autocomplete="email"></label>
                    </div>
                    <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                    <label for="qmsg" class="eyebrow">Message</label>
                    <textarea id="qmsg" name="message" maxlength="2000" required>{{ old('message', "Hello Konektado IT Solutions, I would like to ask about: (choose services above).\nBusiness name and location: \nPreferred contact number: ") }}</textarea>

                    <div class="acts">
                        <button class="btn solid" id="qgmail" type="button" data-to="sales@konektadoitsolutions.com">Send via Gmail ↗</button>
                        <button class="btn ghost" type="submit">Send to our team</button>
                        <button class="btn ghost" id="qcopy" type="button">Copy message</button>
                        <a class="btn ghost" href="https://www.facebook.com/konektadoitsolutions" target="_blank" rel="noopener">Open our Facebook page ↗</a>
                    </div>
                    <small class="qb-foot">Your details are used only to answer your request.</small>
                </form>
            </div>
        </div>
    </section>
</main>

<footer class="foot">
    <div class="wrap foot-in">
        <span>© {{ date('Y') }} {{ config('portal.name') }}</span>
        <span class="foot-links">
            <a href="https://www.facebook.com/konektadoitsolutions" target="_blank" rel="noopener">Facebook ↗</a>
            <a href="{{ route('slots.search') }}">Available slots</a>
            @guest <a href="{{ route('login') }}">Sign in</a> @endguest
        </span>
    </div>
</footer>

<script src="{{ asset('js/landing.js') }}" defer></script>
</body>
</html>
