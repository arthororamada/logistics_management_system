@extends('layouts.app')
@section('title', 'Waybills')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Waybill'], ['label' => 'List']])

@section('content')
<div class="form-stack waybill-list-stack">
    <x-card title="Waybill Advanced Search" subtitle="Use one or more fields below. No records load until you search.">
        <form method="GET" action="{{ route('waybills.index') }}" class="advanced-waybill-form">
            <div class="advanced-waybill-grid">
                <div class="form-group"><select id="creator_ids" name="creator_ids[]" multiple data-searchable-multiple data-placeholder="Select creator" data-search-placeholder="Search creators" aria-label="Creator">@foreach($creators as $creator)<option value="{{ $creator->id }}" @selected(in_array($creator->id, $filters['creator_ids'] ?? []))>{{ $creator->name }} ({{ $creator->username }})</option>@endforeach</select></div>
                <div class="form-group"><select id="client_ids" name="client_ids[]" multiple data-searchable-multiple data-placeholder="Select client" data-search-placeholder="Search clients" aria-label="Client">@foreach($clients as $client)<option value="{{ $client->id }}" @selected(in_array($client->id, $filters['client_ids'] ?? []))>{{ $client->client_code }} - {{ $client->client_name }}</option>@endforeach</select></div>
                <div class="form-group"><select id="priorities" name="priorities[]" multiple data-searchable-multiple data-placeholder="Select priority" data-search-placeholder="Search priorities" aria-label="Priority"><option value="yes" @selected(in_array('yes', $filters['priorities'] ?? []))>Priority</option><option value="no" @selected(in_array('no', $filters['priorities'] ?? []))>Standard</option></select></div>

                <div class="form-group"><select id="package_statuses" name="package_statuses[]" multiple data-searchable-multiple data-placeholder="Select status" data-search-placeholder="Search statuses" aria-label="Status"><option value="for_pickup" @selected(in_array('for_pickup', $filters['package_statuses'] ?? []))>For Pickup</option><option value="in_transit" @selected(in_array('in_transit', $filters['package_statuses'] ?? []))>In Transit</option><option value="delivered" @selected(in_array('delivered', $filters['package_statuses'] ?? []))>Delivered</option><option value="cancelled" @selected(in_array('cancelled', $filters['package_statuses'] ?? []))>Cancelled</option></select></div>
                <div class="form-group"><input id="package_location" name="package_location" value="{{ $filters['package_location'] ?? '' }}" placeholder="Current location" aria-label="Current location"></div>
                <div class="form-group"><input id="payment_type" name="payment_type" value="{{ $filters['payment_type'] ?? '' }}" placeholder="Payment type" aria-label="Payment type"></div>

                <div class="form-group"><select id="date_reference" name="date_reference" aria-label="Date reference"><option value="created_at" @selected($dateReference === 'created_at')>Date Created</option><option value="updated_at" @selected($dateReference === 'updated_at')>Date Updated</option><option value="handover_date" @selected($dateReference === 'handover_date')>Handover Date</option></select></div>
                <div class="form-group advanced-date-range"><input id="date_range" type="text" name="date_range" value="{{ $filters['date_range'] ?? '' }}" placeholder="Start date                 ~                 End date" aria-label="Date range" autocomplete="off">@error('date_range')<p class="form-error">{{ $message }}</p>@enderror @error('date_from')<p class="form-error">{{ $message }}</p>@enderror @error('date_to')<p class="form-error">{{ $message }}</p>@enderror</div>
            </div>

            <div class="advanced-waybill-multivalue">
                <div class="form-group"><textarea id="tracking_numbers" name="tracking_numbers" rows="2" placeholder="Tracking number(s)" aria-label="Tracking numbers">{{ $filters['tracking_numbers'] ?? '' }}</textarea></div>
                <div class="form-group"><textarea id="package_ids" name="package_ids" rows="2" placeholder="Package ID(s)" aria-label="Package IDs">{{ $filters['package_ids'] ?? '' }}</textarea></div>
                <div class="form-group"><textarea id="references" name="references" rows="2" placeholder="Reference(s)" aria-label="References">{{ $filters['references'] ?? '' }}</textarea></div>
                <div class="form-group"><textarea id="order_numbers" name="order_numbers" rows="2" placeholder="Order number(s)" aria-label="Order numbers">{{ $filters['order_numbers'] ?? '' }}</textarea></div>
            </div>

            <div class="advanced-search-actions"><button id="clear-waybill-filters" type="button" class="button button-outline">Clear</button><button type="submit" class="button button-primary"><x-icon name="search" :size="16" /> Search</button></div>
        </form>
    </x-card>

    <x-card title="Waybill Results" subtitle="Results appear only after applying a search filter.">
        <x-slot:actions><button id="export-waybill-results" type="button" class="button button-outline" @disabled($packages->isEmpty())><x-icon name="reports" :size="16" /> Export CSV</button></x-slot:actions>
        <div class="waybill-results-toolbar"><strong>Total: {{ number_format($packages->total()) }}</strong><span>{{ $hasFilters ? 'Filtered waybill results' : 'Ready for search' }}</span></div>
        <div class="table-wrap"><table id="waybill-results-table" class="access-table waybill-table"><thead><tr><th>Client</th><th>Created Date</th><th>Handover Date</th><th>Waybill Number</th><th>Consignee</th><th>City</th><th>Status</th><th>Reference</th><th>Priority</th><th class="align-right" data-export-ignore>Options</th></tr></thead><tbody>
            @forelse($packages as $package)
                <tr><td>{{ $package->client?->client_name ?: '-' }}</td><td><span class="date-label">{{ $package->created_at?->format('M d, Y H:i') ?: '-' }}</span></td><td><span class="date-label">{{ $package->handover_date?->format('M d, Y H:i') ?: '-' }}</span></td><td><strong>{{ $package->waybill_number }}</strong><small>{{ $package->package_id }}</small></td><td><strong>{{ $package->buyer_name }}</strong><small>{{ $package->buyer_mobile }}</small></td><td>{{ $package->buyer_city ?: '-' }}</td><td><x-status-badge :status="$package->package_status" /></td><td>{{ $package->package_reference_1 ?: '-' }}</td><td>@if($package->is_priority)<span class="status-badge status-active">Priority</span>@else<span class="date-label">Standard</span>@endif</td><td class="align-right" data-export-ignore><a class="table-action" href="{{ route('waybills.show', ['package' => $package] + request()->query()) }}">View</a></td></tr>
            @empty
                <tr><td colspan="10" class="empty-table">{{ $hasFilters ? 'No waybills match the selected filters.' : 'No rows loaded. Use Advanced Search above.' }}</td></tr>
            @endforelse
        </tbody></table></div>
        @if($hasFilters)<div class="pagination-links">{{ $packages->links('vendor.pagination.waybill') }}</div>@endif
    </x-card>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rangePicker = typeof window.flatpickr === 'function'
        ? window.flatpickr('#date_range', { mode: 'range', dateFormat: 'Y-m-d', conjunction: ' to ', allowInput: true })
        : null;

    const filterForm = document.querySelector('.advanced-waybill-form');
    const multiSelects = [];
    document.querySelectorAll('select[data-searchable-multiple]').forEach((select) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-multi-select';
        select.before(wrapper);
        wrapper.appendChild(select);
        select.classList.add('searchable-multi-native');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'searchable-multi-trigger';
        trigger.setAttribute('aria-expanded', 'false');
        const triggerText = document.createElement('span');
        triggerText.className = 'searchable-multi-values';
        const triggerArrow = document.createElement('span');
        triggerArrow.className = 'searchable-multi-arrow';
        triggerArrow.textContent = '⌄';
        trigger.append(triggerText, triggerArrow);

        const menu = document.createElement('div');
        menu.className = 'searchable-multi-menu';
        menu.hidden = true;
        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'searchable-multi-search';
        search.placeholder = select.dataset.searchPlaceholder || 'Search options';
        search.setAttribute('aria-label', search.placeholder);
        const options = document.createElement('div');
        options.className = 'searchable-multi-options';
        const optionRows = [...select.options].map((option) => {
            const label = document.createElement('label');
            label.className = 'searchable-multi-option';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = option.selected;
            const text = document.createElement('span');
            text.textContent = option.text;
            label.append(checkbox, text);
            checkbox.addEventListener('change', () => {
                option.selected = checkbox.checked;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            options.appendChild(label);
            return { label, text: option.text.toLocaleLowerCase(), checkbox };
        });
        menu.append(search, options);
        wrapper.append(trigger, menu);

        const render = () => {
            const selectedOptions = [...select.selectedOptions];
            triggerText.replaceChildren();
            if (selectedOptions.length === 0) {
                triggerText.textContent = select.dataset.placeholder;
            } else {
                selectedOptions.forEach((option) => {
                    const value = document.createElement('span');
                    value.className = 'searchable-multi-value';
                    value.textContent = option.text;
                    triggerText.appendChild(value);
                });
            }
            trigger.classList.toggle('has-selection', selectedOptions.length > 0);
            optionRows.forEach(({ checkbox }, index) => { checkbox.checked = select.options[index].selected; });
        };
        const close = () => { menu.hidden = true; trigger.setAttribute('aria-expanded', 'false'); wrapper.classList.remove('is-open'); };
        trigger.addEventListener('click', () => {
            const opening = menu.hidden;
            multiSelects.forEach((control) => control.close());
            if (opening) { menu.hidden = false; trigger.setAttribute('aria-expanded', 'true'); wrapper.classList.add('is-open'); search.focus(); }
        });
        search.addEventListener('input', () => {
            const term = search.value.trim().toLocaleLowerCase();
            optionRows.forEach((row) => { row.label.hidden = !row.text.includes(term); });
        });
        select.addEventListener('change', render);
        multiSelects.push({ wrapper, close });
        render();
    });
    document.addEventListener('click', (event) => multiSelects.forEach((control) => {
        if (!control.wrapper.contains(event.target)) control.close();
    }));

    document.querySelector('#clear-waybill-filters')?.addEventListener('click', () => {
        filterForm.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => { field.value = ''; });
        filterForm.querySelectorAll('select').forEach((field) => {
            if (field.multiple) [...field.options].forEach((option) => { option.selected = false; });
            else field.selectedIndex = 0;
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        rangePicker?.clear();
    });

    document.querySelector('#export-waybill-results')?.addEventListener('click', () => {
        const rows = [...document.querySelectorAll('#waybill-results-table tr')].map((row) =>
            [...row.querySelectorAll('th:not([data-export-ignore]), td:not([data-export-ignore])')].map((cell) => `"${cell.innerText.trim().replaceAll('"', '""')}"`).join(',')
        );
        const blob = new Blob([`\uFEFF${rows.join('\r\n')}`], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = Object.assign(document.createElement('a'), { href: url, download: `waybill-results-${new Date().toISOString().slice(0, 10)}.csv` });
        link.click();
        URL.revokeObjectURL(url);
    });
});
</script>
@endsection
