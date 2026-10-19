{{-- ═══════════════════════════════════════════════════════════
     GLOBAL COMMAND PALETTE (CTRL + K / CMD + K)
════════════════════════════════════════════════════════════ --}}
<div x-show="cmdOpen" x-cloak class="cmd-palette-backdrop" @click.self="cmdOpen = false">
    <div class="cmd-palette-box" x-data="{
        query: '',
        commands: [
            { title: '§ 01.0 Open System Overview', url: '{{ route('dashboard') }}', tag: 'OVERVIEW' },
            { title: '§ 02.0 View Analysis Archive', url: '{{ route('analyses.index') }}', tag: 'ARCHIVE' },
            @can('run-analysis')
            { title: '§ 02.1 Launch New Batch Analysis', url: '{{ route('analyses.create') }}', tag: 'PIPELINE' },
            @endcan
            @can('test-single-prediction')
            { title: '§ 03.0 Open Live Text Classifier', url: '{{ route('predict.index') }}', tag: 'CLASSIFIER' },
            { title: '§ 04.0 Check System Telemetry', url: '{{ route('scraper.status') }}', tag: 'TELEMETRY' },
            @endcan
            @can('manage-users')
            { title: '§ 05.0 Open User Directory', url: '{{ route('admin.users.index') }}', tag: 'USERS' },
            @endcan
            { title: '§ 00.1 Edit Profile & Password', url: '{{ route('profile.edit') }}', tag: 'PROFILE' }
        ],
        filtered() {
            if (!this.query.trim()) return this.commands;
            return this.commands.filter(c => c.title.toLowerCase().includes(this.query.toLowerCase()) || c.tag.toLowerCase().includes(this.query.toLowerCase()));
        }
    }">
        {{-- Search Bar --}}
        <div style="display:flex;align-items:center;padding:0.75rem 1rem;border-bottom:2px solid #0A0A0A;gap:0.75rem;background:#FFFFFF;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A0A0A" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" x-model="query" x-ref="cmdInput" x-init="$watch('cmdOpen', val => { if(val) setTimeout(() => $refs.cmdInput.focus(), 50) })"
                   placeholder="Type a navigation command or feature..."
                   style="flex:1;border:none;outline:none;font-family:var(--font-mono);font-size:0.875rem;font-weight:700;color:#0A0A0A;background:transparent;">
            <kbd style="background:var(--color-surface-2);border:1px solid #0A0A0A;padding:2px 6px;font-family:var(--font-mono);font-size:0.6875rem;">ESC</kbd>
        </div>

        {{-- Command List --}}
        <div style="max-height:280px;overflow-y:auto;padding:0.5rem;">
            <template x-for="(cmd, idx) in filtered()" :key="idx">
                <a :href="cmd.url" style="display:flex;align-items:center;justify-content:space-between;padding:0.625rem 0.75rem;border-bottom:1px solid var(--color-border-subtle);text-decoration:none;transition:background 0.1s;"
                   onmouseover="this.style.background='var(--color-surface-2)'" onmouseout="this.style.background=''">
                    <span style="font-family:var(--font-mono);font-size:0.8125rem;font-weight:700;color:#0A0A0A;" x-text="cmd.title"></span>
                    <span class="badge badge-mono" style="font-size:0.625rem;" x-text="cmd.tag"></span>
                </a>
            </template>
        </div>

        {{-- Footer Info --}}
        <div style="padding:0.5rem 0.75rem;background:var(--color-surface-2);border-top:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);display:flex;justify-content:space-between;">
            <span>SELECT WITH CLICK / ENTER</span>
            <span>HATESENSE MONOGRAPH v2.4</span>
        </div>
    </div>
</div>

