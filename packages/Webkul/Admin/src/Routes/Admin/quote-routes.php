<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Quote\QuoteController;

Route::controller(QuoteController::class)
    ->prefix('quotes')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Quote Listing
        |--------------------------------------------------------------------------
        */

        Route::get('', 'index')
            ->name('admin.quotes.index');

        /*
        |--------------------------------------------------------------------------
        | Search & Lead Products
        |--------------------------------------------------------------------------
        */

        Route::get('search', 'search')
            ->name('admin.quotes.search');

        Route::get('lead-products/{lead_id}', 'leadProducts')
            ->name('admin.quotes.lead_products');

        Route::get('bill-to-people', 'billToPeople')
            ->name('admin.quotes.bill_to_people');

        Route::get('bill-to-person', 'billToPerson')
            ->name('admin.quotes.bill_to_person');

        /*
        |--------------------------------------------------------------------------
        | Create Quote
        |--------------------------------------------------------------------------
        */

        Route::get('create/{lead_id?}', 'create')
            ->name('admin.quotes.create');

        Route::post('create', 'store')
            ->name('admin.quotes.store');

        /*
        |--------------------------------------------------------------------------
        | Edit Quote
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Parameter {id?} pada GET edit sengaja OPTIONAL.
        |
        | View Lead > Quotes bawaan Krayin membangun URL seperti:
        |
        | route('admin.quotes.edit') + '/' + quote.id
        |
        | Kalau diubah menjadi {id} wajib, halaman Lead akan error saat
        | Blade dirender karena route() dipanggil tanpa parameter id.
        |
        */

        Route::get('edit/{id?}', 'edit')
            ->name('admin.quotes.edit');

        Route::put('edit/{id}', 'update')
            ->name('admin.quotes.update');

        /*
        |--------------------------------------------------------------------------
        | Print Quote
        |--------------------------------------------------------------------------
        |
        | Sama seperti Edit, {id?} harus tetap OPTIONAL karena view
        | Lead > Quotes menggunakan route('admin.quotes.print') sebagai
        | base URL lalu menambahkan quote.id melalui Vue.
        |
        */

        Route::get('print/{id?}', 'print')
            ->name('admin.quotes.print');

        /*
        |--------------------------------------------------------------------------
        | Mass Delete
        |--------------------------------------------------------------------------
        */

        Route::post('mass-destroy', 'massDestroy')
            ->name('admin.quotes.mass_delete');

        /*
        |--------------------------------------------------------------------------
        | Delete Quote
        |--------------------------------------------------------------------------
        */

        Route::delete('{id}', 'destroy')
            ->name('admin.quotes.delete');
    });
