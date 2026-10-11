<x-card title="Trip Rate Details" subtitle="Route, vehicle classification, compensation, and release settings.">
    <div class="vehicle-form-grid">
        @foreach([
            ['warehouse_code', 'Warehouse', 'warehouses', 'warehouse_code', 'warehouse_code'],
            ['trip_type', 'Trip Type', 'tripTypes', 'trip_type', 'trip_type'],
            ['vehicle_group', 'Vehicle Group', 'vehicleGroups', 'vehicle_group_description', 'vehicle_group_description'],
            ['vehicle_type', 'Vehicle Type', 'vehicleTypes', 'vehicle_type', 'vehicle_type'],
            ['payment_type', 'Payment Type', 'payments', 'payment_type', 'payment_type'],
            ['client_code', 'Client', 'clients', 'client_code', 'client_name'],
        ] as [$name, $label, $source, $value, $text])
            <div class="form-group">
                <label for="{{ $name }}">{{ $label }} <span class="required">*</span></label>
                <select id="{{ $name }}" class="form-select @error($name) has-error @enderror" name="{{ $name }}" required>
                    <option value="">Select {{ strtolower($label) }}</option>
                    @foreach($$source as $item)<option value="{{ $item->{$value} }}" @selected((string) old($name, $tripRate->{$name}) === (string) $item->{$value})>{{ $item->{$text} }}</option>@endforeach
                </select>
                @error($name)<p class="form-error">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <div class="form-group">
            <label for="trip_number">Trip number <span class="required">*</span></label>
            <input id="trip_number" class="form-input @error('trip_number') has-error @enderror" name="trip_number" value="{{ old('trip_number', $tripRate->trip_number) }}" type="text" maxlength="50" required>
            @error('trip_number')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        @foreach(['cargo_capacity', 'minimum_drop', 'driver_rate', 'helper_rate', 'driver_cash_released_percentage', 'helper_cash_released_percentage', 'additional_driver_rate', 'additional_helper_rate', 'bypass_fuel_liters'] as $name)
            <div class="form-group">
                <label for="{{ $name }}">{{ ucwords(str_replace('_', ' ', $name)) }} <span class="required">*</span></label>
                <input id="{{ $name }}" class="form-input @error($name) has-error @enderror" name="{{ $name }}" value="{{ old($name, $tripRate->{$name}) }}" type="number" step="0.01" min="0" @if(str_contains($name, 'percentage')) max="100" @endif required>
                @error($name)<p class="form-error">{{ $message }}</p>@enderror
            </div>
        @endforeach

        @php
            $routeNames = $routes->map(fn ($route) => implode(', ', array_filter([$route->province, $route->city, $route->brgy])))->filter()->unique()->values();
        @endphp
        @foreach(['route_origin', 'farthest_destination'] as $name)
            @php($selectedRoute = old($name, $tripRate->{$name}))
            <div class="form-group">
                <label for="{{ $name }}">{{ ucwords(str_replace('_', ' ', $name)) }} <span class="required">*</span></label>
                <select id="{{ $name }}" class="form-select @error($name) has-error @enderror" name="{{ $name }}" required>
                    <option value="">Select route</option>
                    @if($selectedRoute && !$routeNames->contains($selectedRoute))<option value="{{ $selectedRoute }}" selected>{{ $selectedRoute }} (current)</option>@endif
                    @foreach($routeNames as $routeName)<option value="{{ $routeName }}" @selected((string) $selectedRoute === (string) $routeName)>{{ $routeName }}</option>@endforeach
                </select>
                @error($name)<p class="form-error">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <div class="trip-rate-options">
            @foreach(['is_interisland', 'is_with_tollfee', 'is_bypass_fuel_usage'] as $name)
                <label class="checkbox-field"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked((bool) old($name, $tripRate->{$name}))> {{ ucwords(str_replace('_', ' ', $name)) }}</label>
            @endforeach
            <label class="checkbox-field"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $tripRate->exists ? $tripRate->is_active : true))> Active</label>
        </div>
    </div>
    <div class="form-actions"><a href="{{ route('trip-rates.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
</x-card>
