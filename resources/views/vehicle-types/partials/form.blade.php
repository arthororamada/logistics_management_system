<x-card title="Vehicle Type Information" subtitle="Define the vehicle classification and operational status." class="narrow-form-card">
    <div class="form-stack">
        <div class="form-group">
            <label for="vehicle_type">Vehicle type <span class="required">*</span></label>
            <div class="input-control @error('vehicle_type') has-error @enderror"><x-icon name="vehicles" :size="18" /><input id="vehicle_type" name="vehicle_type" value="{{ old('vehicle_type', $vehicleType->vehicle_type) }}" maxlength="100" required placeholder="e.g. Cargo Truck"></div>
            @error('vehicle_type')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <label class="account-status-control"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $vehicleType->exists ? $vehicleType->is_active : true))><span class="status-switch"></span><span><strong>Active vehicle type</strong><small>Inactive types remain on record but are unavailable for assignment.</small></span></label>
    </div>
</x-card>
<div class="form-actions narrow-form-actions"><a href="{{ route('vehicle-types.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
