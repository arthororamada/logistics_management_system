import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { createNotifications, withSubmissionLock, confirmedNotifications } from './notification-core';

export const notifications = createNotifications(Swal);
// New modules import this service; ordinary forms and data-confirm actions are handled globally.
// Available to server-rendered scripts and future modules without a second dialog implementation.
window.AppNotifications = notifications;

const readPayload = (page) => {
    const element = page.querySelector('[data-app-notifications]');
    if (!element) return null;
    try { return JSON.parse(element.textContent); } catch { return null; }
};

export function installNotifications() {
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented
            || form.matches('[data-trip-action], [data-trip-editor], [data-native-submit]')) return;
        const submitter = event.submitter;
        const method = (submitter?.getAttribute('formmethod') || form.method || 'get').toUpperCase();
        if (method === 'GET' || method === 'DIALOG') return;
        event.preventDefault();
        await withSubmissionLock(form, async () => {
            const override = form.querySelector('[name="_method"]')?.value?.toUpperCase();
            const destructive = override === 'DELETE' || method === 'DELETE';
            const message = form.dataset.confirm || (destructive ? 'Delete this record? This action cannot be undone.' : '');
            if (message && !await notifications.confirm(message, {
                title: form.dataset.confirmTitle || (destructive ? 'Delete record?' : 'Confirm action'),
                confirmButtonText: form.dataset.confirmButton || (destructive ? 'Delete' : 'Confirm'),
            })) return;
            const content = submitter ? [...submitter.childNodes] : [];
            if (submitter?.tagName === 'BUTTON') submitter.textContent = 'Saving…';
            try {
                const data = new FormData(form);
                if (submitter?.name) data.append(submitter.name, submitter.value);
                const response = await fetch(submitter?.getAttribute('formaction') || form.action, {
                    method, body: data, credentials: 'same-origin', headers: { Accept: 'text/html' },
                });
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const payload = readPayload(page);
                const messages = confirmedNotifications(payload, response);
                for (const message of messages) {
                    if (['success', 'warning', 'info'].includes(message.type)) await notifications[message.type](message.message);
                }
                window.location.assign(response.url);
            } catch (error) {
                await notifications.error(error.message || 'Unable to connect to the server. Please try again.');
            } finally {
                if (submitter?.tagName === 'BUTTON') submitter.replaceChildren(...content);
            }
        });
    });
    const payload = readPayload(document);
    if (payload?.errors?.length) notifications.error(payload.errors.join('\n'), 'Please review the form');
    for (const message of payload?.messages || []) {
        if (['success', 'error', 'warning', 'info'].includes(message.type)) notifications[message.type](message.message);
    }
    // DataTables otherwise uses its own native browser alert for failed AJAX requests.
    if (window.DataTable?.ext) window.DataTable.ext.errMode = () => notifications.error('Unable to load the directory. Reload the page and try again.');
}
