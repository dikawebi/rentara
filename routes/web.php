<?php

use App\Http\Controllers\BlockController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\PropertyAssignmentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitTypeController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Controllers\AmenityController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\UnitAmenityController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\RentalContractController;
use App\Http\Controllers\InvoiceController;
use App\Support\PostAuthenticationRedirect;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('dashboard', fn () => redirect(app(PostAuthenticationRedirect::class)->for(request()->user())))
    ->middleware(['auth', 'active-user', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'active-user', 'verified', 'workspace-context'])->prefix('app')->name('app.')->group(function () {
    Route::get('dashboard', fn () => view('workspace.dashboard', ['workspace' => request()->attributes->get('currentWorkspace')]))->name('dashboard');
    Route::get('workspace/settings', function () {
        $workspace = request()->attributes->get('currentWorkspace');
        abort_unless(request()->user()->can('update', $workspace), 403);

        return view('workspace.settings', compact('workspace'));
    })->name('workspace.settings');
    Route::get('members', [WorkspaceMemberController::class, 'index'])->name('members');
    Route::post('members', [WorkspaceMemberController::class, 'store'])->name('members.store');
    Route::patch('members/{member}', [WorkspaceMemberController::class, 'update'])->name('members.update');
    Route::patch('members/{member}/suspend', [WorkspaceMemberController::class, 'suspend'])->name('members.suspend');
    Route::delete('members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('members.destroy');
    Route::resource('properties', PropertyController::class)->whereNumber('property');
    Route::post('properties/{property}/restore', [PropertyController::class, 'restore'])->whereNumber('property')->name('properties.restore');
    Route::resource('properties.buildings', BuildingController::class)->except(['show'])->whereNumber(['property', 'building']);
    Route::post('properties/{property}/buildings/{building}/restore', [BuildingController::class, 'restore'])->whereNumber(['property', 'building'])->name('properties.buildings.restore');
    Route::resource('properties.floors', FloorController::class)->except(['show'])->whereNumber(['property', 'floor']);
    Route::post('properties/{property}/floors/{floor}/restore', [FloorController::class, 'restore'])->whereNumber(['property', 'floor'])->name('properties.floors.restore');
    Route::resource('properties.blocks', BlockController::class)->except(['show'])->whereNumber(['property', 'block']);
    Route::post('properties/{property}/blocks/{block}/restore', [BlockController::class, 'restore'])->whereNumber(['property', 'block'])->name('properties.blocks.restore');
    Route::resource('properties.units', UnitController::class)->except(['show'])->whereNumber(['property', 'unit']);
    Route::post('properties/{property}/units/{unit}/restore', [UnitController::class, 'restore'])->whereNumber(['property', 'unit'])->name('properties.units.restore');
    Route::resource('unit-types', UnitTypeController::class)->except(['show'])->whereNumber('unit_type');
    Route::post('unit-types/{unit_type}/restore', [UnitTypeController::class, 'restore'])->whereNumber('unit_type')->name('unit-types.restore');
    Route::resource('amenities', AmenityController::class)->except(['show'])->whereNumber('amenity');
    Route::post('amenities/{amenity}/restore', [AmenityController::class, 'restore'])->whereNumber('amenity')->name('amenities.restore');
    Route::resource('tenants', TenantController::class)->except(['show'])->whereNumber('tenant');
    Route::get('tenants/{tenant}', [TenantController::class, 'show'])->whereNumber('tenant')->name('tenants.show');
    Route::post('tenants/{tenant}/restore', [TenantController::class, 'restore'])->whereNumber('tenant')->name('tenants.restore');
    Route::resource('contracts', RentalContractController::class)->except(['edit'])->whereNumber('contract');
    Route::post('contracts/{contract}/restore', [RentalContractController::class, 'restore'])->whereNumber('contract')->name('contracts.restore');
    Route::post('contracts/{contract}/submit', [RentalContractController::class, 'submit'])->whereNumber('contract')->name('contracts.submit');
    Route::post('contracts/{contract}/activate', [RentalContractController::class, 'activate'])->whereNumber('contract')->name('contracts.activate');
    Route::post('contracts/{contract}/cancel', [RentalContractController::class, 'cancel'])->whereNumber('contract')->name('contracts.cancel');
    Route::post('contracts/{contract}/terminate', [RentalContractController::class, 'terminate'])->whereNumber('contract')->name('contracts.terminate');
    Route::post('contracts/{contract}/renew', [RentalContractController::class, 'renew'])->whereNumber('contract')->name('contracts.renew');
    Route::post('contracts/{contract}/tenants', [RentalContractController::class, 'attachTenant'])->whereNumber('contract')->name('contracts.tenants.attach');
    Route::post('contracts/{contract}/check-in', [RentalContractController::class, 'checkIn'])->whereNumber('contract')->name('contracts.check-in');
     Route::post('contracts/{contract}/check-out', [RentalContractController::class, 'checkOut'])->whereNumber('contract')->name('contracts.check-out');
     Route::resource('invoices', InvoiceController::class)->except(['destroy'])->whereNumber('invoice');
     Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->whereNumber('invoice')->name('invoices.pay');
    Route::get('properties/{property}/units/{unit}/amenities/edit', [UnitAmenityController::class, 'edit'])->whereNumber(['property', 'unit'])->name('properties.units.amenities.edit');
    Route::put('properties/{property}/units/{unit}/amenities', [UnitAmenityController::class, 'update'])->whereNumber(['property', 'unit'])->name('properties.units.amenities.update');
    Route::get('properties/{property}/media/{unit?}', [MediaController::class, 'index'])->whereNumber(['property', 'unit'])->name('properties.media.index');
    Route::post('properties/{property}/media/{unit?}', [MediaController::class, 'store'])->whereNumber(['property', 'unit'])->name('properties.media.store');
    Route::get('media/{media}', [MediaController::class, 'stream'])->whereNumber('media')->name('media.stream');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');
    Route::get('properties/{property}/assignments', [PropertyAssignmentController::class, 'index'])->whereNumber('property')->name('properties.assignments.index');
    Route::post('properties/{property}/assignments', [PropertyAssignmentController::class, 'store'])->whereNumber('property')->name('properties.assignments.store');
    Route::delete('properties/{property}/assignments/{assignment}', [PropertyAssignmentController::class, 'destroy'])->whereNumber(['property', 'assignment'])->name('properties.assignments.destroy');
});
Route::post('app/workspace/switch', [WorkspaceController::class, 'switch'])->middleware(['auth', 'active-user', 'verified'])->name('app.workspace.switch');
Route::view('admin/dashboard', 'admin.dashboard')->middleware(['auth', 'active-user', 'verified', 'super-admin'])->name('admin.dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', 'active-user'])
    ->name('profile');

require __DIR__.'/auth.php';
