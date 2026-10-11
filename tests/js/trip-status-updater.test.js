import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { readStatusResponse } from '../../resources/js/trip-status-updater-api.js';
import { createNotifications, withSubmissionLock } from '../../resources/js/notification-core.js';

const trip = { trip_id: 'TRIP-1', current_status: 'Open', created_at: 'Oct 11, 2026 10:00', version: 0 };
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });

test('search and update accept confirmed saved trip data', async () => {
    assert.deepEqual(await readStatusResponse(response({ trip })), { trip });
    const saved = { message: 'Updated', trip: { ...trip, current_status: 'Cancelled', version: 1 } };
    assert.deepEqual(await readStatusResponse(response(saved), true), saved);
});

test('validation and not-found responses expose backend messages', async () => {
    await assert.rejects(readStatusResponse(response({ errors: { reason: ['Reason required'] } }, 422)), /Reason required/);
    await assert.rejects(readStatusResponse(response({ message: 'Trip does not exist.' }, 404)), /Trip does not exist/);
});

test('expired sessions and malformed successful responses cannot report success', async () => {
    await assert.rejects(readStatusResponse(response(null, 419)), /session expired/);
    for (const data of [null, {}, { trip: { ...trip, version: '0' } }]) {
        await assert.rejects(readStatusResponse(response(data)), /did not confirm/);
    }
    await assert.rejects(readStatusResponse(response({ trip }), true), /did not confirm/);
});

// Run the module's real Update handler independently of its search/table controls.
const source = readFileSync(new URL('../../resources/js/trip-status-updater.js', import.meta.url), 'utf8');
const updateHandler = source.match(/update\?\.addEventListener\('submit', async \(event\) => \{[\s\S]*?\n    \}\);/)[0];

test('search results and current status render badges that refresh with the saved status', () => {
    const rendering = source.match(/    const statusBadge = \(status\) => \{[\s\S]*?\n    search\.addEventListener/)[0].replace(/\n    search\.addEventListener$/, '');
    const node = () => ({
        children: [],
        append(child) { this.children.push(child); },
        replaceChildren(...children) { this.children = children; },
        addEventListener(_, listener) { this.click = listener; },
        insertCell() { const cell = node(); this.append(cell); return cell; },
        insertRow() { const row = node(); this.append(row); return row; },
        focus() {},
    });
    const table = Object.assign(node(), { dataset: { canUpdate: '1' } });
    const current = node();
    const editor = { hidden: true };
    const context = {
        table, editor, selected: null, busy: false,
        update: { reset() {}, querySelector: selector => selector === '[data-status-current]' ? current : node() },
        document: { createElement: node },
    };
    runInNewContext(`${rendering}\nthis.renderTrip = render;`, context);
    for (const status of ['Open', 'Dispatched', 'Completed', 'Cancelled']) {
        context.renderTrip({ ...trip, current_status: status });
        assert.equal(table.children.length, 1);
        const row = table.children[0];
        const badge = row.children[4].children[0];
        assert.equal(badge.textContent, status);
        assert.equal(badge.className, `status-badge trip-status trip-status-${status.toLowerCase()}`);
        row.children[0].children[0].click();
        assert.equal(current.children.length, 1);
        assert.equal(current.children[0].textContent, status);
        assert.equal(current.children[0].className, badge.className);
        assert.equal(editor.hidden, false);
    }
});

function updateForm({ confirmed = true, confirmation, failure = false, reasonValue = 'Approved correction' } = {}) {
    const alerts = [];
    const requests = [];
    const messages = [];
    const rendered = [];
    const button = { textContent: 'Update', disabled: false };
    const searchButton = { disabled: false };
    const reason = { value: reasonValue, setCustomValidity(text) { this.error = text; }, reportValidity() {} };
    let submit;
    let closed = false;
    const update = {
        action: '/trip-status-updater', dataset: {},
        addEventListener: (_, listener) => { submit = listener; },
        querySelector: (selector) => selector === '[type="submit"]' ? button : selector === '[name="reason"]' ? reason : { value: 'Cancelled' },
        querySelectorAll: () => [button], setAttribute() {}, removeAttribute() {},
    };
    const context = {
        update, selected: { ...trip }, busy: false, withSubmissionLock, readStatusResponse,
        search: { querySelector: () => searchButton },
        document: { querySelector: () => ({ content: 'csrf-token' }) },
        notifications: createNotifications({ fire: async (options) => {
            alerts.push(options);
            if (options.showCancelButton) return confirmation || { isConfirmed: confirmed };
            return {};
        } }),
        showMessage: (text, error) => messages.push({ text, error }),
        render: (saved) => rendered.push(saved), close: () => { closed = true; },
        fetch: async (url, options) => {
            requests.push({ url, options });
            return failure ? response({ errors: { reason: ['Update rejected.'] } }, 422)
                : response({ message: 'Trip status updated.', trip: { ...trip, current_status: 'Cancelled', version: 1 } });
        },
    };
    runInNewContext(updateHandler, context);
    return { alerts, requests, messages, rendered, button, searchButton, reason, context, update,
        get closed() { return closed; }, submit: () => submit({ preventDefault() {} }) };
}

test('canceling status update preserves the editor and sends no API request', async () => {
    const form = updateForm({ confirmed: false });
    await form.submit();
    assert.equal(form.alerts[0].title, 'Update Trip Status?');
    assert.equal(form.alerts[0].text, 'Update trip TRIP-1 from Open to Cancelled?');
    assert.equal(form.alerts[0].confirmButtonText, 'Update');
    assert.equal(form.alerts[0].cancelButtonText, 'Cancel');
    assert.equal(form.requests.length, 0);
    assert.equal(form.messages.length, 0);
    assert.equal(form.rendered.length, 0);
    assert.equal(form.closed, false);
    assert.equal(form.reason.value, 'Approved correction');
    assert.equal(form.button.disabled, false);
    assert.equal(form.searchButton.disabled, false);
    assert.equal(form.context.busy, false);
});

test('confirmed status update preserves the PUT payload and shows success', async () => {
    const form = updateForm();
    await form.submit();
    assert.equal(form.requests.length, 1);
    const { options } = form.requests[0];
    assert.equal(options.method, 'PUT');
    assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.deepEqual(JSON.parse(options.body), { trip_id: 'TRIP-1', version: 0, new_status: 'Cancelled', reason: 'Approved correction' });
    assert.equal(form.rendered[0].current_status, 'Cancelled');
    assert.equal(form.closed, true);
    assert.equal(form.alerts[1].icon, 'success');
    assert.equal(form.alerts[1].text, 'Trip status updated.');
});

test('failed status update displays the backend error and retains the editor', async () => {
    const form = updateForm({ failure: true });
    await form.submit();
    assert.equal(form.alerts[1].icon, 'error');
    assert.equal(form.alerts[1].text, 'Update rejected.');
    assert.equal(form.messages.at(-1).error, true);
    assert.equal(form.closed, false);
    assert.equal(form.rendered.length, 0);
    assert.equal(form.button.disabled, false);
    assert.equal(form.searchButton.disabled, false);
});

test('status update retains reason validation and prevents duplicate confirmations', async () => {
    const invalid = updateForm({ reasonValue: ' ' });
    await invalid.submit();
    assert.equal(invalid.reason.error, 'Provide a reason for changing the status.');
    assert.equal(invalid.alerts.length, 0);
    assert.equal(invalid.requests.length, 0);
    let finish;
    const confirmation = new Promise(resolve => { finish = resolve; });
    const form = updateForm({ confirmation });
    const pending = form.submit();
    await Promise.resolve();
    await form.submit();
    assert.equal(form.alerts.length, 1);
    assert.equal(form.requests.length, 0);
    assert.equal(form.button.disabled, true);
    finish({ isConfirmed: true });
    await pending;
    assert.equal(form.requests.length, 1);
    assert.equal(form.button.textContent, 'Update');
    assert.equal(form.update.dataset.busy, undefined);
    assert.equal(form.context.busy, false);
});
