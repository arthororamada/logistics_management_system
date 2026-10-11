<x-card title="Maintenance Policy Information" subtitle="Vehicle classification, trigger policy, warning point, and status." class="narrow-form-card">
    <div class="form-stack">
        <div class="form-group">
            <label for="maintenance_code">Maintenance code <span class="required">*</span></label>
            <div class="input-control @error('maintenance_code') has-error @enderror"><x-icon name="maintenance" :size="18" /><input id="maintenance_code" name="maintenance_code" value="{{ old('maintenance_code', $vehicleMaintenance->maintenance_code) }}" maxlength="30" required placeholder="e.g. OIL_CHANGE" style="text-transform: uppercase"></div>
            <p class="field-hint">Letters, numbers, underscores, and hyphens only.</p>
            @error('maintenance_code')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="maintenance_description">Maintenance description <span class="required">*</span></label>
            <div class="input-control @error('maintenance_description') has-error @enderror"><x-icon name="maintenance" :size="18" /><input id="maintenance_description" name="maintenance_description" value="{{ old('maintenance_description', $vehicleMaintenance->maintenance_description) }}" maxlength="150" required placeholder="e.g. Engine oil and filter change"></div>
            @error('maintenance_description')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="vehicle_group">Vehicle group <span class="required">*</span></label>
            <select id="vehicle_group" name="vehicle_group" class="form-select @error('vehicle_group') has-error @enderror" required><option value="">Select vehicle group</option>@foreach($vehicleGroups as $group)<option value="{{ $group->vehicle_group_description }}" @selected(old('vehicle_group', $vehicleMaintenance->vehicle_group) === $group->vehicle_group_description)>{{ $group->vehicle_group_description }}</option>@endforeach</select>
            @error('vehicle_group')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="vehicle_type">Vehicle type <span class="required">*</span></label>
            <select id="vehicle_type" name="vehicle_type" class="form-select @error('vehicle_type') has-error @enderror" required><option value="">Select vehicle type</option>@foreach($vehicleTypes as $type)<option value="{{ $type->vehicle_type }}" @selected(old('vehicle_type', $vehicleMaintenance->vehicle_type) === $type->vehicle_type)>{{ $type->vehicle_type }}</option>@endforeach</select>
            @error('vehicle_type')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="maintenance_type">Maintenance type <span class="required">*</span></label>
            <select id="maintenance_type" name="maintenance_type" class="form-select @error('maintenance_type') has-error @enderror" required><option value="">Select policy type</option><option value="distance_policy" @selected(old('maintenance_type', $vehicleMaintenance->maintenance_type) === 'distance_policy')>Distance policy (kilometers)</option><option value="date_policy" @selected(old('maintenance_type', $vehicleMaintenance->maintenance_type) === 'date_policy')>Date policy (days)</option></select>
            @error('maintenance_type')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="threshold">Threshold <span class="required">*</span></label>
            <div class="input-control @error('threshold') has-error @enderror"><input id="threshold" name="threshold" type="number" min="1" step="1" value="{{ old('threshold', $vehicleMaintenance->threshold) }}" required placeholder="e.g. 10000"></div>
            <p class="field-hint">Kilometers for a distance policy; days for a date policy.</p>
            @error('threshold')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="threshold_warning">Threshold warning <span class="required">*</span></label>
            <div class="input-control @error('threshold_warning') has-error @enderror"><input id="threshold_warning" name="threshold_warning" type="number" min="0" step="1" value="{{ old('threshold_warning', $vehicleMaintenance->threshold_warning) }}" required placeholder="e.g. 1000"></div>
            <p class="field-hint">Advance warning interval; it cannot exceed the threshold.</p>
            @error('threshold_warning')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <label class="account-status-control"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $vehicleMaintenance->exists ? $vehicleMaintenance->is_active : true))><span class="status-switch"></span><span><strong>Active maintenance policy</strong><small>Inactive policies remain on record but are unavailable for scheduling.</small></span></label>
    </div>
</x-card>
<div class="form-actions narrow-form-actions"><a href="{{ route('vehicle-maintenances.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
