export async function readTripAvailability(response) {
    const result = await response.json().catch(() => null);
    if (!response.ok) throw new Error(result?.message || 'Unable to refresh assignment availability. Try again.');
    if (!result?.vehicles || typeof result.vehicles !== 'object' || !result?.personnels || typeof result.personnels !== 'object') {
        throw new Error('The server did not return assignment availability. Reload and try again.');
    }
    return result;
}

export function applyTripAvailability(select, reasons) {
    for (const option of select.options) {
        if (!option.value) continue;
        option.dataset.eligibilityLabel ??= option.textContent.split(' — ')[0];
        const reason = reasons[option.value] ?? (Object.hasOwn(reasons, option.value) ? '' : 'This record is no longer available.');
        option.disabled = Boolean(reason);
        option.dataset.eligibilityReason = reason;
        option.textContent = option.dataset.eligibilityLabel + (reason ? ` — ${reason}` : '');
    }
    const reason = select.selectedOptions[0]?.dataset.eligibilityReason || '';
    select.setCustomValidity(reason);
    return reason;
}
