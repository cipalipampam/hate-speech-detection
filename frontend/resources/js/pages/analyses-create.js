/**
 * Alpine component for § 02.1 New Analysis Form:
 * Manage keywords tag input, platform radio selection, validation limits.
 */
export default function analysisForm() {
    let initial = {
        keywords: [],
        platform: 'both'
    };

    try {
        const el = document.getElementById('analysis-create-data');
        if (el && el.textContent) {
            initial = Object.assign(initial, JSON.parse(el.textContent));
        }
    } catch (e) {
        console.error('Failed to parse analysis-create data:', e);
    }

    return {
        keywords: Array.isArray(initial.keywords) ? initial.keywords : [],
        platform: initial.platform || 'both',

        addKeyword(input) {
            if (!input) return;
            const val = input.value.trim().replace(/,$/, '').trim();
            if (val.length >= 2 && !this.keywords.includes(val) && this.keywords.length < 10) {
                this.keywords.push(val);
            }
            input.value = '';
            input.focus();
        },

        removeKeyword(index) {
            this.keywords.splice(index, 1);
        }
    };
}
