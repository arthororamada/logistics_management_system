import test from 'node:test';
import assert from 'node:assert/strict';
import { createNotifications, confirmedNotifications, withSubmissionLock } from '../../resources/js/notification-core.js';

test('global notifications have consistent icons, text, labels and colors', async () => {
    const calls = [];
    const notifications = createNotifications({ fire: async (options) => { calls.push(options); return {}; } });
    for (const type of ['success', 'error', 'warning', 'info']) await notifications[type]('Actual backend message');
    assert.deepEqual(calls.map((call) => call.icon), ['success', 'error', 'warning', 'info']);
    calls.forEach((call) => { assert.equal(call.text, 'Actual backend message'); assert.equal(call.confirmButtonText, 'OK'); });
    assert.equal(new Set(calls.map((call) => call.confirmButtonColor)).size, 4);
});

test('global dialogs are serialized so errors do not overwrite an open confirmation', async () => {
    const calls = [];
    let finish;
    const notifications = createNotifications({ fire: (options) => {
        calls.push(options);
        if (calls.length === 1) return new Promise((resolve) => { finish = resolve; });
        return Promise.resolve({});
    } });
    const confirmed = notifications.confirm('Delete this record?', { confirmButtonText: 'Delete' });
    const failed = notifications.error('Another request failed');
    await Promise.resolve();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].cancelButtonText, 'Cancel');
    assert.equal(calls[0].focusCancel, true);
    finish({ isDismissed: true });
    assert.equal(await confirmed, false);
    await failed;
    assert.equal(calls.length, 2);
});

test('input cancellation returns null and confirmed empty or entered values remain valid results', async () => {
    let result = { isDismissed: true };
    const notifications = createNotifications({ fire: async () => result });
    assert.equal(await notifications.input('Reason'), null);
    result = { isConfirmed: true, value: '' };
    assert.equal(await notifications.input('Reason'), '');
    result = { isConfirmed: true, value: 'Approved after review' };
    assert.equal(await notifications.input('Reason'), 'Approved after review');
});

test('only backend success is accepted, with validation and business errors taking priority', () => {
    const response = { ok: true, status: 200 };
    const success = { type: 'success', message: 'Record created.' };
    assert.deepEqual(confirmedNotifications({ messages: [success], errors: [] }, response), [success]);
    assert.throws(() => confirmedNotifications(null, response), /did not confirm/);
    assert.throws(() => confirmedNotifications({ messages: [success], errors: ['A name is required.'] }, response), /A name is required/);
    assert.throws(() => confirmedNotifications({ messages: [{ type: 'error', message: 'Record is in use.' }] }, response), /Record is in use/);
    for (const status of [403, 419, 500]) assert.throws(() => confirmedNotifications(null, { ok: false, status }));
});

test('global loading state closes even when the asynchronous operation fails', async () => {
    let closed = false;
    const notifications = createNotifications({ fire: async () => ({}), close: () => { closed = true; }, showLoading: () => {} });
    await assert.rejects(notifications.run('Saving record', async () => { throw new Error('Server failed'); }), /Server failed/);
    assert.equal(closed, true);
    assert.equal(await notifications.run('Saving record', async () => 'saved'), 'saved');
});

test('canceling a global delete does not send a request and duplicate submits remain blocked', async () => {
    const button = { disabled: false };
    const form = { dataset: {}, querySelectorAll: () => [button], setAttribute: () => {}, removeAttribute: () => {} };
    let finish;
    let requests = 0;
    const notifications = createNotifications({ fire: () => new Promise((resolve) => { finish = resolve; }) });
    const submit = () => withSubmissionLock(form, async () => {
        if (await notifications.confirm('Delete record?')) requests++;
    });
    const pending = submit();
    await Promise.resolve();
    await submit();
    finish({ isDismissed: true });
    await pending;
    assert.equal(requests, 0);
    assert.equal(button.disabled, false);
    assert.equal(form.dataset.busy, undefined);
});
