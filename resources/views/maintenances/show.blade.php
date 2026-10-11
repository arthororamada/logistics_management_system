@extends('layouts.app')

@section('title', 'Maintenance Details')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Fleet Operations'], ['label' => 'Maintenance', 'url' => route('maintenances.index')], ['label' => $maintenance->policy->maintenance_code]])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Fleet Operations</p><h2>{{ $maintenance->policy->maintenance_code }}</h2><p>{{ $maintenance->vehicle->plate_number }} · {{ $maintenance->policy->maintenance_description }}</p></div>
        <div class="heading-actions"><a href="{{ route('maintenances.index') }}" class="button button-outline">Back to Maintenance</a>@if(auth()->user()->hasPermission('maintenances.delete'))<form method="POST" action="{{ route('maintenances.destroy', $maintenance) }}" onsubmit="return confirm('Delete this vehicle maintenance assignment and its renewal history?')">@csrf @method('DELETE')<button class="button button-outline" type="submit">Delete Assignment</button></form>@endif</div>
    </section>

    <div class="maintenance-detail-grid">
        <x-card title="Current Maintenance Cycle" subtitle="Live status based on the current odometer or date.">
            <div class="maintenance-current-status status-panel-{{ $currentStatus }}"><span class="status-badge status-{{ $currentStatus }}">{{ ucfirst($currentStatus) }}</span><strong>{{ $maintenance->remaining() }}</strong></div>
            <div class="detail-grid maintenance-cycle-details">
                <div><span class="stat-title">Vehicle</span><strong>{{ $maintenance->vehicle->plate_number }}</strong><small>{{ $maintenance->vehicle->vehicle_group }} / {{ $maintenance->vehicle->vehicle_type }}</small></div>
                <div><span class="stat-title">Current Odometer</span><strong>{{ number_format((float) $maintenance->vehicle->odometer, 2) }} km</strong></div>
                <div><span class="stat-title">Policy Type</span><strong>{{ $maintenance->policy_type === 'distance_policy' ? 'Distance policy' : 'Date policy' }}</strong></div>
                <div><span class="stat-title">Last Service</span><strong>{{ $maintenance->last_service_date->format('M d, Y') }}</strong><small>{{ number_format((float) $maintenance->last_service_odometer, 2) }} km</small></div>
                @if($maintenance->policy_type === 'distance_policy')
                    <div><span class="stat-title">Warning Odometer</span><strong>{{ number_format((float) $maintenance->warning_odometer, 2) }} km</strong></div>
                    <div><span class="stat-title">Due Odometer</span><strong>{{ number_format((float) $maintenance->next_due_odometer, 2) }} km</strong></div>
                @else
                    <div><span class="stat-title">Warning Date</span><strong>{{ $maintenance->warning_date->format('M d, Y') }}</strong></div>
                    <div><span class="stat-title">Due Date</span><strong>{{ $maintenance->next_due_date->format('M d, Y') }}</strong></div>
                @endif
            </div>
        </x-card>

        @if(auth()->user()->hasPermission('maintenances.update'))
            <x-card title="Renew Maintenance" subtitle="Record completed service and begin the next maintenance cycle.">
                <form method="POST" action="{{ route('maintenances.renew', $maintenance) }}" class="form-stack">
                    @csrf
                    <div class="form-group"><label for="service_date">Service date <span class="required">*</span></label><input id="service_date" name="service_date" type="date" min="{{ $maintenance->last_service_date->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" value="{{ old('service_date', now()->format('Y-m-d')) }}" class="form-input @error('service_date') has-error @enderror" required>@error('service_date')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div class="form-group"><label for="service_odometer">Service odometer <span class="required">*</span></label><input id="service_odometer" name="service_odometer" type="number" min="{{ $maintenance->last_service_odometer }}" step="0.01" value="{{ old('service_odometer', $maintenance->vehicle->odometer) }}" class="form-input @error('service_odometer') has-error @enderror" required>@error('service_odometer')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <div class="form-group"><label for="notes">Service notes</label><textarea id="notes" name="notes" rows="4" maxlength="2000" class="textarea-control @error('notes') has-error @enderror" placeholder="Work performed, parts replaced, or reference number">{{ old('notes') }}</textarea>@error('notes')<p class="form-error">{{ $message }}</p>@enderror</div>
                    <p class="system-note"><x-icon name="maintenance" :size="17" />Renewal uses the latest threshold settings from the Vehicle Maintenance policy and retains all previous service history.</p>
                    <x-button type="submit" variant="primary">Renew Maintenance</x-button>
                </form>
            </x-card>
        @endif
    </div>

    <x-card title="Renewal History" subtitle="Initial service and every subsequent renewal for this vehicle and policy.">
        <div class="table-wrap"><table class="access-table"><thead><tr><th>Service Date</th><th>Odometer</th><th>Notes</th><th>Recorded By</th><th>Recorded At</th></tr></thead><tbody>
            @forelse($maintenance->renewals as $renewal)<tr><td>{{ $renewal->service_date->format('M d, Y') }}</td><td>{{ number_format((float) $renewal->service_odometer, 2) }} km</td><td>{{ $renewal->notes ?: '—' }}</td><td>{{ $renewal->creator?->name ?? 'System' }}</td><td>{{ $renewal->created_at?->format('M d, Y g:i A') }}</td></tr>@empty<tr><td colspan="5" class="empty-table">No renewal history has been recorded.</td></tr>@endforelse
        </tbody></table></div>
    </x-card>
@endsection
