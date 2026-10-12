export const waybillFields = ['dm_id', 'waybill_number', 'drops_number', 'quantity', 'kilogram', 'cbm'];

export function manifestWaybillRows(manifest, existing = []) {
    const used = new Set(existing.map(row => row.waybill_number));
    return manifest.waybill_numbers.filter(number => !used.has(number)).map(number => ({
        dm_id: manifest.id, waybill_number: number, drops_number: '', quantity: '', kilogram: '', cbm: '',
    }));
}

export function syncWaybillPreview(editor, preview) {
    if (!preview) return;
    preview.replaceChildren();
    editor.querySelectorAll('[data-trip-collection="waybills"] [data-trip-row]').forEach(row => {
        const tr = document.createElement('tr');
        for (const field of waybillFields) {
            const td = document.createElement('td');
            td.textContent = row.querySelector(`[name$="[${field}]"]`).value;
            tr.append(td);
        }
        preview.append(tr);
    });
    if (!preview.children.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 6; td.textContent = 'No waybills added.'; tr.append(td); preview.append(tr);
    }
}

export function initializeTripWaybills(editor, onChange) {
    const type = editor.querySelector('[name="trip_type"]');
    const select = editor.querySelector('[data-trip-dm]');
    const container = editor.querySelector('[data-trip-collection="waybills"]');
    const message = editor.querySelector('[data-trip-dm-message]');
    const button = editor.querySelector('[data-trip-add-dm]');
    const preview = document.querySelector('[data-trip-waybill-preview]');
    let manifests = [];
    let currentType = type.value;
    let sequence = 0;
    const updatePreview = () => syncWaybillPreview(editor, preview);
    const refresh = async () => {
        const requestId = ++sequence;
        if (currentType !== type.value) {
            container.replaceChildren();
            currentType = type.value;
            updatePreview();
            onChange(select);
        }
        manifests = [];
        select.replaceChildren(new Option(type.value ? 'Loading pending DMs...' : 'Select a trip type first', ''));
        select.disabled = button.disabled = true;
        if (!type.value) return;
        try {
            const url = new URL(editor.dataset.manifestsUrl, window.location.origin);
            url.searchParams.set('trip_type', type.value);
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Unable to load pending DMs. Try selecting the trip type again.');
            const records = await response.json();
            if (requestId !== sequence) return;
            manifests = records;
            select.replaceChildren(new Option('Select pending DM', ''));
            manifests.forEach(record => select.append(new Option(`#${record.id} (${record.waybill_numbers.length} waybills)`, String(record.id))));
            select.disabled = button.disabled = !manifests.length;
            message.textContent = manifests.length ? 'Select a pending DM to populate its waybills. Complete the quantities for every row.' : 'No pending DMs for this trip type.';
        } catch (error) {
            if (requestId !== sequence) return;
            select.replaceChildren(new Option('Unable to load DMs', ''));
            message.textContent = error.message;
        }
    };
    const add = () => {
        const manifest = manifests.find(record => String(record.id) === select.value);
        if (!manifest) return;
        const existing = [...container.querySelectorAll('[name$="[waybill_number]"]')].map(input => ({ waybill_number: input.value }));
        const rows = manifestWaybillRows(manifest, existing);
        for (const data of rows) {
            const fragment = document.getElementById('trip-template-waybills').content.cloneNode(true);
            waybillFields.forEach(field => { fragment.querySelector(`[name$="[${field}]"]`).value = data[field]; });
            container.append(fragment);
        }
        container.querySelectorAll('[data-trip-row]').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/\[[^\]]+\]/, `[${index}]`); });
        });
        message.textContent = rows.length ? `${rows.length} waybill rows added. Complete Number of Drops, Quantity, Kilogram and CBM for each row.` : 'All waybills from this DM are already on the trip.';
        updatePreview();
        onChange(select);
    };
    select.addEventListener('change', add);
    button.addEventListener('click', add);
    type.addEventListener('change', refresh);
    editor.querySelector('[data-trip-rate]').addEventListener('change', () => {
        queueMicrotask(() => { if (currentType !== type.value) refresh(); });
    });
    editor.addEventListener('input', updatePreview);
    editor.addEventListener('change', updatePreview);
    editor.addEventListener('click', () => queueMicrotask(updatePreview));
    updatePreview();
    refresh();
}
