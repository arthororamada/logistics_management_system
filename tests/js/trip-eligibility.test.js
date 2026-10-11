import test from 'node:test';
import assert from 'node:assert/strict';
import { applyTripAvailability, readTripAvailability } from '../../resources/js/trip-eligibility.js';
import { matchingSelectOptions } from '../../resources/js/trip-searchable-select.js';

test('availability reads its own API payload and rejects failed or malformed responses', async () => {
    const data = { vehicles: { 1: null }, personnels: { 2: 'Inactive' } };
    assert.deepEqual(await readTripAvailability({ ok: true, json: async () => data }), data);
    await assert.rejects(readTripAvailability({ ok: false, json: async () => ({ message: 'Forbidden' }) }), /Forbidden/);
    await assert.rejects(readTripAvailability({ ok: true, json: async () => ({}) }), /did not return/);
    await assert.rejects(readTripAvailability({ ok: true, json: async () => { throw new Error('Bad JSON'); } }), /did not return/);
});

test('availability disables occupied/inactive options and rejects a stale selected assignment', () => {
    const options = [{ value: '', textContent: 'Select', dataset: {} }, { value: '1', textContent: 'Vehicle A', dataset: {} }, { value: '2', textContent: 'Vehicle B', dataset: {} }];
    const select = { options, selectedOptions: [options[1]], setCustomValidity(reason) { this.error = reason; } };
    assert.equal(applyTripAvailability(select, { 1: 'Already dispatched', 2: null }), 'Already dispatched');
    assert.equal(select.error, 'Already dispatched');
    assert.deepEqual(matchingSelectOptions(options, '').map(option => option.value), ['', '2']);
    assert.match(options[1].textContent, /Already dispatched/);
    applyTripAvailability(select, { 1: null, 2: 'Inactive' });
    assert.equal(select.error, '');
    assert.equal(options[1].disabled, false);
    assert.equal(options[1].textContent, 'Vehicle A');
    assert.equal(options[2].disabled, true);
    applyTripAvailability(select, {});
    assert.equal(select.error, 'This record is no longer available.');
});
