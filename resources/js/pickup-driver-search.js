document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-driver-picker]').forEach(picker => {
        picker.closest('.content-card')?.classList.add('has-searchable-picker');
        const search = picker.querySelector('[data-driver-search]');
        const select = picker.querySelector('select');
        const trigger = picker.querySelector('.driver-picker-trigger');
        const panel = picker.querySelector('.driver-picker-panel');
        const options = picker.querySelector('[role="listbox"]');
        const value = picker.querySelector('[data-picker-value], #driver-picker-value');
        const status = picker.querySelector('[role="status"]');
        const label = picker.dataset.pickerLabel ?? 'driver';
        const resultKey = picker.dataset.resultKey ?? 'drivers';
        let latestRequest = 0;
        const syncValue = () => { value.textContent = select.value ? select.selectedOptions[0].textContent : `Select a ${label}`; };
        const close = (restoreFocus = false) => {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            search.setAttribute('aria-expanded', 'false');
            if (restoreFocus) trigger.focus();
        };
        const open = () => {
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            search.setAttribute('aria-expanded', 'true');
            search.focus();
        };
        const render = (drivers) => {
            options.replaceChildren();
            drivers.forEach(driver => {
                const option = document.createElement('button');
                option.type = 'button';
                option.setAttribute('role', 'option');
                option.textContent = driver.label;
                option.setAttribute('aria-selected', String(select.value === String(driver.id)));
                option.addEventListener('click', () => {
                    if (![...select.options].some(item => item.value === String(driver.id))) select.add(new Option(driver.label, String(driver.id)));
                    select.value = String(driver.id);
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncValue();
                    options.querySelectorAll('[role="option"]').forEach(item => item.setAttribute('aria-selected', String(item === option)));
                    close(true);
                });
                options.append(option);
            });
        };
        render([...select.options].filter(option => option.value).map(option => ({ id: option.value, label: option.textContent })));
        syncValue();
        select.hidden = true;
        trigger.hidden = false;
        document.getElementById(trigger.getAttribute('aria-labelledby').split(' ')[0]).setAttribute('for', trigger.id);
        trigger.addEventListener('click', () => panel.hidden ? open() : close());
        select.addEventListener('invalid', event => {
            event.preventDefault();
            open();
            status.textContent = `Select a ${label} before creating the document manifest.`;
        });
        document.addEventListener('click', event => { if (!picker.contains(event.target)) close(); });
        picker.addEventListener('focusout', event => { if (!picker.contains(event.relatedTarget)) close(); });
        picker.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(true); }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (panel.hidden) open();
                const items = [...options.querySelectorAll('[role="option"]')];
                const index = items.indexOf(document.activeElement);
                const next = event.key === 'ArrowDown' ? index + 1 : (index < 0 ? items.length - 1 : index - 1);
                if (next < 0) search.focus();
                else items[Math.min(next, items.length - 1)]?.focus();
            }
            if (event.key === 'Enter' && event.target === search) {
                event.preventDefault();
                options.querySelector('[role="option"]')?.click();
            }
        });
        search.addEventListener('keyup', async event => {
            const request = ++latestRequest;
            status.textContent = `Searching ${label}s...`;
            options.setAttribute('aria-busy', 'true');
            // Keep the current options available for keyboard navigation during a lookup.
            try {
                const url = new URL(search.dataset.searchUrl, window.location.origin);
                url.searchParams.set('search', search.value);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Driver search failed');
                const payload = await response.json();
                const drivers = payload[resultKey];
                if (request !== latestRequest) return;
                if (document.activeElement === search || !picker.contains(document.activeElement)) render(drivers);
                status.textContent = drivers.length ? `${drivers.length} matching ${label}(s).` : `No matching active ${label}s found.`;
            } catch {
                if (request === latestRequest) status.textContent = `Unable to search ${label}s. Type again to retry.`;
            } finally {
                if (request === latestRequest) options.removeAttribute('aria-busy');
            }
        });
    });
});
