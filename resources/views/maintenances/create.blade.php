@extends('layouts.app')

@section('title', 'Create Vehicle Maintenance')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Fleet Operations'], ['label' => 'Maintenance', 'url' => route('maintenances.index')], ['label' => 'Create']])

@section('content')
    <section class="page-heading"><div><p class="eyebrow">Fleet Operations</p><h2>Create Vehicle Maintenance</h2><p>Assign an applicable maintenance policy and establish its first service baseline.</p></div></section>

    <form method="POST" action="{{ route('maintenances.store') }}">
        @csrf
        <x-card title="Vehicle and Maintenance Policy" subtitle="Policies are restricted to the selected vehicle's group and type." class="narrow-form-card">
            <div class="form-stack">
                <div class="form-group">
                    <label for="vehicle_id">Vehicle <span class="required">*</span></label>
                    <select id="vehicle_id" name="vehicle_id" class="form-select @error('vehicle_id') has-error @enderror" required>
                        <option value="">Select vehicle</option>
                        @foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $selectedVehicleId) === (string) $vehicle->id)>{{ $vehicle->plate_number }} — {{ $vehicle->body_number }} ({{ $vehicle->vehicle_group }} / {{ $vehicle->vehicle_type }})</option>@endforeach
                    </select>
                    @error('vehicle_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="vehicle_maintenance_id">Maintenance policy <span class="required">*</span></label>
                    <select id="vehicle_maintenance_id" name="vehicle_maintenance_id" class="form-select @error('vehicle_maintenance_id') has-error @enderror" required disabled>
                        <option value="">Select vehicle first</option>
                        @foreach($policies as $policy)<option value="{{ $policy->id }}" data-group="{{ $policy->vehicle_group }}" data-type="{{ $policy->vehicle_type }}" data-policy-type="{{ $policy->maintenance_type }}" data-threshold="{{ $policy->threshold }}" data-warning="{{ $policy->threshold_warning }}" @selected((string) old('vehicle_maintenance_id') === (string) $policy->id)>{{ $policy->maintenance_code }} — {{ $policy->maintenance_description }}</option>@endforeach
                    </select>
                    <p class="field-hint" id="policy-help">Choose a vehicle to load matching maintenance policies.</p>
                    @error('vehicle_maintenance_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="warehouse-location-grid">
                    <div class="form-group"><label for="service_date">Last service date <span class="required">*</span></label><input id="service_date" name="service_date" type="date" class="form-input @error('service_date') has-error @enderror" max="{{ now()->format('Y-m-d') }}" value="{{ old('service_date', now()->format('Y-m-d')) }}" required>@error('service_date')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div class="form-group"><label for="service_odometer">Service odometer <span class="required">*</span></label><input id="service_odometer" name="service_odometer" type="number" min="0" step="0.01" class="form-input @error('service_odometer') has-error @enderror" value="{{ old('service_odometer') }}" required>@error('service_odometer')<p class="form-error">{{ $message }}</p>@enderror</div>
                </div>
                <div class="form-group"><label for="notes">Initial service notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000" class="textarea-control @error('notes') has-error @enderror" placeholder="Work performed, parts replaced, or reference number">{{ old('notes') }}</textarea>@error('notes')<p class="form-error">{{ $message }}</p>@enderror</div>
            </div>
        </x-card>
        <div class="form-actions narrow-form-actions"><a href="{{ $selectedVehicleId ? route('vehicles.show', $selectedVehicleId) : route('maintenances.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">Create Maintenance</x-button></div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const vehicles = @json($vehicles->keyBy('id'));
            const vehicleSelect = document.querySelector('#vehicle_id');
            const policySelect = document.querySelector('#vehicle_maintenance_id');
            const serviceOdometer = document.querySelector('#service_odometer');
            const help = document.querySelector('#policy-help');
            const policyOptions = [...policySelect.querySelectorAll('option[data-group]')];
            const requestedPolicy = @json((string) old('vehicle_maintenance_id', ''));

            function refreshPolicies() {
                const vehicle = vehicles[vehicleSelect.value];
                let matches = 0;
                policyOptions.forEach((option) => {
                    const match = vehicle && option.dataset.group === vehicle.vehicle_group && option.dataset.type === vehicle.vehicle_type;
                    option.hidden = !match; option.disabled = !match; if (match) matches++;
                });
                policySelect.disabled = !vehicle || matches === 0;
                if (!vehicle) {
                    policySelect.value = ''; policySelect.options[0].textContent = 'Select vehicle first';
                    help.textContent = 'Choose a vehicle to load matching maintenance policies.';
                } else {
                    policySelect.options[0].textContent = matches ? 'Select matching policy' : 'No matching active policies';
                    if (!policyOptions.some((option) => !option.disabled && option.value === policySelect.value)) policySelect.value = '';
                    help.textContent = matches ? `${matches} matching policy or policies available for ${vehicle.vehicle_group} / ${vehicle.vehicle_type}.` : `No active policies match ${vehicle.vehicle_group} / ${vehicle.vehicle_type}. Configure one in Vehicle Maintenance.`;
                    if (!serviceOdometer.value) serviceOdometer.value = vehicle.odometer;
                }
                if (requestedPolicy && policyOptions.some((option) => !option.disabled && option.value === requestedPolicy)) policySelect.value = requestedPolicy;
                refreshPolicyHelp();
            }
            function refreshPolicyHelp() {
                const option = policyOptions.find((item) => item.value === policySelect.value);
                if (!option) return;
                const unit = option.dataset.policyType === 'distance_policy' ? 'km' : 'days';
                help.textContent = `Threshold: ${Number(option.dataset.threshold).toLocaleString()} ${unit}; advance warning: ${Number(option.dataset.warning).toLocaleString()} ${unit}.`;
            }
            vehicleSelect.addEventListener('change', () => { serviceOdometer.value = ''; refreshPolicies(); });
            policySelect.addEventListener('change', refreshPolicyHelp);
            refreshPolicies();
        });
    </script>
@endsection
