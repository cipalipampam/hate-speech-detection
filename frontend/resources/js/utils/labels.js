/**
 * Utility helper untuk menerjemahkan label taksonomi ujaran kebencian & format angka.
 */

export const TAXONOMY_LABELS = {
    delegitimasi_institusi: 'Institutional Delegitimization',
    dehumanisasi: 'Dehumanization',
    ajakan_kekerasan: 'Incitement to Violence',
    hoaks_pemicu_kebencian: 'Hate-Baiting Hoax',
    hoax_pemicu_kebencian: 'Hate-Baiting Hoax',
    kutukan_agama_personal: 'Religious & Personal Abuse',
    tidak_relevan: 'Non-Hate / Neutral',
};

export function formatLabel(label) {
    return TAXONOMY_LABELS[label] || label || '—';
}

export function numberFormat(num) {
    return new Intl.NumberFormat('id-ID').format(num || 0);
}
