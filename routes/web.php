<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PickupController;
use App\Http\Controllers\InboundController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\VehicleGroupController;
use App\Http\Controllers\VehicleTypeController;
use App\Http\Controllers\VehicleMakeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FuelSupplierController;
use App\Http\Controllers\OtherCostController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PersonnelTypeController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ShippingLineController;
use App\Http\Controllers\ShippingRateController;
use App\Http\Controllers\TripPaymentController;
use App\Http\Controllers\VehicleStatusController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleMaintenanceController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TripRateController;
use App\Http\Controllers\WaybillController;
use App\Http\Controllers\TripStatusUpdaterController;
use App\Http\Controllers\TripTypeController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : view('auth.login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'permission:dashboard.view'])->name('dashboard');

Route::get('/template', function () {
    return view('template');
})->middleware(['auth', 'permission:template.view'])->name('template');

Route::middleware('auth')->group(function () {
    Route::get('/document-manifests/return', [ReturnController::class, 'index'])->middleware('permission:return.view')->name('return.index');
    Route::post('/document-manifests/return', [ReturnController::class, 'store'])->middleware(['permission:return.view', 'permission:return.create'])->name('return.store');
    Route::post('/document-manifests/return/waybills', [ReturnController::class, 'addWaybills'])->middleware(['permission:return.view', 'permission:return.create'])->name('return.waybills.add');
    Route::delete('/document-manifests/return/waybills', [ReturnController::class, 'removeWaybill'])->middleware(['permission:return.view', 'permission:return.create'])->name('return.waybills.remove');
    Route::get('/document-manifests/return/{returnManifest}', [ReturnController::class, 'show'])->middleware('permission:return.view')->name('return.show');
    Route::post('/document-manifests/return/{returnManifest}/dispatch', [ReturnController::class, 'dispatch'])->middleware(['permission:return.view', 'permission:return.create'])->name('return.dispatch');
    Route::get('/document-manifests/delivery', [DeliveryController::class, 'index'])->middleware('permission:delivery.view')->name('delivery.index');
    Route::post('/document-manifests/delivery', [DeliveryController::class, 'store'])->middleware(['permission:delivery.view', 'permission:delivery.create'])->name('delivery.store');
    Route::post('/document-manifests/delivery/waybills', [DeliveryController::class, 'addWaybills'])->middleware(['permission:delivery.view', 'permission:delivery.create'])->name('delivery.waybills.add');
    Route::delete('/document-manifests/delivery/waybills', [DeliveryController::class, 'removeWaybill'])->middleware(['permission:delivery.view', 'permission:delivery.create'])->name('delivery.waybills.remove');
    Route::get('/document-manifests/delivery/{delivery}', [DeliveryController::class, 'show'])->middleware('permission:delivery.view')->name('delivery.show');
    Route::post('/document-manifests/delivery/{delivery}/dispatch', [DeliveryController::class, 'dispatch'])->middleware(['permission:delivery.view', 'permission:delivery.create'])->name('delivery.dispatch');
    Route::get('/document-manifests/transfer', [TransferController::class, 'index'])->middleware('permission:transfer.view')->name('transfer.index');
    Route::post('/document-manifests/transfer', [TransferController::class, 'store'])->middleware(['permission:transfer.view', 'permission:transfer.create'])->name('transfer.store');
    Route::post('/document-manifests/transfer/waybills', [TransferController::class, 'addWaybills'])->middleware(['permission:transfer.view', 'permission:transfer.create'])->name('transfer.waybills.add');
    Route::delete('/document-manifests/transfer/waybills', [TransferController::class, 'removeWaybill'])->middleware(['permission:transfer.view', 'permission:transfer.create'])->name('transfer.waybills.remove');
    Route::get('/document-manifests/transfer/{transfer}', [TransferController::class, 'show'])->middleware('permission:transfer.view')->name('transfer.show');
    Route::post('/document-manifests/transfer/{transfer}/dispatch', [TransferController::class, 'dispatch'])->middleware(['permission:transfer.view', 'permission:transfer.create'])->name('transfer.dispatch');
    Route::get('/document-manifests/inbound', [InboundController::class, 'index'])->middleware('permission:inbound.view')->name('inbound.index');
    Route::post('/document-manifests/inbound', [InboundController::class, 'store'])->middleware(['permission:inbound.view', 'permission:inbound.create'])->name('inbound.store');
    Route::post('/document-manifests/inbound/waybills', [InboundController::class, 'addWaybills'])->middleware(['permission:inbound.view', 'permission:inbound.create'])->name('inbound.waybills.add');
    Route::delete('/document-manifests/inbound/waybills', [InboundController::class, 'removeWaybill'])->middleware(['permission:inbound.view', 'permission:inbound.create'])->name('inbound.waybills.remove');

    Route::get('/document-manifests/pickup', [PickupController::class, 'index'])->middleware('permission:pickup.view')->name('pickup.index');
    Route::post('/document-manifests/pickup', [PickupController::class, 'store'])->middleware(['permission:pickup.view', 'permission:pickup.create'])->name('pickup.store');
    Route::post('/document-manifests/pickup/waybills', [PickupController::class, 'addWaybills'])->middleware(['permission:pickup.view', 'permission:pickup.create'])->name('pickup.waybills.add');
    Route::delete('/document-manifests/pickup/waybills', [PickupController::class, 'removeWaybill'])->middleware(['permission:pickup.view', 'permission:pickup.create'])->name('pickup.waybills.remove');
    Route::get('/document-manifests/pickup/{pickup}', [PickupController::class, 'show'])->middleware('permission:pickup.view')->name('pickup.show');
    Route::post('/document-manifests/pickup/{pickup}/dispatch', [PickupController::class, 'dispatch'])->middleware(['permission:pickup.view', 'permission:pickup.create'])->name('pickup.dispatch');
    Route::get('/waybills', [WaybillController::class, 'index'])->middleware('permission:waybills.view')->name('waybills.index');
    Route::get('/waybills/create', [WaybillController::class, 'create'])->middleware('permission:waybills.create')->name('waybills.create');
    Route::post('/waybills', [WaybillController::class, 'store'])->middleware('permission:waybills.create')->name('waybills.store');
    Route::get('/waybills/upload', [WaybillController::class, 'upload'])->middleware('permission:waybills.upload')->name('waybills.upload');
    Route::get('/waybills/upload/template', [WaybillController::class, 'template'])->middleware('permission:waybills.upload')->name('waybills.template');
    Route::post('/waybills/upload/custom-template', [WaybillController::class, 'customTemplate'])->middleware('permission:waybills.upload')->name('waybills.custom-template');
    Route::post('/waybills/upload', [WaybillController::class, 'import'])->middleware('permission:waybills.upload')->name('waybills.import');
    Route::post('/waybills/upload/proceed', [WaybillController::class, 'proceedImport'])->middleware('permission:waybills.upload')->name('waybills.import.proceed');
    Route::get('/waybills/{package}', [WaybillController::class, 'show'])->middleware('permission:waybills.view')->name('waybills.show');

    Route::get('/trip-status-updaters', [TripStatusUpdaterController::class, 'index'])->middleware('permission:trip-status-updaters.view')->name('trip-status-updaters.index');
    Route::get('/trip-status-updaters/search', [TripStatusUpdaterController::class, 'search'])->middleware('permission:trip-status-updaters.view')->name('trip-status-updaters.search');
    Route::put('/trip-status-updaters', [TripStatusUpdaterController::class, 'update'])->middleware(['permission:trip-status-updaters.view', 'permission:trip-status-updaters.update'])->name('trip-status-updaters.update');
    Route::get('/trips', [\App\Http\Controllers\TripController::class, 'index'])->middleware('permission:trips.view')->name('trips.index');
    Route::post('/trips', [\App\Http\Controllers\TripController::class, 'store'])->middleware(['permission:trips.create', 'permission:trips.view'])->name('trips.store');
    Route::get('/trips/{trip}', [\App\Http\Controllers\TripController::class, 'show'])->middleware('permission:trips.view')->name('trips.show');
    Route::get('/trips/{trip}/availability', [\App\Http\Controllers\TripController::class, 'availability'])->middleware('permission:trips.view')->name('trips.availability');
    Route::put('/trips/{trip}', [\App\Http\Controllers\TripController::class, 'update'])->middleware('permission:trips.update')->name('trips.update');
    Route::post('/trips/{trip}/dispatch', [\App\Http\Controllers\TripController::class, 'dispatch'])->middleware('permission:trips.dispatch')->name('trips.dispatch');
    Route::post('/trips/{trip}/complete', [\App\Http\Controllers\TripController::class, 'complete'])->middleware('permission:trips.complete')->name('trips.complete');
    Route::get('/trips/{trip}/print', [\App\Http\Controllers\TripController::class, 'print'])->middleware('permission:trips.view')->name('trips.print');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

    Route::get('/locations', [LocationController::class, 'index'])->middleware('permission:locations.view')->name('locations.index');
    Route::get('/locations/create', [LocationController::class, 'create'])->middleware('permission:locations.create')->name('locations.create');
    Route::post('/locations', [LocationController::class, 'store'])->middleware('permission:locations.create')->name('locations.store');
    Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->middleware('permission:locations.update')->name('locations.edit');
    Route::put('/locations/{location}', [LocationController::class, 'update'])->middleware('permission:locations.update')->name('locations.update');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->middleware('permission:locations.delete')->name('locations.destroy');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('permissions.index');

    Route::get('/warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view')->name('warehouses.index');
    Route::get('/warehouses/create', [WarehouseController::class, 'create'])->middleware('permission:warehouses.create')->name('warehouses.create');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create')->name('warehouses.store');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->middleware('permission:warehouses.update')->name('warehouses.edit');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.update')->name('warehouses.update');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete')->name('warehouses.destroy');

    Route::get('/vehicle-groups', [VehicleGroupController::class, 'index'])->middleware('permission:vehicle-groups.view')->name('vehicle-groups.index');
    Route::get('/vehicle-groups/create', [VehicleGroupController::class, 'create'])->middleware('permission:vehicle-groups.create')->name('vehicle-groups.create');
    Route::post('/vehicle-groups', [VehicleGroupController::class, 'store'])->middleware('permission:vehicle-groups.create')->name('vehicle-groups.store');
    Route::get('/vehicle-groups/{vehicleGroup}/edit', [VehicleGroupController::class, 'edit'])->middleware('permission:vehicle-groups.update')->name('vehicle-groups.edit');
    Route::put('/vehicle-groups/{vehicleGroup}', [VehicleGroupController::class, 'update'])->middleware('permission:vehicle-groups.update')->name('vehicle-groups.update');
    Route::delete('/vehicle-groups/{vehicleGroup}', [VehicleGroupController::class, 'destroy'])->middleware('permission:vehicle-groups.delete')->name('vehicle-groups.destroy');

    Route::get('/vehicle-types', [VehicleTypeController::class, 'index'])->middleware('permission:vehicle-types.view')->name('vehicle-types.index');
    Route::get('/vehicle-types/create', [VehicleTypeController::class, 'create'])->middleware('permission:vehicle-types.create')->name('vehicle-types.create');
    Route::post('/vehicle-types', [VehicleTypeController::class, 'store'])->middleware('permission:vehicle-types.create')->name('vehicle-types.store');
    Route::get('/vehicle-types/{vehicleType}/edit', [VehicleTypeController::class, 'edit'])->middleware('permission:vehicle-types.update')->name('vehicle-types.edit');
    Route::put('/vehicle-types/{vehicleType}', [VehicleTypeController::class, 'update'])->middleware('permission:vehicle-types.update')->name('vehicle-types.update');
    Route::delete('/vehicle-types/{vehicleType}', [VehicleTypeController::class, 'destroy'])->middleware('permission:vehicle-types.delete')->name('vehicle-types.destroy');

    Route::get('/vehicle-makes', [VehicleMakeController::class, 'index'])->middleware('permission:vehicle-makes.view')->name('vehicle-makes.index');
    Route::get('/vehicle-makes/create', [VehicleMakeController::class, 'create'])->middleware('permission:vehicle-makes.create')->name('vehicle-makes.create');
    Route::post('/vehicle-makes', [VehicleMakeController::class, 'store'])->middleware('permission:vehicle-makes.create')->name('vehicle-makes.store');
    Route::get('/vehicle-makes/{vehicleMake}/edit', [VehicleMakeController::class, 'edit'])->middleware('permission:vehicle-makes.update')->name('vehicle-makes.edit');
    Route::put('/vehicle-makes/{vehicleMake}', [VehicleMakeController::class, 'update'])->middleware('permission:vehicle-makes.update')->name('vehicle-makes.update');
    Route::delete('/vehicle-makes/{vehicleMake}', [VehicleMakeController::class, 'destroy'])->middleware('permission:vehicle-makes.delete')->name('vehicle-makes.destroy');

    Route::get('/clients', [ClientController::class, 'index'])->middleware('permission:clients.view')->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->middleware('permission:clients.create')->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->middleware('permission:clients.create')->name('clients.store');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->middleware('permission:clients.update')->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->middleware('permission:clients.update')->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->middleware('permission:clients.delete')->name('clients.destroy');

    Route::get('/routes', [RouteController::class, 'index'])->middleware('permission:routes.view')->name('routes.index');
    Route::get('/routes/create', [RouteController::class, 'create'])->middleware('permission:routes.create')->name('routes.create');
    Route::post('/routes', [RouteController::class, 'store'])->middleware('permission:routes.create')->name('routes.store');
    Route::get('/routes/{route}/edit', [RouteController::class, 'edit'])->middleware('permission:routes.update')->name('routes.edit');
    Route::put('/routes/{route}', [RouteController::class, 'update'])->middleware('permission:routes.update')->name('routes.update');
    Route::delete('/routes/{route}', [RouteController::class, 'destroy'])->middleware('permission:routes.delete')->name('routes.destroy');

    Route::get('/trip-types', [TripTypeController::class, 'index'])->middleware('permission:trip-types.view')->name('trip-types.index');
    Route::get('/trip-types/create', [TripTypeController::class, 'create'])->middleware('permission:trip-types.create')->name('trip-types.create');
    Route::post('/trip-types', [TripTypeController::class, 'store'])->middleware('permission:trip-types.create')->name('trip-types.store');
    Route::get('/trip-types/{tripType}/edit', [TripTypeController::class, 'edit'])->middleware('permission:trip-types.update')->name('trip-types.edit');
    Route::put('/trip-types/{tripType}', [TripTypeController::class, 'update'])->middleware('permission:trip-types.update')->name('trip-types.update');
    Route::delete('/trip-types/{tripType}', [TripTypeController::class, 'destroy'])->middleware('permission:trip-types.delete')->name('trip-types.destroy');

    Route::get('/other-costs', [OtherCostController::class, 'index'])->middleware('permission:other-costs.view')->name('other-costs.index');
    Route::get('/other-costs/create', [OtherCostController::class, 'create'])->middleware('permission:other-costs.create')->name('other-costs.create');
    Route::post('/other-costs', [OtherCostController::class, 'store'])->middleware('permission:other-costs.create')->name('other-costs.store');
    Route::get('/other-costs/{otherCost}/edit', [OtherCostController::class, 'edit'])->middleware('permission:other-costs.update')->name('other-costs.edit');
    Route::put('/other-costs/{otherCost}', [OtherCostController::class, 'update'])->middleware('permission:other-costs.update')->name('other-costs.update');
    Route::delete('/other-costs/{otherCost}', [OtherCostController::class, 'destroy'])->middleware('permission:other-costs.delete')->name('other-costs.destroy');

    Route::get('/fuel-suppliers', [FuelSupplierController::class, 'index'])->middleware('permission:fuel-suppliers.view')->name('fuel-suppliers.index');
    Route::get('/fuel-suppliers/create', [FuelSupplierController::class, 'create'])->middleware('permission:fuel-suppliers.create')->name('fuel-suppliers.create');
    Route::post('/fuel-suppliers', [FuelSupplierController::class, 'store'])->middleware('permission:fuel-suppliers.create')->name('fuel-suppliers.store');
    Route::get('/fuel-suppliers/{fuelSupplier}/edit', [FuelSupplierController::class, 'edit'])->middleware('permission:fuel-suppliers.update')->name('fuel-suppliers.edit');
    Route::put('/fuel-suppliers/{fuelSupplier}', [FuelSupplierController::class, 'update'])->middleware('permission:fuel-suppliers.update')->name('fuel-suppliers.update');
    Route::delete('/fuel-suppliers/{fuelSupplier}', [FuelSupplierController::class, 'destroy'])->middleware('permission:fuel-suppliers.delete')->name('fuel-suppliers.destroy');

    Route::get('/shipping-lines', [ShippingLineController::class, 'index'])->middleware('permission:shipping-lines.view')->name('shipping-lines.index');
    Route::get('/shipping-lines/create', [ShippingLineController::class, 'create'])->middleware('permission:shipping-lines.create')->name('shipping-lines.create');
    Route::post('/shipping-lines', [ShippingLineController::class, 'store'])->middleware('permission:shipping-lines.create')->name('shipping-lines.store');
    Route::get('/shipping-lines/{shippingLine}/edit', [ShippingLineController::class, 'edit'])->middleware('permission:shipping-lines.update')->name('shipping-lines.edit');
    Route::put('/shipping-lines/{shippingLine}', [ShippingLineController::class, 'update'])->middleware('permission:shipping-lines.update')->name('shipping-lines.update');
    Route::delete('/shipping-lines/{shippingLine}', [ShippingLineController::class, 'destroy'])->middleware('permission:shipping-lines.delete')->name('shipping-lines.destroy');

    Route::get('/shipping-rates', [ShippingRateController::class, 'index'])->middleware('permission:shipping-rates.view')->name('shipping-rates.index');
    Route::get('/shipping-rates/create', [ShippingRateController::class, 'create'])->middleware('permission:shipping-rates.create')->name('shipping-rates.create');
    Route::post('/shipping-rates', [ShippingRateController::class, 'store'])->middleware('permission:shipping-rates.create')->name('shipping-rates.store');
    Route::get('/shipping-rates/{shippingRate}/edit', [ShippingRateController::class, 'edit'])->middleware('permission:shipping-rates.update')->name('shipping-rates.edit');
    Route::put('/shipping-rates/{shippingRate}', [ShippingRateController::class, 'update'])->middleware('permission:shipping-rates.update')->name('shipping-rates.update');
    Route::delete('/shipping-rates/{shippingRate}', [ShippingRateController::class, 'destroy'])->middleware('permission:shipping-rates.delete')->name('shipping-rates.destroy');
    Route::get('/trip-rates', [TripRateController::class, 'index'])->middleware('permission:trip-rates.view')->name('trip-rates.index');
    Route::get('/trip-rates/create', [TripRateController::class, 'create'])->middleware('permission:trip-rates.create')->name('trip-rates.create');
    Route::post('/trip-rates', [TripRateController::class, 'store'])->middleware('permission:trip-rates.create')->name('trip-rates.store');
    Route::get('/trip-rates/{tripRate}/edit', [TripRateController::class, 'edit'])->middleware('permission:trip-rates.update')->name('trip-rates.edit');
    Route::put('/trip-rates/{tripRate}', [TripRateController::class, 'update'])->middleware('permission:trip-rates.update')->name('trip-rates.update');
    Route::delete('/trip-rates/{tripRate}', [TripRateController::class, 'destroy'])->middleware('permission:trip-rates.delete')->name('trip-rates.destroy');

    Route::get('/trip-payments', [TripPaymentController::class, 'index'])->middleware('permission:trip-payments.view')->name('trip-payments.index');
    Route::get('/trip-payments/create', [TripPaymentController::class, 'create'])->middleware('permission:trip-payments.create')->name('trip-payments.create');
    Route::post('/trip-payments', [TripPaymentController::class, 'store'])->middleware('permission:trip-payments.create')->name('trip-payments.store');
    Route::get('/trip-payments/{tripPayment}/edit', [TripPaymentController::class, 'edit'])->middleware('permission:trip-payments.update')->name('trip-payments.edit');
    Route::put('/trip-payments/{tripPayment}', [TripPaymentController::class, 'update'])->middleware('permission:trip-payments.update')->name('trip-payments.update');
    Route::delete('/trip-payments/{tripPayment}', [TripPaymentController::class, 'destroy'])->middleware('permission:trip-payments.delete')->name('trip-payments.destroy');

    Route::get('/vehicle-statuses', [VehicleStatusController::class, 'index'])->middleware('permission:vehicle-statuses.view')->name('vehicle-statuses.index');
    Route::get('/vehicle-statuses/create', [VehicleStatusController::class, 'create'])->middleware('permission:vehicle-statuses.create')->name('vehicle-statuses.create');
    Route::post('/vehicle-statuses', [VehicleStatusController::class, 'store'])->middleware('permission:vehicle-statuses.create')->name('vehicle-statuses.store');
    Route::get('/vehicle-statuses/{vehicleStatus}/edit', [VehicleStatusController::class, 'edit'])->middleware('permission:vehicle-statuses.update')->name('vehicle-statuses.edit');
    Route::put('/vehicle-statuses/{vehicleStatus}', [VehicleStatusController::class, 'update'])->middleware('permission:vehicle-statuses.update')->name('vehicle-statuses.update');
    Route::delete('/vehicle-statuses/{vehicleStatus}', [VehicleStatusController::class, 'destroy'])->middleware('permission:vehicle-statuses.delete')->name('vehicle-statuses.destroy');

    Route::get('/vehicle-maintenances/import-template', [VehicleMaintenanceController::class, 'template'])->middleware('permission:vehicle-maintenances.create')->name('vehicle-maintenances.import-template');
    Route::post('/vehicle-maintenances/import', [VehicleMaintenanceController::class, 'import'])->middleware('permission:vehicle-maintenances.create')->name('vehicle-maintenances.import');
    Route::get('/vehicle-maintenances', [VehicleMaintenanceController::class, 'index'])->middleware('permission:vehicle-maintenances.view')->name('vehicle-maintenances.index');
    Route::get('/vehicle-maintenances/create', [VehicleMaintenanceController::class, 'create'])->middleware('permission:vehicle-maintenances.create')->name('vehicle-maintenances.create');
    Route::post('/vehicle-maintenances', [VehicleMaintenanceController::class, 'store'])->middleware('permission:vehicle-maintenances.create')->name('vehicle-maintenances.store');
    Route::get('/vehicle-maintenances/{vehicleMaintenance}/edit', [VehicleMaintenanceController::class, 'edit'])->middleware('permission:vehicle-maintenances.update')->name('vehicle-maintenances.edit');
    Route::put('/vehicle-maintenances/{vehicleMaintenance}', [VehicleMaintenanceController::class, 'update'])->middleware('permission:vehicle-maintenances.update')->name('vehicle-maintenances.update');
    Route::delete('/vehicle-maintenances/{vehicleMaintenance}', [VehicleMaintenanceController::class, 'destroy'])->middleware('permission:vehicle-maintenances.delete')->name('vehicle-maintenances.destroy');

    Route::get('/maintenances', [MaintenanceController::class, 'index'])->middleware('permission:maintenances.view')->name('maintenances.index');
    Route::get('/maintenances/create', [MaintenanceController::class, 'create'])->middleware('permission:maintenances.create')->name('maintenances.create');
    Route::post('/maintenances', [MaintenanceController::class, 'store'])->middleware('permission:maintenances.create')->name('maintenances.store');
    Route::get('/maintenances/{maintenance}', [MaintenanceController::class, 'show'])->middleware('permission:maintenances.view')->name('maintenances.show');
    Route::post('/maintenances/{maintenance}/renew', [MaintenanceController::class, 'renew'])->middleware('permission:maintenances.update')->name('maintenances.renew');
    Route::delete('/maintenances/{maintenance}', [MaintenanceController::class, 'destroy'])->middleware('permission:maintenances.delete')->name('maintenances.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('permission:maintenances.view')->name('notifications.index');
    Route::get('/personnel-types', [PersonnelTypeController::class, 'index'])->middleware('permission:personnel-types.view')->name('personnel-types.index');
    Route::get('/personnel-types/create', [PersonnelTypeController::class, 'create'])->middleware('permission:personnel-types.create')->name('personnel-types.create');
    Route::post('/personnel-types', [PersonnelTypeController::class, 'store'])->middleware('permission:personnel-types.create')->name('personnel-types.store');
    Route::get('/personnel-types/{personnelType}/edit', [PersonnelTypeController::class, 'edit'])->middleware('permission:personnel-types.update')->name('personnel-types.edit');
    Route::put('/personnel-types/{personnelType}', [PersonnelTypeController::class, 'update'])->middleware('permission:personnel-types.update')->name('personnel-types.update');
    Route::delete('/personnel-types/{personnelType}', [PersonnelTypeController::class, 'destroy'])->middleware('permission:personnel-types.delete')->name('personnel-types.destroy');
    Route::get('/personnel', [PersonnelController::class, 'index'])->middleware('permission:personnel.view')->name('personnel.index');
    Route::get('/personnel/create', [PersonnelController::class, 'create'])->middleware('permission:personnel.create')->name('personnel.create');
    Route::post('/personnel', [PersonnelController::class, 'store'])->middleware('permission:personnel.create')->name('personnel.store');
    Route::get('/personnel/{personnel}', [PersonnelController::class, 'show'])->middleware('permission:personnel.view')->name('personnel.show');
    Route::get('/personnel/{personnel}/edit', [PersonnelController::class, 'edit'])->middleware('permission:personnel.update')->name('personnel.edit');
    Route::put('/personnel/{personnel}', [PersonnelController::class, 'update'])->middleware('permission:personnel.update')->name('personnel.update');

    Route::get('/vehicles', [VehicleController::class, 'index'])->middleware('permission:vehicles.view')->name('vehicles.index');
    Route::get('/vehicles/export', [VehicleController::class, 'export'])->middleware('permission:vehicles.view')->name('vehicles.export');
    Route::get('/vehicles/import-template', [VehicleController::class, 'template'])->middleware('permission:vehicles.create')->name('vehicles.import-template');
    Route::post('/vehicles/import', [VehicleController::class, 'import'])->middleware('permission:vehicles.create')->name('vehicles.import');
    Route::get('/vehicles/create', [VehicleController::class, 'create'])->middleware('permission:vehicles.create')->name('vehicles.create');
    Route::post('/vehicles', [VehicleController::class, 'store'])->middleware('permission:vehicles.create')->name('vehicles.store');
    Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->middleware('permission:vehicles.update')->name('vehicles.edit');
    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->middleware('permission:vehicles.view')->name('vehicles.show');
    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicles.update')->name('vehicles.update');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
