{{-- Needs: $vault (list of [label, count]). The ciphertext shown is random filler, never real data. --}}
<section class="vault card" data-vault aria-labelledby="vault-title">
    <div class="vault-head">
        <div class="vault-lock" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/><circle cx="12" cy="15.5" r="1.4"/></svg>
        </div>
        <div>
            <h3 id="vault-title">Encrypted vault</h3>
            <p class="muted">Sensitive text is encrypted before it is saved.</p>
        </div>
        <span class="vault-badge mono">AES-256</span>
    </div>
    <div class="vault-rows">
        @foreach ($vault as $row)
            <div class="vault-row">
                <div class="vault-meta">
                    <span>{{ $row['label'] }}</span>
                    <b><span data-count="{{ $row['count'] }}">{{ $row['count'] }}</span> <small class="muted">sealed</small></b>
                </div>
                <code class="cipher mono" data-cipher aria-hidden="true">{{ \Illuminate\Support\Str::random(34) }}</code>
            </div>
        @endforeach
    </div>
    <p class="vault-foot muted">The scrambled text above is only an illustration of how stored data looks without the key.</p>
    <span class="vault-scan" aria-hidden="true"></span>
</section>
