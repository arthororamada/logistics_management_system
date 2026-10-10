import './bootstrap';
import './trips';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-term-department]').forEach((departmentSelect) => {
        const campusSelect = document.querySelector('[data-term-campus]');
        if (!campusSelect) return;

        const syncTermCampuses = () => {
            const department = departmentSelect.options[departmentSelect.selectedIndex];
            const allowed = new Set((department?.dataset.campusIds ?? '').split(',').filter(Boolean));
            [...campusSelect.options].forEach((option, index) => {
                if (index === 0) return;
                const available = allowed.has(option.value);
                option.disabled = !available;
                option.hidden = !available;
                if (!available && option.selected) campusSelect.value = '';
            });
        };

        departmentSelect.addEventListener('change', syncTermCampuses);
        syncTermCampuses();
    });

    document.querySelectorAll('[data-department-select]').forEach((select) => {
        const campusOptions = [...document.querySelectorAll('[data-program-campus]')];
        campusOptions.forEach((option) => {
            const state = option.querySelector('[data-campus-state]');
            if (state) state.dataset.originalState = state.textContent.trim();
        });

        const syncDepartmentCampuses = () => {
            const selected = select.options[select.selectedIndex];
            const allowed = new Set((selected?.dataset.campusIds ?? '').split(',').filter(Boolean));
            campusOptions.forEach((option) => {
                const input = option.querySelector('input[type="checkbox"]');
                const state = option.querySelector('[data-campus-state]');
                const available = allowed.has(option.dataset.campusId);
                option.classList.toggle('is-unavailable', !available);
                if (input) {
                    input.disabled = !available;
                    if (!available) input.checked = false;
                }
                if (state) state.textContent = available ? state.dataset.originalState : 'Not available for department';
            });
        };

        select.addEventListener('change', syncDepartmentCampuses);
        syncDepartmentCampuses();
    });

    document.querySelectorAll('[data-color-control]').forEach((control) => {
        const picker = control.querySelector('[data-color-picker]');
        const value = control.querySelector('[data-color-value]');
        const preview = control.querySelector('[data-color-preview]');
        if (!picker || !value || !preview) return;

        const applyColor = (color) => {
            const normalized = color.toUpperCase();
            if (/^#[0-9A-F]{6}$/.test(normalized)) {
                picker.value = normalized;
                value.value = normalized;
                preview.style.setProperty('--selected-color', normalized);
            }
        };

        picker.addEventListener('input', () => applyColor(picker.value));
        value.addEventListener('input', () => applyColor(value.value));
        applyColor(value.value);
    });

    document.querySelectorAll('[data-collapsible-card]').forEach((card) => {
        const button = card.querySelector('[data-card-toggle]');
        const bodyId = button?.getAttribute('aria-controls');
        const body = bodyId ? document.getElementById(bodyId) : null;
        if (!button || !body) return;

        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            button.setAttribute('aria-label', `${expanded ? 'Expand' : 'Collapse'} ${card.querySelector('.card-header h2')?.textContent?.trim() ?? 'card'}`);
            body.hidden = expanded;
            card.classList.toggle('is-collapsed', expanded);
        });
    });

    document.querySelectorAll('[data-permission-group]').forEach((group) => {
        const selectAll = group.querySelector('[data-select-group]');
        const permissions = [...group.querySelectorAll('input[name="permissions[]"]')];
        const syncSelectAll = () => {
            if (!selectAll) return;
            selectAll.checked = permissions.length > 0 && permissions.every((input) => input.checked);
            selectAll.indeterminate = permissions.some((input) => input.checked) && !selectAll.checked;
        };
        selectAll?.addEventListener('change', () => permissions.forEach((input) => { input.checked = selectAll.checked; }));
        permissions.forEach((input) => input.addEventListener('change', syncSelectAll));
        syncSelectAll();
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.closest('.input-control')?.querySelector('input');
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.textContent = showing ? 'Show' : 'Hide';
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            button.setAttribute('aria-pressed', String(!showing));
        });
    });

    const shell = document.querySelector('[data-app-shell]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const close = document.querySelector('[data-sidebar-close]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const mobile = () => window.matchMedia('(max-width: 768px)').matches;
    const tablet = () => window.matchMedia('(min-width: 769px) and (max-width: 1024px)').matches;

    if (shell && localStorage.getItem('fleet-sidebar-collapsed') === 'true' && !mobile() && !tablet()) {
        shell.classList.add('sidebar-collapsed');
        toggle?.setAttribute('aria-expanded', 'false');
    }

    const closeSidebar = () => {
        shell?.classList.remove('sidebar-open', 'sidebar-expanded');
        toggle?.setAttribute('aria-expanded', 'false');
    };

    toggle?.addEventListener('click', () => {
        if (mobile()) {
            const isOpen = shell.classList.toggle('sidebar-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
        } else if (tablet()) {
            const isExpanded = shell.classList.toggle('sidebar-expanded');
            toggle.setAttribute('aria-expanded', String(isExpanded));
        } else {
            const isCollapsed = shell.classList.toggle('sidebar-collapsed');
            localStorage.setItem('fleet-sidebar-collapsed', String(isCollapsed));
            toggle.setAttribute('aria-expanded', String(!isCollapsed));
        }
    });

    close?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);
    window.addEventListener('resize', () => {
        if (!mobile()) shell?.classList.remove('sidebar-open');
        if (!tablet()) shell?.classList.remove('sidebar-expanded');
    });

    const dropdowns = [...document.querySelectorAll('[data-dropdown]')];
    const closeDropdowns = (except = null) => dropdowns.forEach((dropdown) => {
        if (dropdown !== except) {
            dropdown.classList.remove('is-open');
            dropdown.querySelector('[data-dropdown-toggle]')?.setAttribute('aria-expanded', 'false');
        }
    });

    dropdowns.forEach((dropdown) => {
        const button = dropdown.querySelector('[data-dropdown-toggle]');
        button?.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = !dropdown.classList.contains('is-open');
            closeDropdowns(dropdown);
            dropdown.classList.toggle('is-open', willOpen);
            button.setAttribute('aria-expanded', String(willOpen));
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-dropdown]')) closeDropdowns();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { closeDropdowns(); closeSidebar(); }
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            document.querySelector('.topbar-search input')?.focus();
        }
    });
});
