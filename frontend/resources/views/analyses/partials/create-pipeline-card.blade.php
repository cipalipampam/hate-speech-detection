{{-- ── Card 3: Execution Engine & Pipeline Options ── --}}
<div class="card" style="padding:1rem 1.5rem;margin-bottom:2rem;">
    <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;">
        {{-- Checkbox headless: checked by default saat kunjungan pertama.
             Saat form gagal validasi, ikuti nilai yang benar-benar disubmit user. --}}
        <input type="checkbox" name="headless" value="1"
               {{ !$errors->any() || old('headless') ? 'checked' : '' }}
               style="width:16px;height:16px;accent-color:#0A0A0A;">
        <span style="font-family:var(--font-mono);font-size:0.8125rem;font-weight:700;color:#0A0A0A;">
            [X] RUN PLAYWRIGHT BROWSER IN HEADLESS MODE (BACKGROUND SERVICE)
        </span>
    </label>
</div>

