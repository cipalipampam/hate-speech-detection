/**
 * Alpine component untuk status pill koneksi FastAPI di masthead header.
 */
export default function fastApiHeaderTelemetry(endpoint) {
    return {
        isOnline: sessionStorage.getItem('fastapi_online') !== 'false',
        isChecking: false,
        pollTimer: null,

        async check() {
            if (this.isChecking || !endpoint) return;
            this.isChecking = true;
            try {
                const res = await fetch(endpoint, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.isOnline = !!data.online;
                } else {
                    this.isOnline = false;
                }
            } catch (e) {
                this.isOnline = false;
            } finally {
                sessionStorage.setItem('fastapi_online', this.isOnline ? 'true' : 'false');
                this.isChecking = false;
            }
        },

        init() {
            this.check();

            window.addEventListener('fastapi-status-changed', (e) => {
                if (e.detail && typeof e.detail.isOnline === 'boolean') {
                    this.isOnline = e.detail.isOnline;
                    sessionStorage.setItem('fastapi_online', this.isOnline ? 'true' : 'false');
                }
            });

            this.pollTimer = setInterval(() => {
                if (window.__SCRAPER_POLLING_ACTIVE__) return;
                this.check();
            }, 5000);

            window.addEventListener('beforeunload', () => {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
            });
        }
    };
}
