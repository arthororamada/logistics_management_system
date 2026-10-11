export function syncTripCost(row, changed = '') {
    const field = (name) => row.querySelector(`[name$="[${name}]"]`);
    const type = field('cost_code').selectedOptions[0]?.dataset.code ?? '';
    const fuel = ['FUEL_PURCHASE', 'CASH_FUEL'].includes(type);
    const roro = type === 'RORO_COST';
    if (changed === 'cost_code') {
        for (const name of ['fuel_supplier', 'liters_required', 'price_per_liter', 'shipping_line_name', 'shipping_rate_id', 'cost_amount']) field(name).value = '';
    }
    row.querySelectorAll('[data-cost-fields]').forEach((group) => {
        const visible = { fuel, shipping: roro, supplier: type === 'FUEL_PURCHASE' }[group.dataset.costFields];
        group.hidden = !visible;
        group.querySelectorAll('input, select').forEach((input) => {
            input.disabled = !visible;
            input.required = visible;
            if (!visible) {
                input.value = '';
                input.setCustomValidity('');
            }
        });
    });
    const line = field('shipping_line_name');
    const route = field('shipping_rate_id');
    const amount = field('cost_amount');
    if (changed === 'shipping_line_name') {
        route.value = '';
        amount.value = '';
    }
    route.disabled = !roro || !line.value;
    for (const option of route.options) {
        if (!option.value) continue;
        option.hidden = option.disabled = option.dataset.line !== line.value;
    }
    if (route.selectedOptions[0]?.disabled) route.value = '';
    amount.readOnly = fuel || roro;
    if (fuel) {
        const liters = field('liters_required');
        const price = field('price_per_liter');
        const cents = Math.round(Math.round(Number(liters.value) * 100) * Math.round(Number(price.value) * 100) / 100);
        amount.value = liters.value !== '' && price.value !== '' ? (cents / 100).toFixed(2) : '';
    } else if (roro) {
        amount.value = route.selectedOptions[0]?.dataset.amount ?? '';
    }
    // Readonly inputs do not participate in native constraint validation.
    const invalid = amount.readOnly && (amount.value === '' || !Number.isFinite(Number(amount.value)) || Number(amount.value) < 0 || Number(amount.value) > 999999.99);
    const anchor = fuel ? field('liters_required') : route;
    anchor.setCustomValidity(invalid && !anchor.disabled ? 'Select complete cost details with an amount up to 999999.99.' : '');
}
