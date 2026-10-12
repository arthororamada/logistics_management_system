import test from 'node:test';
import assert from 'node:assert/strict';
import { filterTripRateOptions, populateTripRateFields, personnelCashReleaseCents, personnelRateForClassification } from '../../resources/js/trip-calculations.js';

const vehicle = { vehicle_group: 'Truck', vehicle_type: '6W', warehouse_code: 'MNL', cargo_capacity: 5000 };
const rate = { ...vehicle, trip_number: 'LOCAL-01', minimum_drop: '3.00', trip_type: 'Delivery', payment_type: 'Per Drop' };

function form() {
    const events = [];
    const inputs = Object.fromEntries(['trip_no', 'drops_number', 'trip_type', 'payment_type'].map(name => {
        let value = 'Old value';
        return [name, { get value() { return value; }, set value(next) { value = String(next); }, dispatchEvent(event) { events.push([name, event.type]); } }];
    }));
    return { inputs, events, querySelector: selector => inputs[selector.match(/name="(.*?)"/)[1]] };
}

test('vehicle changes hide incompatible rates, clear invalid selection and reset dependent fields', () => {
    const editor = form();
    const options = [
        { value: '', dataset: {} },
        ...[rate, { ...rate, vehicle_type: '4W' }, { ...rate, vehicle_group: 'Van' }, { ...rate, warehouse_code: 'CEB' }, { ...rate, cargo_capacity: 6000 }, {}]
            .map((data, index) => ({ value: String(index + 1), dataset: { rate: JSON.stringify(data) } })),
    ];
    const select = { options, value: '2', get selectedOptions() { return options.filter(option => option.value === this.value); }, dispatchEvent() { populateTripRateFields(editor); } };
    filterTripRateOptions(select, vehicle);
    assert.equal(select.value, '');
    assert.deepEqual(options.slice(1).map(option => option.hidden), [false, true, true, true, true, true]);
    assert.ok(Object.values(editor.inputs).every(input => input.value === ''));
    select.value = '1';
    filterTripRateOptions(select, vehicle);
    assert.equal(select.value, '1');
    filterTripRateOptions(select, {});
    assert.equal(select.value, '');
    filterTripRateOptions(select, { ...vehicle, vehicle_type: '4W' });
    assert.equal(options[2].disabled, false);
    assert.equal(options[1].hidden, true);
});

test('each rate selection replaces every dependent field and emits trip type changes for waybills', () => {
    const editor = form();
    populateTripRateFields(editor, rate);
    assert.deepEqual(Object.values(editor.inputs).map(input => input.value), ['LOCAL-01', '3', 'Delivery', 'Per Drop']);
    populateTripRateFields(editor, { trip_number: 'TRANSFER-02', minimum_drop: 0, trip_type: 'Transfer', payment_type: 'Per Trip' });
    assert.deepEqual(Object.values(editor.inputs).map(input => input.value), ['TRANSFER-02', '0', 'Transfer', 'Per Trip']);
    assert.equal(editor.events.filter(([name]) => name === 'trip_type').length, 2);
    populateTripRateFields(editor, {});
    assert.ok(Object.values(editor.inputs).every(input => input.value === ''));
});

test('employee classification selects distinct driver and helper release definitions', () => {
    const rates = { Driver: { base_cents: 123456, cash_released_percentage: 20 }, Helper: { base_cents: 50000, cash_released_percentage: 50 } };
    assert.equal(personnelCashReleaseCents(personnelRateForClassification(rates, 'DRIVER')), 24691);
    assert.equal(personnelCashReleaseCents(personnelRateForClassification(rates, 'Helper')), 25000);
    assert.equal(personnelCashReleaseCents({ ...rates.Driver, cash_released_percentage: 0.20 }), 247);
    assert.equal(personnelCashReleaseCents({ ...rates.Driver, base_cents: 200000, cash_released_percentage: 30 }), 60000);
    assert.equal(personnelCashReleaseCents(undefined), 0);
    assert.equal(personnelCashReleaseCents({ base_cents: null, cash_released_percentage: null }), 0);
    assert.equal(personnelCashReleaseCents({ base_cents: 50000, cash_released_percentage: 0 }), 0);
    assert.equal(personnelCashReleaseCents(rates.Helper, 40000), 40000);
});
