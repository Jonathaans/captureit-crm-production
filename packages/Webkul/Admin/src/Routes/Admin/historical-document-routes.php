<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Invoice\HistoricalDocumentController;

Route::controller(HistoricalDocumentController::class)->prefix('historical-documents')->group(function () {
    Route::get('/', 'index')->name('admin.historical-documents.index');
    Route::get('create', 'create')->name('admin.historical-documents.create');
    Route::get('template', 'template')->name('admin.historical-documents.template');
    Route::post('preview', 'preview')->name('admin.historical-documents.preview');
    Route::post('upload', 'upload')->name('admin.historical-documents.upload');
    Route::get('{id}', 'show')->whereUuid('id')->name('admin.historical-documents.show');
    Route::post('{id}/map', 'map')->whereUuid('id')->name('admin.historical-documents.map');
    Route::post('{id}/store', 'store')->whereUuid('id')->name('admin.historical-documents.store');
});
