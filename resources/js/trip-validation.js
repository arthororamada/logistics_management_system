import { expandTripSection, refreshTripSelects } from './trip-searchable-select.js';

export const tripSectionMessages = {
    general: 'General Trip Information is required. Please complete all required fields.',
    vehicle: 'Vehicle is required. Please select a vehicle.',
    segments: 'Route is required. Please select or configure a valid route with a positive distance.',
    personnels: 'Personnel is required. Please assign the required personnel, including a driver.',
    costs: 'Additional Trip Cost is required.',
    disbursements: 'Please correct the invalid fields in Disbursement Information.',
};

export function tripErrorSection(field) {
    const root = field.split('.')[0];
    if (root === 'vehicle_id') return 'vehicle';
    if (['segments', 'trip_route_origin', 'trip_route_destination', 'total_distance'].includes(root)) return 'segments';
    if (root === 'personnels') return 'personnels';
    if (['costs', 'fuel_purchases', 'fuelPurchases'].includes(root)) return 'costs';
    if (['disbursements', 'gcash_number'].includes(root)) return 'disbursements';
    return 'general';
}

export function tripErrorSections(errors) {
    const affected = new Set(Object.keys(errors).map(tripErrorSection));
    return Object.fromEntries(Object.entries(tripSectionMessages).filter(([section]) => affected.has(section)));
}

export function tripSaveErrors(payload, invalidFields = {}) {
    const errors = { ...invalidFields };
    for (const field of ['trip_no', 'trip_type', 'payment_type', 'tariff_id', 'vehicle_id']) {
        if (!String(payload[field] ?? '').trim() && !Object.hasOwn(errors, field)) errors[field] = ['Required.'];
    }
    if (payload.trip_no != null && String(payload.trip_no).trim() !== '' && !['1', '2', '3', '4', '5'].includes(String(payload.trip_no))) {
        errors.trip_no = ['Please select a trip number from 1 to 5.'];
    }
    if (!payload.personnels?.some(person => person.designation?.toLowerCase() === 'driver')) errors.personnels = [];
    const distance = payload.segments?.reduce((sum, row) => sum + Number(row.total_distance || 0), 0) || 0;
    if (!payload.segments?.length || !Number.isFinite(distance) || distance <= 0) errors.segments = [];
    if (!payload.costs?.length && !payload.fuel_purchases?.length) errors.costs = [tripSectionMessages.costs];
    payload.costs?.forEach((cost, index) => {
        const path = `costs.${index}.cost_amount`;
        if (Object.hasOwn(errors, path)) return;
        const amount = cost.cost_amount;
        const legacy = !Object.hasOwn(cost, 'cost_amount') && cost.released_amount != null && cost.nonreleased_amount != null;
        if (legacy) return;
        if (amount == null || String(amount).trim() === '') errors[path] = [tripSectionMessages.costs];
        else if (!Number.isFinite(Number(amount)) || Number(amount) < 0 || Number(amount) > 999999.99) errors[path] = ['Additional Trip Cost must be between 0 and 999999.99.'];
    });
    return errors;
}

export function updateTripSectionErrors(current, section, local) {
    return {
        ...Object.fromEntries(Object.entries(current).filter(([path]) => tripErrorSection(path) !== section)),
        ...Object.fromEntries(Object.entries(local).filter(([path]) => tripErrorSection(path) === section)),
    };
}

export function tripFormPayload(editor) {
    const payload = Object.fromEntries(['personnels', 'segments', 'fuel_purchases', 'costs', 'disbursements'].map(name => [name, []]));
    for (const [name, value] of new FormData(editor)) {
        const match = name.match(/^(personnels|segments|fuel_purchases|costs|disbursements)\[(\d+)\]\[(\w+)\]$/);
        if (match) {
            payload[match[1]][Number(match[2])] ??= {};
            payload[match[1]][Number(match[2])][match[3]] = value === '' ? null : value;
        } else if (!['_token', '_method'].includes(name)) payload[name] = value === '' ? null : value;
    }
    // FormData omits disabled selected options. Keep the actual assignment value
    // so availability changes produce eligibility errors rather than a false edit.
    editor.querySelectorAll('[data-trip-vehicle], select[name$="[employee_id]"]').forEach(select => {
        if (select.disabled) return;
        const match = select.name.match(/^personnels\[(\d+)\]\[employee_id\]$/);
        if (match) {
            payload.personnels[Number(match[1])] ??= {};
            payload.personnels[Number(match[1])].employee_id = select.value || null;
        } else if (select.name === 'vehicle_id') payload.vehicle_id = select.value || null;
    });
    return payload;
}

export function tripHasUnsavedChanges(payload, savedPayload) {
    return JSON.stringify(payload) !== JSON.stringify(savedPayload);
}

export function invalidTripFields(editor) {
    const errors = {};
    editor.querySelectorAll('input[name], select[name]').forEach(field => {
        if (field.disabled) return;
        const reason = field.selectedOptions?.[0]?.dataset.eligibilityReason;
        if (!field.validity.valid || reason) errors[field.name.replace(/\[([^\]]+)\]/g, '.$1')] = [reason || field.validationMessage];
    });
    return errors;
}

export function createTripValidation(editor, summary) {
    let current = {};
    const clearMarks = () => {
        editor.querySelectorAll('[aria-invalid="true"]').forEach(field => field.removeAttribute('aria-invalid'));
        editor.querySelectorAll('[data-trip-field-error], [data-trip-section-error]').forEach(node => node.remove());
        editor.querySelectorAll('.trip-section-invalid').forEach(section => section.classList.remove('trip-section-invalid'));
    };
    const render = () => {
        clearMarks();
        summary.replaceChildren();
        const sections = tripErrorSections(current);
        summary.hidden = !Object.keys(current).length;
        for (const [name, message] of Object.entries(sections)) {
            const section = editor.querySelector(`[data-trip-section="${name}"]`);
            const item = document.createElement('div');
            const link = document.createElement('a');
            link.textContent = message;
            link.href = `#trip-section-${name}`;
            link.addEventListener('click', event => {
                event.preventDefault();
                if (section) {
                    expandTripSection(section.querySelector('.card-body'));
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    (section.querySelector('[aria-invalid="true"]') || section.querySelector('[data-card-toggle]'))?.focus();
                }
            });
            item.append(link); summary.append(item);
            if (section) {
                section.classList.add('trip-section-invalid');
                const badge = document.createElement('small');
                badge.dataset.tripSectionError = ''; badge.textContent = 'Needs attention';
                section.querySelector('.card-header h2')?.append(badge);
                const details = Object.entries(current)
                    .filter(([path]) => tripErrorSection(path) === name && !editor.querySelector(`[name="${path.replace(/\.([^.]*)/g, '[$1]')}"]`))
                    .flatMap(([, messages]) => messages)
                    .filter(message => !Object.values(tripSectionMessages).includes(message));
                if (details.length) {
                    const hint = document.createElement('small');
                    hint.dataset.tripFieldError = ''; hint.textContent = [...new Set(details)].join(' ');
                    section.querySelector('.card-body')?.prepend(hint);
                }
            }
        }
        editor.querySelectorAll('input[name], select[name]').forEach(field => {
            const path = field.name.replace(/\[([^\]]+)\]/g, '.$1');
            const root = path.split('.')[0];
            if (!Object.hasOwn(current, path) && !Object.hasOwn(current, root)) return;
            field.setAttribute('aria-invalid', 'true');
            const input = field.closest('.trip-search-select')?.querySelector('[role="combobox"]');
            input?.setAttribute('aria-invalid', 'true');
            const details = [...new Set(current[path] || [])].filter(message => !Object.values(tripSectionMessages).includes(message));
            if (details.length) {
                const hint = document.createElement('small');
                hint.dataset.tripFieldError = ''; hint.textContent = details.join(' ');
                (field.closest('.trip-search-select') || field).after(hint);
            }
        });
        refreshTripSelects(editor);
    };
    return {
        show(errors, focus = true) {
            current = errors; render();
            if (focus) {
                editor.querySelectorAll('.trip-section-invalid .card-body').forEach(expandTripSection);
                summary.focus();
            }
        },
        update(field) {
            if (!Object.keys(current).length) return;
            const name = field.closest('[data-trip-section]')?.dataset.tripSection;
            const local = tripSaveErrors(tripFormPayload(editor), invalidTripFields(editor));
            // Revalidate only the edited section; retain server errors elsewhere.
            current = updateTripSectionErrors(current, name, local);
            render();
        },
    };
}
