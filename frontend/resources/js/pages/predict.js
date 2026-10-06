/**
 * Alpine component for § 03.0 Live Classifier Sandbox:
 * Single text inference, Kamusalay normalization preview, hierarchical confidence.
 */
import { formatLabel } from '../utils/labels';

export default function liveClassifier() {
    let initial = {
        endpoint: '/predict/classify'
    };

    try {
        const el = document.getElementById('predict-server-data');
        if (el && el.textContent) {
            initial = Object.assign(initial, JSON.parse(el.textContent));
        }
    } catch (e) {
        console.error('Failed to parse predict server data:', e);
    }

    const classifyEndpoint = initial.endpoint || '/predict/classify';

    return {
        inputText: '',
        loading:   false,
        result:    null,
        errorMsg:  '',
        latency:   0,

        async classify() {
            if (this.inputText.trim().length < 4) return;

            this.loading  = true;
            this.result   = null;
            this.errorMsg = '';
            this.latency  = 0;
            const t0 = performance.now();

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                const response = await fetch(classifyEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ text: this.inputText }),
                });

                const data = await response.json();
                this.latency = Math.round(performance.now() - t0);

                if (!response.ok || !data.success) {
                    this.errorMsg = data.message || 'The AI processing service is offline or unreachable.';
                } else {
                    this.result = data.data;
                }
            } catch (e) {
                this.latency  = Math.round(performance.now() - t0);
                this.errorMsg = 'The AI subsystem connection failed. Ensure the inference service is running.';
            } finally {
                this.loading = false;
            }
        },

        formatLabel(label) {
            return formatLabel(label);
        }
    };
}
