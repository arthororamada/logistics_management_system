import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { createTripDialogs, withTripSubmissionLock } from '../../resources/js/trip-dialogs.js';
import { createNotifications } from '../../resources/js/notification-core.js';

// Exercise the actual editor submit handler without initializing unrelated trip controls.
const source = readFileSync(new URL('../../resources/js/trips.js', import.meta.url), 'utf8');
const handler = source.match(/editor\.addEventListener\('submit', async \(event\) => \{[\s\S]*?\n        \}\);/)[0];

function setup({ confirmed = true, invalid = {}, failure = null, confirmation } = {}) {
    const calls = [];
    const alerts = [];
    const button = { disabled: false, textContent: 'Save' };
    const payload = { trip_no: '1', version: 0 };
    let submit;
    const editor = {
        action: '/trips/1', dataset: {},
        querySelectorAll: () => [button], setAttribute() {}, removeAttribute() {},
        addEventListener: (_, listener) => { submit = listener; },
    };
    const dialogs = createTripDialogs(createNotifications({ fire: async (options) => {
        alerts.push(options);
        if (options.showCancelButton) return confirmation ? confirmation : { isConfirmed: confirmed };
        return {};
    } }));
    runInNewContext(handler, {
        editor, dialogs, withTripSubmissionLock,
        refreshAvailability: async () => { calls.push('availability'); },
        tripFormPayload: () => payload,
        invalidTripFields: () => invalid,
        tripSaveErrors: (_, errors) => errors,
        validation: { show: (errors) => { calls.push({ errors }); } },
        request: async (url, method, data) => {
            calls.push({ url, method, data });
            if (failure) throw failure;
            return { message: 'Trip saved successfully.', redirect: '/trips/1' };
        },
        report: (message) => dialogs.error(message),
        window: { location: { assign: (url) => { calls.push({ redirect: url }); } } },
    });
    return { calls, alerts, button, editor, payload, submit: () => submit({ preventDefault() {}, submitter: button }) };
}

test('canceling Trip Details save sends no request and retains the form', async () => {
    const form = setup({ confirmed: false });
    await form.submit();
    assert.deepEqual(form.calls, []);
    assert.equal(form.alerts.length, 1);
    assert.equal(form.alerts[0].title, 'Save Trip?');
    assert.equal(form.alerts[0].confirmButtonText, 'Save Trip');
    assert.equal(form.alerts[0].cancelButtonText, 'Cancel');
    assert.equal(form.button.disabled, false);
    assert.equal(form.button.textContent, 'Save');
    assert.equal(form.editor.dataset.busy, undefined);
});

test('confirming save validates and uses the existing PUT request and success alert', async () => {
    const form = setup();
    await form.submit();
    assert.equal(form.calls[0], 'availability');
    const request = form.calls.find(call => call.method);
    assert.equal(request.url, '/trips/1');
    assert.equal(request.method, 'PUT');
    assert.equal(request.data, form.payload);
    assert.equal(form.alerts[1].icon, 'success');
    assert.equal(form.alerts[1].text, 'Trip saved successfully.');
    assert.equal(form.calls.at(-1).redirect, '/trips/1');
});

test('confirmation cannot bypass validation or submit a second save', async () => {
    let finish;
    const confirmation = new Promise(resolve => { finish = resolve; });
    const errors = { trip_no: ['Required.'] };
    const form = setup({ invalid: errors, confirmation });
    const pending = form.submit();
    await Promise.resolve();
    await form.submit();
    assert.equal(form.alerts.length, 1);
    assert.equal(form.button.disabled, true);
    finish({ isConfirmed: true });
    await pending;
    assert.equal(form.calls.some(call => call.method), false);
    assert.equal(form.calls.at(-1).errors, errors);
    assert.equal(form.button.disabled, false);
});

test('failed saves show an error alert and retain backend field feedback', async () => {
    for (const errors of [undefined, { trip_no: ['Invalid trip number.'] }]) {
        const failure = Object.assign(new Error('Unable to save trip.'), { errors });
        const form = setup({ failure });
        await form.submit();
        assert.equal(form.alerts[1].icon, 'error');
        assert.equal(form.alerts[1].title, 'Unable to save trip');
        assert.equal(form.alerts[1].text, failure.message);
        assert.equal(form.calls.some(call => call.redirect), false);
        if (errors) assert.equal(form.calls.at(-1).errors, errors);
        assert.equal(form.button.disabled, false);
        assert.equal(form.button.textContent, 'Save');
    }
});
