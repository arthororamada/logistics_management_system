@extends('layouts.app')
@section('title', 'Upload Waybills')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Waybill', 'url' => route('waybills.index')], ['label' => 'Upload']])

@section('content')
<div id="waybill-upload-notification" class="alert alert-success" role="status" hidden></div>
<section class="page-heading">
    <div><p class="eyebrow">Waybill</p><h2>Mass Upload</h2><p>Import packages and their connected items from a CSV file.</p></div>
    <a href="{{ route('waybills.index') }}" class="button button-outline">Back to Waybills</a>
</section>

<x-card title="Select Client" subtitle="This client is saved as the package client and seller, and supplies the prefix and sequence for generated waybills." class="upload-client-card">
    <div class="upload-client-selector">
        <div class="form-group"><label for="upload-client-search">Client <span class="required">*</span></label><div class="input-control @error('upload_client_id') has-error @enderror"><x-icon name="search" :size="17" /><input id="upload-client-search" list="upload-client-options" value="{{ $selectedUploadClientLabel }}" placeholder="Search client code or name" autocomplete="off" required></div><datalist id="upload-client-options">@foreach($uploadClients as $client)<option value="{{ $client['client_code'] }} - {{ $client['client_name'] }}"></option>@endforeach</datalist><input id="upload_client_id" name="upload_client_id" type="hidden" value="{{ old('upload_client_id') }}" form="waybill-upload-form">@error('upload_client_id')<p class="form-error">{{ $message }}</p>@enderror</div>
        <div id="upload-client-sequence" class="upload-client-sequence" @if(!$selectedUploadClient) hidden @endif><span>Next generated waybill</span><strong>{{ $selectedUploadClient ? ($selectedUploadClient['prefix'] ?: $selectedUploadClient['client_code'].'-').str_pad((string) ($selectedUploadClient['last_value'] + 1), 8, '0', STR_PAD_LEFT) : '' }}</strong><small>CSV waybill values override this sequence.</small></div>
    </div>
</x-card>

<div class="waybill-upload-layout">
    <x-card title="Upload CSV File" subtitle="Waybill number is optional. Blank or omitted values are generated for the selected client.">
        <form id="waybill-upload-form" method="POST" action="{{ route('waybills.import') }}" enctype="multipart/form-data" class="waybill-upload-form" data-native-submit>
            @csrf
            <label id="waybill-drop-zone" class="waybill-drop-zone" for="waybill-upload">
                <span class="waybill-upload-icon"><x-icon name="upload" :size="28" /></span>
                <strong>Drop your CSV file here</strong>
                <span>or click to browse from your computer</span>
                <small>CSV or TXT, maximum file size 50 MB</small>
                <input id="waybill-upload" name="file" type="file" accept=".csv,.txt,text/csv,text/plain" required>
            </label>
            @error('file')<p class="form-error waybill-upload-error">{{ $message }}</p>@enderror

            <div id="waybill-selected-file" class="waybill-selected-file" hidden>
                <span class="waybill-file-icon"><x-icon name="reports" :size="20" /></span>
                <div><strong id="waybill-file-name"></strong><small id="waybill-file-size"></small></div>
                <button id="remove-waybill-file" type="button" class="text-button">Remove</button>
            </div>

            <div class="waybill-upload-actions">
                <p><strong>Ready to validate?</strong><span>No package or item will be saved during validation.</span></p>
                <x-button id="upload-waybills-button" type="submit" variant="primary" icon="search" disabled>Validate</x-button>
            </div>
        </form>
    </x-card>

    <div class="form-stack waybill-upload-help">
        <x-card title="Start with the Template" subtitle="Use the official column structure for a successful import.">
            <div class="template-download-panel">
                <span><x-icon name="reports" :size="24" /></span>
                <div><strong>Waybill CSV Template</strong><small>Includes package, seller, buyer, delivery, and item columns.</small></div>
            </div>
            <a href="{{ route('waybills.template') }}" class="button button-outline waybill-template-button">Download CSV Template</a>
            <button id="open-template-builder" type="button" class="button button-primary waybill-template-button"><x-icon name="plus" :size="16" /> Create a Template</button>
        </x-card>

        <x-card title="Upload Guidelines">
            <ol class="waybill-upload-steps">
                <li><span>1</span><div><strong>Keep the headers unchanged</strong><small>Do not rename, remove, or rearrange template columns.</small></div></li>
                <li><span>2</span><div><strong>Waybill number is optional</strong><small>Leave it blank to use the selected client's prefix and next sequence.</small></div></li>
                <li><span>3</span><div><strong>Use one row per item</strong><small>Repeat the same package ID and package details for additional items.</small></div></li>
                <li><span>4</span><div><strong>Validate and review</strong><small>Check the package preview, then select Proceed Uploading to save it.</small></div></li>
            </ol>
        </x-card>
    </div>
</div>

@if($previewPackages->isNotEmpty())
<x-card title="Validated Packages" subtitle="Review the packages and item counts. After saving, a result CSV with the created waybill numbers downloads automatically." class="waybill-preview-card">
    <div class="waybill-preview-summary">
        <div><strong>{{ $previewPackages->count() }}</strong><span>Packages</span></div>
        <div><strong>{{ $previewPackages->sum('item_count') }}</strong><span>Items</span></div>
        <div><strong>{{ $selectedUploadClientLabel }}</strong><span>Selected client and seller</span></div>
        <form id="proceed-waybill-upload-form" method="POST" action="{{ route('waybills.import.proceed') }}">
            @csrf
            <input type="hidden" name="preview_token" value="{{ $previewToken }}">
            <x-button id="proceed-waybill-upload-button" type="submit" variant="primary" icon="upload">Proceed Uploading</x-button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="access-table waybill-preview-table">
            <thead><tr><th>Waybill Number</th><th>Source</th><th>Package ID</th><th>Order Number</th><th>Buyer</th><th>City</th><th>Items</th><th>Total Price</th></tr></thead>
            <tbody>
                @foreach($previewPackages as $package)
                    <tr>
                        <td><strong>{{ $package['waybill_number'] }}</strong></td>
                        <td><span class="waybill-preview-source {{ strtolower($package['waybill_source']) }}">{{ $package['waybill_source'] }}</span></td>
                        <td>{{ $package['package_id'] }}</td>
                        <td>{{ $package['order_number'] }}</td>
                        <td>{{ $package['buyer_name'] }}</td>
                        <td>{{ $package['buyer_city'] }}</td>
                        <td>{{ $package['item_count'] }}</td>
                        <td>{{ number_format((float) $package['total_price'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
@endif

<dialog id="template-builder-modal" class="template-builder-modal">
    <form method="POST" action="{{ route('waybills.custom-template') }}" class="template-builder-form" data-native-submit>
        @csrf
        <header class="template-builder-header"><div><p class="eyebrow">Custom CSV</p><h2>Create a Template</h2><span>Select the package and item fields to include as CSV columns.</span></div><button id="close-template-builder" type="button" class="icon-button" aria-label="Close template builder"><x-icon name="close" :size="18" /></button></header>
        <div class="template-builder-toolbar"><strong><span id="selected-template-fields">{{ count($requiredTemplateColumns) }}</span> fields selected</strong><div><button id="select-all-template-fields" type="button" class="text-button">Select all</button><button id="clear-template-fields" type="button" class="text-button">Clear all</button></div></div>
        <div class="template-field-grid">
            @foreach($templateColumns as $column)
                <label class="template-field-option"><input type="checkbox" name="fields[]" value="{{ $column }}" @checked(in_array($column, $requiredTemplateColumns, true))><span><strong>{{ ucwords(str_replace('_', ' ', $column)) }}</strong><small>{{ $column }}</small></span></label>
            @endforeach
        </div>
        @error('fields')<p class="form-error template-builder-error">{{ $message }}</p>@enderror
        @error('fields.*')<p class="form-error template-builder-error">{{ $message }}</p>@enderror
        <footer class="template-builder-footer"><button id="cancel-template-builder" type="button" class="button button-outline">Cancel</button><button id="download-custom-template" type="submit" class="button button-primary"><x-icon name="reports" :size="16" /> Download Template</button></footer>
    </form>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('#waybill-upload');
    const form = document.querySelector('#waybill-upload-form');
    const dropZone = document.querySelector('#waybill-drop-zone');
    const selected = document.querySelector('#waybill-selected-file');
    const name = document.querySelector('#waybill-file-name');
    const size = document.querySelector('#waybill-file-size');
    const remove = document.querySelector('#remove-waybill-file');
    const submit = document.querySelector('#upload-waybills-button');
    const modal = document.querySelector('#template-builder-modal');
    const templateForm = modal.querySelector('.template-builder-form');
    const templateCheckboxes = [...modal.querySelectorAll('input[type="checkbox"]')];
    const selectedCount = document.querySelector('#selected-template-fields');
    const downloadTemplate = document.querySelector('#download-custom-template');
    const clients = @json($uploadClients);
    const clientSearch = document.querySelector('#upload-client-search');
    const clientId = document.querySelector('#upload_client_id');
    const clientSequence = document.querySelector('#upload-client-sequence');
    const notification = document.querySelector('#waybill-upload-notification');
    const proceedForm = document.querySelector('#proceed-waybill-upload-form');
    const proceedButton = document.querySelector('#proceed-waybill-upload-button');

    const showNotification = (message, type = 'success') => {
        notification.textContent = message;
        notification.classList.toggle('alert-success', type === 'success');
        notification.classList.toggle('alert-error', type === 'error');
        notification.hidden = false;
        notification.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const showFile = (file) => {
        if (!file) { selected.hidden = true; submit.disabled = true; return; }
        name.textContent = file.name;
        size.textContent = file.size >= 1024 * 1024
            ? `${(file.size / 1024 / 1024).toFixed(1)} MB`
            : `${(file.size / 1024).toFixed(1)} KB`;
        selected.hidden = false;
        submit.disabled = false;
    };

    const selectUploadClient = () => {
        const match = clients.find((client) => `${client.client_code} - ${client.client_name}`.toLocaleLowerCase() === clientSearch.value.trim().toLocaleLowerCase());
        clientId.value = match?.id ?? '';
        clientSearch.setCustomValidity(match ? '' : 'Select a client from the list.');
        clientSequence.hidden = !match;
        if (match) clientSequence.querySelector('strong').textContent = `${match.prefix || `${match.client_code}-`}${String(Number(match.last_value) + 1).padStart(8, '0')}`;
    };

    input.addEventListener('change', () => showFile(input.files[0]));
    clientSearch.addEventListener('input', selectUploadClient);
    ['dragenter', 'dragover'].forEach((eventName) => dropZone.addEventListener(eventName, (event) => { event.preventDefault(); dropZone.classList.add('is-dragging'); }));
    ['dragleave', 'drop'].forEach((eventName) => dropZone.addEventListener(eventName, (event) => { event.preventDefault(); dropZone.classList.remove('is-dragging'); }));
    dropZone.addEventListener('drop', (event) => {
        const file = event.dataTransfer.files[0];
        if (!file) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        showFile(file);
    });
    remove.addEventListener('click', () => { input.value = ''; showFile(null); });
    form.addEventListener('submit', (event) => { selectUploadClient(); if (!clientId.value) { event.preventDefault(); clientSearch.reportValidity(); return; } submit.disabled = true; submit.textContent = 'Validating...'; });
    proceedForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        proceedButton.disabled = true;
        proceedButton.textContent = 'Uploading...';

        try {
            const response = await fetch(proceedForm.action, {
                method: 'POST',
                body: new FormData(proceedForm),
                headers: { Accept: 'application/json, text/csv', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                const validationMessage = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                throw new Error(validationMessage || payload.message || 'The upload could not be completed.');
            }

            const blob = await response.blob();
            const disposition = response.headers.get('Content-Disposition') || '';
            const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1] || 'uploaded-waybills.csv';
            const downloadUrl = URL.createObjectURL(blob);
            const download = document.createElement('a');
            download.href = downloadUrl;
            download.download = filename;
            document.body.appendChild(download);
            download.click();
            download.remove();
            window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);

            form.reset();
            input.value = '';
            clientSearch.value = '';
            clientSearch.setCustomValidity('');
            clientId.value = '';
            clientSequence.hidden = true;
            clientSequence.querySelector('strong').textContent = '';
            showFile(null);
            document.querySelector('.waybill-preview-card')?.remove();
            showNotification(response.headers.get('X-Upload-Message') || 'Uploading successful. The result CSV was downloaded.');
        } catch (error) {
            proceedButton.disabled = false;
            proceedButton.textContent = 'Proceed Uploading';
            showNotification(error.message, 'error');
        }
    });

    const updateTemplateSelection = () => {
        const count = templateCheckboxes.filter((checkbox) => checkbox.checked).length;
        selectedCount.textContent = count;
        downloadTemplate.disabled = count === 0;
    };
    document.querySelector('#open-template-builder').addEventListener('click', () => modal.showModal());
    document.querySelector('#close-template-builder').addEventListener('click', () => modal.close());
    document.querySelector('#cancel-template-builder').addEventListener('click', () => modal.close());
    document.querySelector('#select-all-template-fields').addEventListener('click', () => { templateCheckboxes.forEach((checkbox) => { checkbox.checked = true; }); updateTemplateSelection(); });
    document.querySelector('#clear-template-fields').addEventListener('click', () => { templateCheckboxes.forEach((checkbox) => { checkbox.checked = false; }); updateTemplateSelection(); });
    templateCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateTemplateSelection));
    templateForm.addEventListener('submit', () => modal.close());
    modal.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });
    updateTemplateSelection();
    if (clientSearch.value) selectUploadClient();
    @if($errors->has('fields') || $errors->has('fields.*')) modal.showModal(); @endif
});
</script>
@endsection
