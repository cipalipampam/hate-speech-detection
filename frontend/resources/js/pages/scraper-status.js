/**
 * Alpine component for § 04.0 Scraper Telemetry:
 * Real-time FastAPI session monitoring, login trigger handling (noVNC / native GUI).
 */
export default function scraperTelemetry(paramConfig = null) {
    let config = {
        initialOnline: false,
        initialSessionData: null,
        loginEnv: null,
        checkUrl: window.location.pathname
    };

    if (paramConfig && typeof paramConfig === 'object') {
        config = Object.assign(config, paramConfig);
    } else {
        try {
            const el = document.getElementById('scraper-telemetry-data');
            if (el && el.textContent) {
                config = Object.assign(config, JSON.parse(el.textContent));
            }
        } catch (e) {
            console.error('Failed to parse scraper telemetry data:', e);
        }
    }

    return {
        isOnline: Boolean(config.initialOnline),
        sessionData: config.initialSessionData || null,
        loginEnv: config.loginEnv || null,
        checkUrl: config.checkUrl || window.location.pathname,
        isChecking: false,
        pollTimer: null,

        get xValid() {
            return !!(this.sessionData && this.sessionData.x && this.sessionData.x.is_valid === true);
        },

        get xMessage() {
            if (!this.isOnline) {
                return 'The AI subsystem is offline; session telemetry is unavailable.';
            }
            return (this.sessionData && this.sessionData.x && this.sessionData.x.message)
                ? this.sessionData.x.message
                : 'Session is not configured or its cookies have expired.';
        },

        get threadsValid() {
            return !!(this.sessionData && this.sessionData.threads && this.sessionData.threads.is_valid === true);
        },

        get threadsMessage() {
            if (!this.isOnline) {
                return 'The AI subsystem is offline; session telemetry is unavailable.';
            }
            return (this.sessionData && this.sessionData.threads && this.sessionData.threads.message)
                ? this.sessionData.threads.message
                : 'Session is not configured or its cookies have expired.';
        },

        get guiMode() {
            return (this.loginEnv && this.loginEnv.gui_mode) ? this.loginEnv.gui_mode : null;
        },

        get novncUrl() {
            const raw = (this.loginEnv && this.loginEnv.novnc_url) ? this.loginEnv.novnc_url : null;
            if (!raw) return null;

            const host = window.location.hostname;
            if (host && host !== 'localhost' && host !== '127.0.0.1') {
                return raw.replace(/\/\/(localhost|127\.0\.0\.1)(?=[:/])/, '//' + host);
            }
            return raw;
        },

        requestLogin() {
            if (this.guiMode === 'novnc' && this.novncUrl) {
                window.open(this.novncUrl, '_blank', 'noopener');
            }
        },

        async checkStatus() {
            if (this.isChecking) return;
            this.isChecking = true;
            try {
                const res = await fetch(this.checkUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.isOnline = !!data.isServerOnline;
                    this.sessionData = data.sessionData || null;
                    this.loginEnv = data.loginEnvironment || null;
                } else {
                    this.isOnline = false;
                    this.sessionData = null;
                    this.loginEnv = null;
                }
            } catch (err) {
                this.isOnline = false;
                this.sessionData = null;
                this.loginEnv = null;
            } finally {
                this.isChecking = false;
                window.__SCRAPER_POLLING_ACTIVE__ = true;
                window.dispatchEvent(new CustomEvent('fastapi-status-changed', {
                    detail: { isOnline: this.isOnline }
                }));
            }
        },

        init() {
            window.__SCRAPER_POLLING_ACTIVE__ = true;

            this.pollTimer = setInterval(() => {
                this.checkStatus();
            }, 3000);

            window.addEventListener('beforeunload', () => {
                window.__SCRAPER_POLLING_ACTIVE__ = false;
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
            });
        }
    };
}
