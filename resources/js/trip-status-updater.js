import { notifications } from './notifications';
import { withSubmissionLock } from './notification-core';
import { readStatusResponse } from './trip-status-updater-api';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-trip-status-updater]');
    if (!root) return;
    const search = root.querySelector('[data-status-search]');
    const update = root.querySelector('[data-status-update]');
    const editor = root.querySelector('[data-status-editor]');
    const table = root.querySelector('[data-status-results]');
    const message = root.querySelector('[data-status-message]');
    let selected = null;
    let busy = false;
    const showMessage = (text, error = false) => {
        message.textContent = text;
        message.className = error ? 'alert alert-error' : 'alert';
        message.hidden = false;
    };
    const close = () => {
        if (editor) editor.hidden = true;
        update?.reset();
    };
    const empty = (text) => {
        selected = null;
        table.replaceChildren();
        const row = table.insertRow();
        const cell = row.insertCell();
        cell.colSpan = 5; cell.className = 'empty-table'; cell.textContent = text;
    };
    const statusBadge = (status) => {
        const badge = document.createElement('span');
        badge.className = `status-badge trip-status trip-status-${status.toLowerCase()}`;
        badge.textContent = status;
        return badge;
    };
    const render = (trip) => {
        selected = trip;
        table.replaceChildren();
        const row = table.insertRow();
        const action = row.insertCell();
        if (table.dataset.canUpdate === '1') {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'button button-primary'; button.textContent = 'Update Status';
            button.addEventListener('click', () => {
                if (busy) return;
                update.reset();
                update.querySelector('[data-status-trip-id]').value = selected.trip_id;
                update.querySelector('[data-status-current]').replaceChildren(statusBadge(selected.current_status));
                editor.hidden = false;
                update.querySelector('[name="new_status"]').focus();
            });
            action.append(button);
        } else action.textContent = '—';
        for (const value of [trip.trip_id, trip.created_at, trip.van_number || '—']) row.insertCell().textContent = value;
        row.insertCell().append(statusBadge(trip.current_status));
    };
    search.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        busy = true;
        close(); empty('Searching…'); showMessage('Searching…');
        const button = search.querySelector('button');
        await withSubmissionLock(search, async () => {
            button.textContent = 'Searching…';
            try {
                const url = new URL(search.action);
                url.searchParams.set('trip_id', search.querySelector('[name="trip_id"]').value.trim());
                const result = await readStatusResponse(await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }));
                render(result.trip); message.hidden = true;
            } catch (error) {
                empty('No matching trip.'); showMessage(error.message, true);
            } finally {
                button.textContent = 'Search'; busy = false;
            }
        });
    });
    root.querySelector('[data-status-cancel]')?.addEventListener('click', () => { if (!busy) close(); });
    update?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !selected) return;
        const reason = update.querySelector('[name="reason"]');
        if (!reason.value.trim()) { reason.setCustomValidity('Provide a reason for changing the status.'); reason.reportValidity(); return; }
        reason.setCustomValidity('');
        const payload = { trip_id: selected.trip_id, version: selected.version, new_status: update.querySelector('[name="new_status"]').value, reason: reason.value.trim() };
        busy = true;
        const searchButton = search.querySelector('button');
        searchButton.disabled = true;
        await withSubmissionLock(update, async () => {
            const button = update.querySelector('[type="submit"]');
            try {
                if (!await notifications.confirm(`Update trip ${payload.trip_id} from ${selected.current_status} to ${payload.new_status}?`, {
                    title: 'Update Trip Status?',
                    confirmButtonText: 'Update',
                    confirmButtonColor: '#1d4ed8',
                })) return;
                showMessage('Updating trip status…');
                button.textContent = 'Updating…';
                const result = await readStatusResponse(await fetch(update.action, {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(payload),
                }), true);
                render(result.trip); close(); showMessage(result.message);
                await notifications.success(result.message);
            } catch (error) {
                showMessage(error.message, true);
                await notifications.error(error.message);
            } finally {
                button.textContent = 'Update'; searchButton.disabled = false; busy = false;
            }
        });
    });
    update?.querySelector('[name="reason"]').addEventListener('input', (event) => event.target.setCustomValidity(''));
});
