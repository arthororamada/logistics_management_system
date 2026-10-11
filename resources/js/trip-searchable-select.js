const instances = new WeakMap();
let sequence = 0;

export function matchingSelectOptions(options, query) {
    const needle = query.trim().toLocaleLowerCase();
    const seen = new Set();
    return [...options].filter((option) => {
        if (option.disabled || option.hidden || seen.has(option.value)) return false;
        seen.add(option.value);
        return option.textContent.toLocaleLowerCase().includes(needle);
    });
}

export function expandTripSection(element) {
    const button = element?.closest('[data-trip-section]')?.querySelector('[data-card-toggle]');
    if (button?.getAttribute('aria-expanded') === 'false') button.click();
}

function accessibleLabel(select) {
    if (select.getAttribute('aria-label')) return select.getAttribute('aria-label');
    const label = select.id ? document.querySelector(`label[for="${CSS.escape(select.id)}"]`) : select.closest('label');
    if (label) {
        const copy = label.cloneNode(true);
        copy.querySelectorAll('select, input, small').forEach((element) => element.remove());
        return copy.textContent.trim();
    }
    return select.name || 'Select option';
}

function enhance(select) {
    if (instances.has(select)) return;
    const id = `trip-search-select-${++sequence}`;
    const wrapper = document.createElement('div');
    wrapper.className = 'trip-search-select';
    const input = document.createElement('input');
    input.type = 'text'; input.className = 'form-input'; input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-haspopup', 'listbox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', `${id}-options`);
    input.setAttribute('aria-label', accessibleLabel(select));
    const panel = document.createElement('div');
    panel.className = 'trip-select-panel'; panel.hidden = true;
    const list = document.createElement('ul');
    list.id = `${id}-options`; list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', input.getAttribute('aria-label'));
    const empty = document.createElement('p');
    empty.className = 'trip-select-empty'; empty.textContent = 'No matching options.';
    empty.setAttribute('role', 'status'); empty.hidden = true;
    panel.append(list, empty);
    select.before(wrapper); wrapper.append(select, input, panel);
    select.classList.add('trip-native-select'); select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    let active = -1;
    let matches = [];
    let query = '';
    const selectedLabel = () => select.selectedOptions[0]?.textContent.trim() || 'Select option';
    const highlight = (index) => {
        active = index;
        [...list.children].forEach((option, position) => { option.classList.toggle('is-highlighted', position === active); });
        if (active >= 0 && list.children[active]) {
            input.setAttribute('aria-activedescendant', list.children[active].id);
            list.children[active].scrollIntoView({ block: 'nearest' });
        } else input.removeAttribute('aria-activedescendant');
    };
    const close = () => {
        panel.hidden = true; wrapper.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant');
        input.value = selectedLabel(); query = ''; active = -1;
    };
    const choose = (option) => {
        select.value = option.value;
        input.setCustomValidity('');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close(); input.focus(); input.select();
    };
    const render = () => {
        matches = matchingSelectOptions(select.options, query);
        list.replaceChildren();
        matches.forEach((option, index) => {
            const item = document.createElement('li');
            item.id = `${id}-option-${index}`; item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.selected));
            item.textContent = option.textContent;
            item.addEventListener('pointerdown', (event) => { event.preventDefault(); choose(option); });
            list.append(item);
        });
        empty.hidden = matches.length !== 0;
        const selected = matches.findIndex((option) => option.selected);
        highlight(selected >= 0 ? selected : (matches.length ? 0 : -1));
    };
    const open = (search = '') => {
        if (select.disabled) return;
        query = search; input.value = search; input.placeholder = selectedLabel();
        panel.hidden = false; wrapper.classList.add('is-open'); input.setAttribute('aria-expanded', 'true');
        render();
    };
    const refresh = () => {
        input.disabled = select.disabled;
        if (select.disabled || select.validity.valid) input.setCustomValidity('');
        if (panel.hidden) input.value = selectedLabel();
        else render();
    };
    instances.set(select, { close, refresh });
    input.addEventListener('focus', () => open());
    input.addEventListener('click', () => { if (panel.hidden) open(); });
    input.addEventListener('input', () => open(input.value));
    input.addEventListener('blur', close);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (panel.hidden) open();
            if (matches.length) highlight((active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length);
        } else if (event.key === 'Enter' && !panel.hidden) {
            event.preventDefault(); if (matches[active]) choose(matches[active]);
        } else if (event.key === 'Escape') { event.preventDefault(); close(); }
        else if (event.key === 'Tab') close();
    });
    select.addEventListener('change', () => { input.setCustomValidity(''); refresh(); });
    select.addEventListener('focus', () => input.focus());
    select.addEventListener('invalid', (event) => {
        event.preventDefault(); expandTripSection(select);
        input.setCustomValidity(select.validationMessage); input.focus(); input.reportValidity();
    });
    new MutationObserver(refresh).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'selected', 'hidden'] });
    refresh();
}

export function enhanceTripSelects(root = document) {
    root.querySelectorAll('select[data-trip-searchable]').forEach(enhance);
}

export function refreshTripSelects(root = document) {
    root.querySelectorAll('select[data-trip-searchable]').forEach((select) => instances.get(select)?.refresh());
}

if (typeof document !== 'undefined') document.addEventListener('pointerdown', (event) => {
    document.querySelectorAll('.trip-search-select.is-open').forEach((wrapper) => {
        if (!wrapper.contains(event.target)) instances.get(wrapper.querySelector('select'))?.close();
    });
});
