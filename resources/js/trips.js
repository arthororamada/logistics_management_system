import { personnelSalaryCents } from './trip-calculations';
import { enhanceTripSelects, refreshTripSelects, expandTripSection } from './trip-searchable-select';
import { notifications } from './notifications';
import { createTripDialogs, withTripSubmissionLock, readTripResponse } from './trip-dialogs';
import { syncTripCost } from './trip-costs';

document.addEventListener('DOMContentLoaded', () => {
    const dialogs = createTripDialogs(notifications);
    const errors = document.querySelector('[data-trip-errors]');
    const report = (message) => {
        if (errors) {
            errors.textContent = message;
            errors.hidden = false;
        }
        return dialogs.error(message);
    };
    const request = async (url, method, data) => {
        const response = await fetch(url, {
            method, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            body: JSON.stringify(data),
        });
        return readTripResponse(response);
    };
    document.querySelectorAll('[data-trip-action]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            await withTripSubmissionLock(form, async () => {
                if (form.hasAttribute('data-confirm') && !await dialogs.confirm(form.dataset.tripOperation)) return;
                const button = form.querySelector('button');
                const label = button.textContent;
                button.textContent = 'Saving…';
                try {
                    const result = await request(form.action, 'POST', Object.fromEntries(new FormData(form)));
                    await dialogs.success(result.message || 'Trip saved successfully.');
                    if (form.querySelector('[name="creation_key"]')) window.location.assign(result.redirect);
                    else window.location.reload();
                } catch (error) {
                    await report(error.message);
                } finally {
                    button.textContent = label;
                }
            });
        });
    });
    const editor = document.querySelector('[data-trip-editor]');
    if (editor) {
        const collections = ['personnels', 'segments', 'fuel_purchases', 'costs', 'disbursements'];
        const rateSelect = editor.querySelector('[data-trip-rate]');
        const releasesContainer = editor.querySelector('[data-trip-disbursements]');
        const savedReleases = new Map(JSON.parse(releasesContainer.dataset.savedReleases).map((release) => [String(release.employee_id), Number(release.cash_released)]));
        const pendingReleases = new Map(savedReleases);
        const captureReleases = () => {
            releasesContainer.querySelectorAll('[data-disbursement-row]').forEach((row) => {
                pendingReleases.set(row.querySelector('[name$="[employee_id]"]').value, row.querySelector('[name$="[cash_released]"]').value);
            });
        };
        const syncDisbursements = () => {
            captureReleases();
            releasesContainer.replaceChildren();
            const assigned = new Set();
            editor.querySelectorAll('[data-trip-collection="personnels"] [data-trip-row]').forEach((person) => {
                const employee = person.querySelector('[name$="[employee_id]"]');
                if (!employee.value || assigned.has(employee.value)) return;
                const index = assigned.size;
                assigned.add(employee.value);
                const fragment = document.getElementById('trip-disbursement-template').content.cloneNode(true);
                const row = fragment.querySelector('[data-disbursement-row]');
                row.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace('[__INDEX__]', `[${index}]`); });
                row.querySelector('[name$="[employee_id]"]').value = employee.value;
                row.querySelector('[data-disbursement-employee]').textContent = employee.selectedOptions[0].textContent;
                const salary = Number(person.querySelector('[data-personnel-salary]').value || 0);
                const released = row.querySelector('[name$="[cash_released]"]');
                released.value = pendingReleases.get(employee.value) ?? 0;
                released.min = savedReleases.get(employee.value) ?? 0;
                released.max = salary.toFixed(2);
                row.querySelector('[data-disbursement-balance]').value = Math.max(0, salary - Number(released.value)).toFixed(2);
                releasesContainer.append(fragment);
            });
        };
        const syncPersonnelSalaries = () => {
            const rates = JSON.parse(rateSelect.selectedOptions[0]?.dataset.personnelRates || '{}');
            const drops = Number(editor.querySelector('[name="drops_number"]').value || 0);
            editor.querySelectorAll('[data-trip-collection="personnels"] [data-trip-row]').forEach((row) => {
                const designation = row.querySelector('[name$="[designation]"]');
                const salary = personnelSalaryCents(rates[designation.value], drops);
                row.querySelector('[data-personnel-salary]').value = salary === null ? '' : (salary / 100).toFixed(2);
                row.querySelector('[data-personnel-rate-error]').hidden = !designation.value || salary !== null;
            });
            syncDisbursements();
        };
        const reindex = (name) => {
            editor.querySelectorAll(`[data-trip-collection="${name}"] [data-trip-row]`).forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/\[[^\]]+\]/, `[${index}]`); });
            });
        };
        editor.addEventListener('click', (event) => {
            const add = event.target.closest('[data-trip-add]');
            if (add) {
                const name = add.dataset.tripAdd;
                editor.querySelector(`[data-trip-collection="${name}"]`).append(document.getElementById(`trip-template-${name}`).content.cloneNode(true));
                reindex(name);
                if (name === 'costs') editor.querySelectorAll('[data-trip-collection="costs"] [data-trip-row]').forEach((row) => syncTripCost(row));
                enhanceTripSelects(editor);
                if (name === 'personnels') syncPersonnelSalaries();
            }
            const remove = event.target.closest('[data-trip-remove]');
            if (remove) {
                const name = remove.closest('[data-trip-collection]').dataset.tripCollection;
                if (name === 'personnels') {
                    const employeeId = remove.closest('[data-trip-row]').querySelector('[name$="[employee_id]"]').value;
                    if ((savedReleases.get(employeeId) ?? 0) > 0) { report('Personnel with released funds cannot be removed.'); return; }
                }
                remove.closest('[data-trip-row]').remove(); reindex(name);
                if (name === 'personnels') syncPersonnelSalaries();
            }
        });
        editor.addEventListener('change', (event) => {
            const field = event.target;
            const row = field.closest('[data-trip-row]');
            if (row && field.name.endsWith('[fuel_supplier]')) row.querySelector('[name$="[price_per_liter]"]').value = field.selectedOptions[0]?.dataset.price ?? 0;
            if (row?.closest('[data-trip-collection="costs"]')) syncTripCost(row, field.name.match(/\[(\w+)\]$/)?.[1]);
            if (row?.closest('[data-trip-collection="personnels"]')) syncPersonnelSalaries();
            refreshTripSelects(editor);
        });
        editor.addEventListener('input', (event) => {
            if (event.target.name === 'drops_number') syncPersonnelSalaries();
            const costRow = event.target.closest('[data-trip-collection="costs"] [data-trip-row]');
            if (costRow) syncTripCost(costRow);
            const row = event.target.closest('[data-disbursement-row]');
            if (row && event.target.name.endsWith('[cash_released]')) {
                captureReleases();
                const salary = Number(event.target.max);
                row.querySelector('[data-disbursement-balance]').value = Math.max(0, salary - Number(event.target.value)).toFixed(2);
            }
        });
        const vehicle = editor.querySelector('[data-trip-vehicle]');
        const syncVehicle = () => {
            const data = JSON.parse(vehicle.selectedOptions[0]?.dataset.vehicle || '{}');
            editor.querySelectorAll('[data-vehicle-field]').forEach((field) => { field.value = data[field.dataset.vehicleField] ?? ''; });
        };
        vehicle.addEventListener('change', syncVehicle); syncVehicle();
        rateSelect.addEventListener('change', (event) => {
            const rate = JSON.parse(event.target.selectedOptions[0]?.dataset.rate || '{}');
            for (const [field, source] of Object.entries({ trip_no: 'trip_number', trip_type: 'trip_type', payment_type: 'payment_type' })) {
                const input = editor.querySelector(`[name="${field}"]`);
                if (!input.value && rate[source]) input.value = rate[source];
            }
            syncPersonnelSalaries();
            refreshTripSelects(editor);
        });
        syncPersonnelSalaries();
        editor.querySelectorAll('[data-trip-collection="costs"] [data-trip-row]').forEach((row) => syncTripCost(row));
        editor.querySelectorAll('[data-trip-section] .card-header').forEach((header) => {
            header.addEventListener('click', (event) => {
                if (!event.target.closest('button, a, input')) header.querySelector('[data-card-toggle]')?.click();
            });
        });
        editor.addEventListener('invalid', (event) => expandTripSection(event.target), true);
        editor.addEventListener('submit', async (event) => {
            event.preventDefault();
            const dispatch = event.submitter?.value === 'dispatch';
            await withTripSubmissionLock(editor, async () => {
                if (dispatch && !await dialogs.confirm('dispatch')) return;
                const payload = Object.fromEntries(collections.map((name) => [name, []]));
                for (const [name, value] of new FormData(editor)) {
                    const match = name.match(/^(personnels|segments|fuel_purchases|costs|disbursements)\[(\d+)\]\[(\w+)\]$/);
                    if (match) {
                        payload[match[1]][Number(match[2])] ??= {};
                        payload[match[1]][Number(match[2])][match[3]] = value === '' ? null : value;
                    } else if (!['_token', '_method'].includes(name)) payload[name] = value === '' ? null : value;
                }
                const button = event.submitter;
                const label = button?.textContent;
                if (button) button.textContent = 'Saving…';
                try {
                    const result = await request(dispatch ? editor.dataset.dispatchUrl : editor.action, dispatch ? 'POST' : 'PUT', payload);
                    await dialogs.success(result.message || 'Trip saved successfully.');
                    window.location.assign(result.redirect);
                } catch (error) {
                    await report(error.message);
                } finally {
                    if (button) button.textContent = label;
                }
            });
        });
    }
    enhanceTripSelects(document);
    const notice = sessionStorage.getItem('trip-notification');
    const noticeTarget = document.querySelector('.trip-workspace, [data-trip-directory]');
    if (notice && noticeTarget) {
        sessionStorage.removeItem('trip-notification');
        dialogs.success(notice);
    }
    document.querySelector('[data-trip-print]')?.addEventListener('click', () => window.print());
    if (document.body.classList.contains('trip-print-page')) window.addEventListener('load', () => window.print(), { once: true });
});
