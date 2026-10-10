import test from 'node:test';
import assert from 'node:assert/strict';
import { personnelSalaryCents } from '../../resources/js/trip-calculations.js';
import { matchingSelectOptions } from '../../resources/js/trip-searchable-select.js';
import { createTripDialogs, withTripSubmissionLock, readTripResponse } from '../../resources/js/trip-dialogs.js';

test('trip success requires a confirmed backend response and failures retain useful errors', async () => {
    const saved = { message: 'Dispatched', redirect: '/trips/1', trip: { id: 1 } };
    assert.deepEqual(await readTripResponse({ ok: true, json: async () => saved }), saved);
    await assert.rejects(readTripResponse({ ok: true, json: async () => ({}) }), /did not confirm/);
    await assert.rejects(readTripResponse({ ok: true, json: async () => { throw new Error('HTML response'); } }), /did not confirm/);
    await assert.rejects(readTripResponse({ ok: false, status: 422, json: async () => ({ errors: { personnels: ['Assign a driver.'] } }) }), /Assign a driver/);
    await assert.rejects(readTripResponse({ ok: false, status: 500, json: async () => { throw new Error('HTML error'); } }), /Request failed \(500\)/);
});

test('trip confirmations require explicit approval and show the correct action', async () => {
    const calls = [];
    let response = { isDismissed: true };
    const dialogs = createTripDialogs({ fire: async (options) => { calls.push(options); return response; } });
    assert.equal(await dialogs.confirm('dispatch'), false);
    assert.equal(calls[0].confirmButtonText, 'Confirm Dispatch');
    assert.match(calls[0].text, /read-only/);
    assert.equal(calls[0].focusCancel, true);
    response = { isConfirmed: true };
    assert.equal(await dialogs.confirm('complete'), true);
    assert.equal(calls[1].confirmButtonText, 'Complete Trip');
    assert.match(calls[1].text, /Standard editing/);
    await dialogs.success('Saved');
    await dialogs.error('Validation failed');
    assert.equal(calls[2].icon, 'success');
    assert.equal(calls[3].icon, 'error');
});

test('trip lock blocks duplicate confirmations and releases controls after cancellation or failure', async () => {
    const buttons = [{ disabled: false }, { disabled: true }];
    const attributes = new Map();
    const form = {
        dataset: {}, querySelectorAll: () => buttons,
        setAttribute: (key, value) => attributes.set(key, value),
        removeAttribute: (key) => attributes.delete(key),
    };
    let resolveConfirmation;
    const confirmation = new Promise((resolve) => { resolveConfirmation = resolve; });
    let requests = 0;
    const pending = withTripSubmissionLock(form, async () => {
        if (await confirmation) requests++;
    });
    assert.equal(buttons[0].disabled, true);
    assert.equal(attributes.get('aria-busy'), 'true');
    await withTripSubmissionLock(form, () => { requests++; });
    resolveConfirmation(false);
    await pending;
    assert.equal(requests, 0);
    assert.equal(buttons[0].disabled, false);
    assert.equal(buttons[1].disabled, true);
    assert.equal(form.dataset.busy, undefined);
    await assert.rejects(withTripSubmissionLock(form, async () => { throw new Error('Network failure'); }), /Network failure/);
    assert.equal(buttons[0].disabled, false);
    assert.equal(attributes.has('aria-busy'), false);
});

test('selected designation and trip rate determine salary', () => {
    const rates = {
        Driver: { base_cents: 100000, additional_cents: 10000, minimum_drops: 1 },
        Helper: { base_cents: 50000, additional_cents: 5000, minimum_drops: 1 },
        'Dispatch Assistant': { base_cents: 30025, additional_cents: 2510, minimum_drops: 1 },
    };
    assert.equal(personnelSalaryCents(rates.Driver, 2), 110000);
    assert.equal(personnelSalaryCents(rates.Helper, 2), 55000);
    assert.equal(personnelSalaryCents(rates['Dispatch Assistant'], 3), 35045);
    assert.equal(personnelSalaryCents({ ...rates.Helper, base_cents: 70000 }, 2), 75000);
    assert.equal(personnelSalaryCents(undefined, 2), null);
});

test('drops below the minimum leave the configured base salary intact', () => {
    assert.equal(personnelSalaryCents({ base_cents: 12345, additional_cents: 1000, minimum_drops: 3 }, 1), 12345);
});

test('search selects filter actual options without changing the selection', () => {
    const options = [
        { value: '1', textContent: 'VAN-001 ABC123', selected: true },
        { value: '2', textContent: 'VAN-002 XYZ456' },
        { value: '2', textContent: 'Duplicate VAN-002' },
        { value: '3', textContent: 'Disabled Van', disabled: true },
        { value: '4', textContent: 'Hidden Van', hidden: true },
    ];
    assert.deepEqual(matchingSelectOptions(options, '  xyz  '), [options[1]]);
    assert.deepEqual(matchingSelectOptions(options, 'missing'), []);
    assert.deepEqual(matchingSelectOptions(options, ''), [options[0], options[1]]);
    assert.equal(options[0].selected, true);
});
