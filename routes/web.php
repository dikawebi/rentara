<?php

use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
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
});
Route::post('app/workspace/switch', [WorkspaceController::class, 'switch'])->middleware(['auth', 'active-user', 'verified'])->name('app.workspace.switch');
Route::view('admin/dashboard', 'admin.dashboard')->middleware(['auth', 'active-user', 'verified', 'super-admin'])->name('admin.dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', 'active-user'])
    ->name('profile');

require __DIR__.'/auth.php';
