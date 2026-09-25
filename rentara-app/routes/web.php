<?php

use App\Http\Controllers\AdminListingController;
use App\Http\Controllers\ApplicantIdentityDocumentController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\CreateOrganizationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationApplicationController;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\OrganizationMembersController;
use App\Http\Controllers\OrganizationPropertyController;
use App\Http\Controllers\OwnerListingController;
use App\Http\Controllers\PropertyPhotoController;
use App\Http\Controllers\PropertyUnitController;
use App\Http\Controllers\PublicListingPhotoController;
use App\Http\Controllers\RentalAgreementController;
use App\Http\Controllers\RentalApplicationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::get('/listings', [MarketplaceController::class, 'index'])->name('listings.index');
Route::get('/listings/{listing:slug}/photos/{photo}/{variant}', [PublicListingPhotoController::class, 'show'])
    ->whereIn('variant', ['full', 'thumbnail'])
    ->name('listings.photos.show');
Route::get('/listings/{listing:slug}', [MarketplaceController::class, 'show'])->name('listings.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/listings/{listing:slug}/apply', [RentalApplicationController::class, 'create'])->name('listings.apply');
    Route::post('/listings/{listing:slug}/applications', [RentalApplicationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('listings.applications.store');
    Route::get('/my-applications', [RentalApplicationController::class, 'index'])->name('applications.index');
    Route::get('/my-applications/{application}', [RentalApplicationController::class, 'show'])->name('applications.show');
    Route::post('/my-applications/{application}/identity-documents', [ApplicantIdentityDocumentController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('applications.identity-documents.store');
    Route::get('/my-applications/{application}/identity-documents/{identityDocument}/download', [ApplicantIdentityDocumentController::class, 'download'])
        ->name('applications.identity-documents.download');
    Route::post('/my-applications/{application}/agreement/approve', [RentalAgreementController::class, 'approveApplicant'])
        ->name('applications.agreement.approve');
    Route::get('/my-applications/{application}/agreement/download', [RentalAgreementController::class, 'downloadApplicant'])
        ->name('applications.agreement.download');
    Route::post('/my-invoices/{invoice}/payment-evidence', [InvoiceController::class, 'uploadEvidence'])
        ->middleware('throttle:10,1')
        ->name('invoices.payment-evidence.store');
    Route::get('/complaints', [ComplaintController::class, 'tenantIndex'])->name('complaints.index');
    Route::get('/complaints/create', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [ComplaintController::class, 'store'])->middleware('throttle:10,1')->name('complaints.store');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::post('/complaints/{complaint}/comments', [ComplaintController::class, 'comment'])->name('complaints.comments.store');
    Route::post('/complaints/{complaint}/attachments', [ComplaintController::class, 'attachment'])->middleware('throttle:10,1')->name('complaints.attachments.store');
    Route::get('/complaints/{complaint}/attachments/{attachment}', [ComplaintController::class, 'download'])->name('complaints.attachments.download');
    Route::post('/complaints/{complaint}/close', [ComplaintController::class, 'tenantClose'])->name('complaints.close');
    Route::post('/complaints/{complaint}/reopen', [ComplaintController::class, 'tenantReopen'])->name('complaints.reopen');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
    Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
    Route::get('/forgot-password', fn () => Inertia::render('Auth/ForgotPassword'))->name('password.request');
    Route::get('/reset-password/{token}', function (Request $request, string $token) {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    })->name('password.reset');
});

Route::get('/email/verify', fn () => Inertia::render('Auth/VerifyEmail'))
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/dashboard', function (Request $request) {
    $memberships = $request->user()
        ->organizationMemberships()
        ->whereNotNull('accepted_at')
        ->with('organization:id,name')
        ->oldest()
        ->get();

    return Inertia::render('Dashboard', [
        'user' => $request->user()->only('name', 'email', 'email_verified_at', 'is_platform_admin'),
        'organizations' => $memberships->map(fn ($membership): array => [
            'id' => $membership->organization->id,
            'name' => $membership->organization->name,
            'role' => $membership->role->value,
            'can_manage_members' => in_array($membership->role->value, ['owner', 'manager'], true),
            'can_manage_properties' => in_array($membership->role->value, ['owner', 'manager'], true),
        ])->values(),
    ]);
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('/organizations', CreateOrganizationController::class)
    ->middleware(['auth', 'verified'])
    ->name('organizations.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/organizations/{organization}/members', [OrganizationMembersController::class, 'index'])
        ->name('organizations.members.index');
    Route::post('/organizations/{organization}/invitations', [OrganizationMembersController::class, 'storeInvitation'])
        ->middleware('throttle:10,1')
        ->name('organizations.invitations.store');
    Route::delete('/organizations/{organization}/members/{membership}', [OrganizationMembersController::class, 'destroyMembership'])
        ->name('organizations.members.destroy');
    Route::delete('/organizations/{organization}/invitations/{invitation}', [OrganizationMembersController::class, 'destroyInvitation'])
        ->name('organizations.invitations.destroy');

    Route::get('/organization-invitations/{token}', [OrganizationInvitationController::class, 'show'])
        ->whereAlphaNumeric('token')
        ->name('organization-invitations.show');
    Route::post('/organization-invitations/{token}/accept', [OrganizationInvitationController::class, 'accept'])
        ->whereAlphaNumeric('token')
        ->name('organization-invitations.accept');

    Route::get('/organizations/{organization}/applications', [OrganizationApplicationController::class, 'index'])
        ->name('organizations.applications.index');
    Route::get('/organizations/{organization}/complaints', [ComplaintController::class, 'organizationIndex'])->name('organizations.complaints.index');
    Route::get('/organizations/{organization}/complaints/{complaint}', [ComplaintController::class, 'organizationShow'])->name('organizations.complaints.show');
    Route::patch('/organizations/{organization}/complaints/{complaint}', [ComplaintController::class, 'update'])->name('organizations.complaints.update');
    Route::patch('/organizations/{organization}/booking-policy', [OrganizationPropertyController::class, 'updateBookingExpiryPolicy'])
        ->name('organizations.booking-policy.update');
    Route::get('/organizations/{organization}/applications/{application}', [OrganizationApplicationController::class, 'show'])
        ->name('organizations.applications.show');
    Route::patch('/organizations/{organization}/applications/{application}/status', [OrganizationApplicationController::class, 'updateStatus'])
        ->name('organizations.applications.status.update');
    Route::patch('/organizations/{organization}/applications/{application}/identity-documents/{identityDocument}/review', [OrganizationApplicationController::class, 'reviewIdentityDocument'])
        ->name('organizations.applications.identity-documents.review');
    Route::get('/organizations/{organization}/applications/{application}/identity-documents/{identityDocument}/download', [OrganizationApplicationController::class, 'downloadIdentityDocument'])
        ->name('organizations.applications.identity-documents.download');
    Route::match(['post', 'patch'], '/organizations/{organization}/applications/{application}/agreement', [RentalAgreementController::class, 'upsert'])
        ->name('organizations.applications.agreement.upsert');
    Route::post('/organizations/{organization}/applications/{application}/agreement/approve', [RentalAgreementController::class, 'approveOrganization'])
        ->name('organizations.applications.agreement.approve');
    Route::post('/organizations/{organization}/bookings/{booking}/confirm-payment', [RentalAgreementController::class, 'confirmPayment'])
        ->name('organizations.bookings.confirm-payment');
    Route::patch('/organizations/{organization}/invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])
        ->name('organizations.invoices.paid');
    Route::get('/organizations/{organization}/invoices/{invoice}/payment-evidence', [InvoiceController::class, 'downloadEvidence'])
        ->name('organizations.invoices.payment-evidence.download');
    Route::patch('/organizations/{organization}/invoices/{invoice}/reverse', [InvoiceController::class, 'reverse'])
        ->name('organizations.invoices.reverse');
    Route::get('/organizations/{organization}/applications/{application}/agreement/download', [RentalAgreementController::class, 'downloadOrganization'])
        ->name('organizations.applications.agreement.download');

    Route::scopeBindings()->group(function () {
        Route::get('/organizations/{organization}/properties', [OrganizationPropertyController::class, 'index'])
            ->name('organizations.properties.index');
        Route::post('/organizations/{organization}/properties', [OrganizationPropertyController::class, 'store'])
            ->name('organizations.properties.store');
        Route::get('/organizations/{organization}/properties/{property}', [OrganizationPropertyController::class, 'show'])
            ->name('organizations.properties.show');
        Route::patch('/organizations/{organization}/properties/{property}', [OrganizationPropertyController::class, 'update'])
            ->name('organizations.properties.update');
        Route::delete('/organizations/{organization}/properties/{property}', [OrganizationPropertyController::class, 'destroy'])
            ->name('organizations.properties.destroy');
        Route::get('/organizations/{organization}/properties/{property}/listing', [OwnerListingController::class, 'show'])
            ->name('organizations.properties.listing.show');
        Route::post('/organizations/{organization}/properties/{property}/listing/submit', [OwnerListingController::class, 'submit'])
            ->name('organizations.properties.listing.submit');
        Route::post('/organizations/{organization}/properties/{property}/listing/pause', [OwnerListingController::class, 'pause'])
            ->name('organizations.properties.listing.pause');

        Route::post('/organizations/{organization}/properties/{property}/units', [PropertyUnitController::class, 'store'])
            ->name('organizations.properties.units.store');
        Route::patch('/organizations/{organization}/properties/{property}/units/{unit}', [PropertyUnitController::class, 'update'])
            ->name('organizations.properties.units.update');
        Route::delete('/organizations/{organization}/properties/{property}/units/{unit}', [PropertyUnitController::class, 'destroy'])
            ->name('organizations.properties.units.destroy');

        Route::post('/organizations/{organization}/properties/{property}/photos', [PropertyPhotoController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('organizations.properties.photos.store');
        Route::patch('/organizations/{organization}/properties/{property}/photos/order', [PropertyPhotoController::class, 'reorder'])
            ->name('organizations.properties.photos.reorder');
        Route::get('/organizations/{organization}/properties/{property}/photos/{photo}/{variant}', [PropertyPhotoController::class, 'show'])
            ->whereIn('variant', ['full', 'thumbnail'])
            ->name('organizations.properties.photos.show');
        Route::delete('/organizations/{organization}/properties/{property}/photos/{photo}', [PropertyPhotoController::class, 'destroy'])
            ->name('organizations.properties.photos.destroy');
    });
});

Route::middleware(['auth', 'verified', 'platform-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/listings', [AdminListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/{listing:slug}', [AdminListingController::class, 'show'])->name('listings.show');
    Route::post('/listings/{listing:slug}/approve', [AdminListingController::class, 'approve'])->name('listings.approve');
    Route::post('/listings/{listing:slug}/reject', [AdminListingController::class, 'reject'])->name('listings.reject');
});
