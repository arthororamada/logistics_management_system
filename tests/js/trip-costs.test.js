import test from 'node:test';
import assert from 'node:assert/strict';
import { syncTripCost } from '../../resources/js/trip-costs.js';
import { matchingSelectOptions } from '../../resources/js/trip-searchable-select.js';

function input(value = '') {
    return { value, disabled: false, required: false, readOnly: false, validationMessage: '', setCustomValidity(message) { this.validationMessage = message; } };
}
function select(options) {
    const control = input();
    control.options = [{ value: '', dataset: {} }, ...options];
    Object.defineProperty(control, 'selectedOptions', { get() { return control.options.filter((option) => option.value === control.value); } });
    return control;
}
// Form fixture using the same select/input interface as the editor.
function fixture(type = '') {
    const fields = {
        cost_code: select(['CASH_FUEL', 'FUEL_PURCHASE', 'CASH_RORO', 'RORO_COST', 'TOLL'].map((code) => ({ value: code, dataset: { code } }))),
        shipping_line_name: select(['Line A', 'Line B'].map((value) => ({ value, dataset: {} }))),
        shipping_rate_id: select([{ value: '1', dataset: { line: 'Line A', amount: '1234.56' } }, { value: '2', dataset: { line: 'Line B', amount: '800.00' } }]),
        liters_required: input(), price_per_liter: input(), fuel_supplier: input(), cost_amount: input('50.00'), remarks: input(),
    };
    fields.cost_code.value = type;
    const groups = [['fuel', ['liters_required', 'price_per_liter']], ['supplier', ['fuel_supplier']], ['shipping', ['shipping_line_name']], ['shipping', ['shipping_rate_id']]].map(([kind, names]) => ({
        dataset: { costFields: kind }, hidden: false, querySelectorAll: () => names.map((name) => fields[name]),
    }));
    const row = { querySelector: (selector) => fields[selector.match(/\[name\$="\[(\w+)\]\"\]/)[1]], querySelectorAll: () => groups };
    return { row, fields, groups };
}

test('default, cash RORO and ordinary codes expose only manual amount and remark', () => {
    for (const code of ['', 'CASH_RORO', 'TOLL']) {
        const { row, fields, groups } = fixture(code);
        syncTripCost(row);
        assert.ok(groups.every((group) => group.hidden));
        assert.equal(fields.cost_amount.readOnly, false);
        assert.equal(fields.cost_amount.value, '50.00');
        assert.equal(fields.shipping_rate_id.disabled, true);
    }
});

test('cash fuel calculates on either input change and requires both values', () => {
    const { row, fields, groups } = fixture('CASH_FUEL');
    syncTripCost(row);
    assert.equal(fields.cost_amount.value, '');
    assert.equal(fields.cost_amount.readOnly, true);
    assert.equal(fields.liters_required.required, true);
    assert.equal(groups.find((group) => group.dataset.costFields === 'supplier').hidden, true);
    fields.liters_required.value = '12.25'; fields.price_per_liter.value = '61.35';
    syncTripCost(row);
    assert.equal(fields.cost_amount.value, '751.54');
    fields.liters_required.value = '10'; syncTripCost(row);
    assert.equal(fields.cost_amount.value, '613.50');
    fields.price_per_liter.value = '60'; syncTripCost(row);
    assert.equal(fields.cost_amount.value, '600.00');
    fields.price_per_liter.value = '999999.99'; syncTripCost(row);
    assert.ok(fields.liters_required.validationMessage);
    fields.price_per_liter.value = '60'; syncTripCost(row);
    assert.equal(fields.liters_required.validationMessage, '');
    fields.liters_required.value = '0.01'; fields.price_per_liter.value = '0.50'; syncTripCost(row);
    assert.equal(fields.cost_amount.value, '0.01');
});

test('supplier fuel reopens with details and switching to ordinary cost clears them', () => {
    const { row, fields } = fixture('FUEL_PURCHASE');
    fields.fuel_supplier.value = '7'; fields.liters_required.value = '10'; fields.price_per_liter.value = '60';
    syncTripCost(row);
    assert.equal(fields.fuel_supplier.disabled, false);
    assert.equal(fields.fuel_supplier.required, true);
    assert.equal(fields.fuel_supplier.value, '7');
    assert.equal(fields.cost_amount.value, '600.00');
    fields.cost_code.value = 'TOLL'; syncTripCost(row, 'cost_code');
    assert.equal(fields.fuel_supplier.value, '');
    assert.equal(fields.liters_required.value, '');
    assert.equal(fields.liters_required.disabled, true);
    assert.equal(fields.cost_amount.value, '');
    assert.equal(fields.cost_amount.readOnly, false);
});

test('switching between fuel types resets quantities, price and supplier', () => {
    const { row, fields } = fixture('FUEL_PURCHASE');
    fields.fuel_supplier.value = '7'; fields.liters_required.value = '10'; fields.price_per_liter.value = '60';
    syncTripCost(row);
    fields.cost_code.value = 'CASH_FUEL'; syncTripCost(row, 'cost_code');
    assert.equal(fields.fuel_supplier.value, '');
    assert.equal(fields.liters_required.value, '');
    assert.equal(fields.price_per_liter.value, '');
    assert.equal(fields.cost_amount.value, '');
    assert.equal(fields.fuel_supplier.disabled, true);
    assert.equal(fields.liters_required.required, true);
});

test('RORO requires a line, filters routes, supplies rate and resets on line changes', () => {
    const { row, fields } = fixture('RORO_COST');
    syncTripCost(row);
    assert.equal(fields.shipping_rate_id.disabled, true);
    fields.shipping_line_name.value = 'Line A'; syncTripCost(row, 'shipping_line_name');
    assert.equal(fields.shipping_rate_id.disabled, false);
    assert.equal(fields.shipping_rate_id.required, true);
    assert.equal(fields.shipping_rate_id.options[1].hidden, false);
    assert.equal(fields.shipping_rate_id.options[2].hidden, true);
    assert.equal(fields.shipping_rate_id.options[2].disabled, true);
    fields.shipping_rate_id.value = '1'; syncTripCost(row, 'shipping_rate_id');
    assert.equal(fields.cost_amount.value, '1234.56');
    assert.equal(fields.cost_amount.readOnly, true);
    syncTripCost(row);
    assert.equal(fields.shipping_rate_id.value, '1');
    fields.shipping_line_name.value = 'Line B'; syncTripCost(row, 'shipping_line_name');
    assert.equal(fields.shipping_rate_id.value, '');
    assert.equal(fields.cost_amount.value, '');
    assert.equal(fields.shipping_rate_id.options[1].disabled, true);
    assert.equal(fields.shipping_rate_id.options[2].hidden, false);
    fields.shipping_rate_id.value = '1'; syncTripCost(row);
    assert.equal(fields.shipping_rate_id.value, '');
    fields.shipping_rate_id.value = '2'; syncTripCost(row);
    assert.equal(fields.cost_amount.value, '800.00');
    fields.cost_code.value = 'CASH_RORO'; syncTripCost(row, 'cost_code');
    assert.equal(fields.shipping_line_name.value, '');
    assert.equal(fields.shipping_rate_id.value, '');
    assert.equal(fields.cost_amount.value, '');
    assert.equal(fields.cost_amount.readOnly, false);
});

test('RORO retains duplicate routes by record ID and uses the exact selected rate', () => {
    const { row, fields } = fixture('RORO_COST');
    const routes = fields.shipping_rate_id;
    routes.options[1].textContent = 'Batangas → Calapan · Truck · PHP 1,234.56 · #1';
    routes.options[2].textContent = 'Batangas → Calapan';
    routes.options.push({ value: '3', dataset: { line: 'Line A', amount: '1500.00' }, textContent: 'Batangas → Calapan · Truck · PHP 1,500.00 · #3' });
    routes.options[0].textContent = 'Select route';
    fields.shipping_line_name.value = 'Line A';
    syncTripCost(row, 'shipping_line_name');
    assert.deepEqual(matchingSelectOptions(routes.options, 'calapan').map((option) => option.value), ['1', '3']);
    routes.value = '3'; syncTripCost(row, 'shipping_rate_id');
    assert.equal(fields.cost_amount.value, '1500.00');
    routes.value = '1'; syncTripCost(row, 'shipping_rate_id');
    assert.equal(fields.cost_amount.value, '1234.56');
    routes.value = ''; syncTripCost(row, 'shipping_rate_id');
    assert.equal(fields.cost_amount.value, '');
    assert.equal(fields.cost_amount.readOnly, true);
});
