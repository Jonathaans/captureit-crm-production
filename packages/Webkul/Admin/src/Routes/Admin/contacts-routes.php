<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Contact\OrganizationController;
use Webkul\Admin\Http\Controllers\Contact\Persons\ActivityController;
use Webkul\Admin\Http\Controllers\Contact\Persons\PersonController;
use Webkul\Admin\Http\Controllers\Contact\Persons\TagController;

Route::prefix('contacts')->group(function () {
    /**
     * Persons routes.
     */
    Route::controller(PersonController::class)->prefix('persons')->group(function () {
        Route::get('', 'index')->name('admin.contacts.persons.index');

        Route::get('create', 'create')->name('admin.contacts.persons.create');

        Route::post('create', 'store')->name('admin.contacts.persons.store');

        Route::get('view/{id}', 'show')->name('admin.contacts.persons.view');

        Route::get(
            '{id}/ktp-document',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'personKtp']
        )
            ->name('admin.contacts.persons.ktp');

        Route::get('edit/{id}', 'edit')->name('admin.contacts.persons.edit');

        Route::get(
            'identity-document/{id}',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'editPerson']
        )->name('admin.contacts.persons.identity');

        Route::post(
            'identity-document/{id}',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'updatePerson']
        )->name('admin.contacts.persons.identity.update');

        Route::get(
            'identity-document/{id}/file',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'personKtp']
        )->name('admin.contacts.persons.identity-document.file');

        Route::put('edit/{id}', 'update')->name('admin.contacts.persons.update');

        Route::get('search', 'search')->name('admin.contacts.persons.search');

        Route::middleware(['throttle:100,60'])->delete('{id}', 'destroy')->name('admin.contacts.persons.delete');

        Route::post('mass-destroy', 'massDestroy')->name('admin.contacts.persons.mass_delete');

        /**
         * Tag routes.
         */
        Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
            Route::post('', 'attach')->name('admin.contacts.persons.tags.attach');

            Route::delete('', 'detach')->name('admin.contacts.persons.tags.detach');
        });

        /**
         * Activity routes.
         */
        Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
            Route::get('', 'index')->name('admin.contacts.persons.activities.index');
        });
    });

    /**
     * Organization routes.
     */
    Route::controller(OrganizationController::class)->prefix('organizations')->group(function () {
        Route::get('', 'index')->name('admin.contacts.organizations.index');

        Route::get('create', 'create')->name('admin.contacts.organizations.create');

        Route::post('create', 'store')->name('admin.contacts.organizations.store');

        Route::get('edit/{id?}', 'edit')->name('admin.contacts.organizations.edit');

        Route::get(
            'identity-document/{id}',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'editOrganization']
        )->name('admin.contacts.organizations.identity');

        Route::post(
            'identity-document/{id}',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'updateOrganization']
        )->name('admin.contacts.organizations.identity.update');

        Route::get(
            'identity-document/{id}/file',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'organizationNpwp']
        )->name('admin.contacts.organizations.npwp');

        Route::get(
            '{id}/npwp-document',
            [\Webkul\Admin\Http\Controllers\Contact\ContactIdentityDocumentController::class, 'organizationNpwp']
        )
            ->name('admin.contacts.organizations.npwp-document.file');

        Route::put('edit/{id}', 'update')->name('admin.contacts.organizations.update');

        Route::delete('{id}', 'destroy')->name('admin.contacts.organizations.delete');

        Route::put('mass-destroy', 'massDestroy')->name('admin.contacts.organizations.mass_delete');
    });
});
