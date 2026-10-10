<x-card title="Vehicle Group Information" subtitle="Code, description, and operational status." class="narrow-form-card">
    <div class="form-stack">
        <div class="form-group">
            <label for="group_code">Group code <span class="required">*</span></label>
            <div class="input-control @error('group_code') has-error @enderror"><x-icon name="vehicle-group" :size="18" /><input id="group_code" name="group_code" value="{{ old('group_code', $vehicleGroup->group_code) }}" maxlength="30" required placeholder="e.g. HEAVY_TRUCKS" style="text-transform: uppercase"></div>
            <p class="field-hint">Letters, numbers, underscores, and hyphens only.</p>
            @error('group_code')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="vehicle_group_description">Vehicle group description <span class="required">*</span></label>
            <div class="input-control @error('vehicle_group_description') has-error @enderror"><x-icon name="vehicles" :size="18" /><input id="vehicle_group_description" name="vehicle_group_description" value="{{ old('vehicle_group_description', $vehicleGroup->vehicle_group_description) }}" maxlength="150" required placeholder="e.g. Heavy-Duty Trucks"></div>
            @error('vehicle_group_description')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <label class="account-status-control"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $vehicleGroup->exists ? $vehicleGroup->is_active : true))><span class="status-switch"></span><span><strong>Active vehicle group</strong><small>Inactive groups remain on record but are unavailable for assignment.</small></span></label>
    </div>
</x-card>
<div class="form-actions narrow-form-actions"><a href="{{ route('vehicle-groups.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
