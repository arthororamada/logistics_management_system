import test from 'node:test';
import assert from 'node:assert/strict';
import { tripSaveErrors, tripErrorSections, tripErrorSection, updateTripSectionErrors, tripHasUnsavedChanges } from '../../resources/js/trip-validation.js';
import { readTripResponse } from '../../resources/js/trip-dialogs.js';

const complete = () => ({ trip_no: 'T-1', trip_type: 'Delivery', payment_type: 'Cash', tariff_id: 1, vehicle_id: 1, personnels: [{ employee_id: 1, designation: 'Driver' }], segments: [{ origin_id: 1, destination_id: 2, total_distance: 10 }], costs: [{ cost_code: 1, cost_amount: 0 }] });

test('dispatch requires saving changed information and allows unchanged saved information', () => {
    const saved = complete();
    const edited = complete();
    assert.equal(tripHasUnsavedChanges(edited, saved), false);
    edited.trip_no = 'Unsaved trip number';
    assert.equal(tripHasUnsavedChanges(edited, saved), true);
    edited.trip_no = saved.trip_no;
    edited.personnels.push({ employee_id: 2, designation: 'Helper' });
    assert.equal(tripHasUnsavedChanges(edited, saved), true);
});

test('save collects every incomplete section and deduplicates multiple field failures', () => {
    const errors = tripSaveErrors({}, { 'costs.0.cost_code': ['Required'], 'costs.0.cost_amount': ['Required'], 'costs.1.cost_amount': ['Required'] });
    const sections = tripErrorSections(errors);
    assert.deepEqual(Object.keys(sections), ['general', 'vehicle', 'segments', 'personnels', 'costs']);
    assert.equal(new Set(Object.values(sections)).size, 5);
    assert.equal(Object.values(sections).filter(message => message.startsWith('General Trip Information')).length, 1);
});

test('additional costs are required and a complete trip accepts zero costs', () => {
    assert.deepEqual(tripSaveErrors(complete()), {});
    assert.equal(tripErrorSections(tripSaveErrors({})).costs, 'Additional Trip Cost is required.');
});

test('missing and empty cost collections and amounts block saving until a valid value is entered', () => {
    for (const costs of [undefined, null, []]) {
        const payload = { ...complete(), costs };
        assert.equal(tripErrorSections(tripSaveErrors(payload)).costs, 'Additional Trip Cost is required.');
    }
    for (const amount of [undefined, null, '', ' ', 'bad', -1, 1000000]) {
        const payload = complete();
        payload.costs[0].cost_amount = amount;
        assert.ok(tripSaveErrors(payload)['costs.0.cost_amount']);
    }
    for (const amount of [0, '0', '0.00', 15.25]) {
        const payload = complete();
        payload.costs[0].cost_amount = amount;
        assert.deepEqual(tripSaveErrors(payload), {});
    }
    const current = { 'costs.0.cost_amount': ['Additional Trip Cost is required.'] };
    assert.deepEqual(updateTripSectionErrors(current, 'costs', tripSaveErrors(complete())), {});
});

test('existing fuel rows in the unified cost section also fulfill the cost requirement', () => {
    const payload = { ...complete(), costs: [], fuel_purchases: [{ fuel_supplier: 1, liters_required: 0, price_per_liter: 60 }] };
    assert.deepEqual(tripSaveErrors(payload), {});
});

test('zero distance and missing driver remain blocked until corrected', () => {
    const payload = complete();
    payload.segments[0].total_distance = 0;
    payload.personnels[0].designation = 'Helper';
    assert.deepEqual(Object.keys(tripErrorSections(tripSaveErrors(payload))), ['segments', 'personnels']);
    payload.personnels[0].designation = 'DRIVER';
    payload.segments[0].total_distance = 10;
    assert.deepEqual(tripSaveErrors(payload), {});
});

test('field errors retain eligibility, cost, and disbursement section mapping', () => {
    assert.equal(tripErrorSection('fuel_purchases.0.fuel_supplier'), 'costs');
    assert.equal(tripErrorSection('trip_route_origin'), 'segments');
    assert.equal(tripErrorSection('disbursements.0.cash_released'), 'disbursements');
    const errors = tripSaveErrors(complete(), { vehicle_id: ['Already dispatched'], 'personnels.0.employee_id': ['Inactive'], 'costs.0.shipping_rate_id': ['Required'] });
    assert.deepEqual(Object.keys(tripErrorSections(errors)), ['vehicle', 'personnels', 'costs']);
    assert.deepEqual(errors.vehicle_id, ['Already dispatched']);
    assert.deepEqual(tripSaveErrors({}, { vehicle_id: ['Not Running'] }).vehicle_id, ['Not Running']);
});

test('backend response displays each section once and retains field details for highlighting', async () => {
    const errors = { trip_no: ['Required'], trip_type: ['Required'], vehicle_id: ['Not running'] };
    const sections = tripErrorSections(errors);
    await assert.rejects(readTripResponse({ ok: false, json: async () => ({ errors, sections }) }), error => {
        assert.equal(error.message, Object.values(sections).join('\n'));
        assert.deepEqual(error.errors, errors);
        assert.deepEqual(error.sections, sections);
        return true;
    });
    await assert.rejects(readTripResponse({ ok: false, json: async () => ({ errors: { one: ['Same'], two: ['Same'] } }) }), error => error.message === 'Same');
});

test('correcting a section clears only resolved errors and retains other server failures', () => {
    const current = { trip_no: ['Required'], trip_type: ['Required'], vehicle_id: ['Already dispatched'] };
    const partial = updateTripSectionErrors(current, 'general', { trip_type: ['Required'] });
    assert.deepEqual(partial, { trip_type: ['Required'], vehicle_id: ['Already dispatched'] });
    const resolved = updateTripSectionErrors(partial, 'general', {});
    assert.deepEqual(resolved, { vehicle_id: ['Already dispatched'] });
    assert.deepEqual(updateTripSectionErrors(resolved, 'vehicle', {}), {});
});
