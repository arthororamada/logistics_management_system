<x-card title="Vehicle Make Information" subtitle="Define the manufacturer and operational status." class="narrow-form-card">
    <div class="form-stack">
        <div class="form-group">
            <label for="vehicle_make">Vehicle make <span class="required">*</span></label>
            <div class="input-control @error('vehicle_make') has-error @enderror"><x-icon name="vehicles" :size="18" /><input id="vehicle_make" name="vehicle_make" value="{{ old('vehicle_make', $vehicleMake->vehicle_make) }}" maxlength="100" required placeholder="e.g. Isuzu"></div>
            @error('vehicle_make')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <label class="account-status-control"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $vehicleMake->exists ? $vehicleMake->is_active : true))><span class="status-switch"></span><span><strong>Active vehicle make</strong><small>Inactive makes remain on record but are unavailable for assignment.</small></span></label>
    </div>
</x-card>
<div class="form-actions narrow-form-actions"><a href="{{ route('vehicle-makes.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
