// Rate definitions come from TripRate::personnelRateDefinitions; no designation is fixed here.
export function personnelSalaryCents(definition, drops) {
    if (!definition) return null;
    const extraDrops = Math.max(0, Number(drops) - definition.minimum_drops);
    return definition.base_cents + extraDrops * definition.additional_cents;
}

export function personnelRateForClassification(rates, classification) {
    const normalize = value => String(value ?? '').trim().toLocaleLowerCase().replace(/[\s_-]+/g, '_');
    return Object.entries(rates).find(([label]) => normalize(label) === normalize(classification))?.[1];
}

export function personnelCashReleaseCents(definition, savedReleaseCents = 0) {
    const base = Number(definition?.base_cents ?? 0);
    const percentage = Number(definition?.cash_released_percentage ?? 0);
    const computed = Number.isFinite(base) && Number.isFinite(percentage)
        ? Math.round(Math.max(0, base) * Math.min(100, Math.max(0, percentage)) / 100) : 0;
    return Math.max(computed, savedReleaseCents);
}

// Match the server's vehicle compatibility checks, including existing warehouse/capacity rules.
export function tripRateMatchesVehicle(rate, vehicle) {
    return Boolean(vehicle?.vehicle_group && vehicle?.vehicle_type
        && rate?.vehicle_group === vehicle.vehicle_group && rate?.vehicle_type === vehicle.vehicle_type
        && rate?.warehouse_code === vehicle.warehouse_code
        && Number(vehicle.cargo_capacity ?? 0) >= Number(rate.cargo_capacity ?? 0));
}

export function populateTripRateFields(editor, rate = {}) {
    for (const [field, source] of Object.entries({ trip_no: 'trip_number', drops_number: 'minimum_drop', trip_type: 'trip_type', payment_type: 'payment_type' })) {
        const input = editor.querySelector(`[name="${field}"]`);
        const previous = input.value;
        input.value = field === 'drops_number' && rate[source] != null ? Math.trunc(Number(rate[source])) : (rate[source] ?? '');
        if (previous !== input.value) input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

export function filterTripRateOptions(select, vehicle) {
    for (const option of select.options) {
        if (!option.value) continue;
        const matches = tripRateMatchesVehicle(JSON.parse(option.dataset.rate || '{}'), vehicle);
        option.hidden = option.disabled = !matches;
    }
    if (select.value && select.selectedOptions[0]?.disabled) {
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
