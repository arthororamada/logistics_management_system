@extends('layouts.app')
@section('title', 'Permissions')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Permissions']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Access control</p><h2>Permissions</h2><p>Review the capabilities available for assignment to application roles.</p></div><x-button href="{{ route('roles.index') }}" variant="outline" icon="shield">Manage Roles</x-button></section>
    <div class="permission-registry">
        @foreach($permissionGroups as $group => $permissions)
            <x-card :title="$group" :subtitle="$permissions->count().' registered permissions'" collapsible>
                <div class="permission-list">
                    @foreach($permissions as $permission)
                        <article><span class="permission-list-icon"><x-icon name="key" :size="17" /></span><div><h3>{{ $permission->name }}</h3><code>{{ $permission->slug }}</code><p>{{ $permission->description }}</p></div><span class="role-count">{{ $permission->roles_count }} {{ Str::plural('role', $permission->roles_count) }}</span></article>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
@endsection
