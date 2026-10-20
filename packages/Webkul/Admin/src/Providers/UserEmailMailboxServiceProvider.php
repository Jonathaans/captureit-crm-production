<?php

namespace Webkul\Admin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Http\Controllers\UserEmail\MyEmailAttachmentController;
use Webkul\Admin\Http\Controllers\UserEmail\MyEmailFolderController;

class UserEmailMailboxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix('admin')
            ->group(
                function () {
                    Route::get(
                        'my-email/drafts',
                        [
                            MyEmailFolderController::class,
                            'drafts',
                        ]
                    )->name(
                        'admin.my-email.drafts'
                    );

                    Route::get(
                        'my-email/drafts/{id}/edit',
                        [
                            MyEmailFolderController::class,
                            'editDraft',
                        ]
                    )->name(
                        'admin.my-email.drafts.edit'
                    );

                    Route::post(
                        'my-email/drafts',
                        [
                            MyEmailFolderController::class,
                            'saveDraft',
                        ]
                    )->name(
                        'admin.my-email.drafts.save'
                    );

                    Route::get(
                        'my-email/outbox',
                        [
                            MyEmailFolderController::class,
                            'outbox',
                        ]
                    )->name(
                        'admin.my-email.outbox'
                    );

                    Route::post(
                        'my-email/outbox/{id}/retry',
                        [
                            MyEmailFolderController::class,
                            'retry',
                        ]
                    )->name(
                        'admin.my-email.outbox.retry'
                    );

                    Route::get(
                        'my-email/trash',
                        [
                            MyEmailFolderController::class,
                            'trash',
                        ]
                    )->name(
                        'admin.my-email.trash'
                    );

                    Route::post(
                        'my-email/messages/{id}/trash',
                        [
                            MyEmailFolderController::class,
                            'moveToTrash',
                        ]
                    )->name(
                        'admin.my-email.trash.move'
                    );

                    Route::post(
                        'my-email/trash/{id}/restore',
                        [
                            MyEmailFolderController::class,
                            'restore',
                        ]
                    )->name(
                        'admin.my-email.trash.restore'
                    );

                    Route::delete(
                        'my-email/trash/{id}',
                        [
                            MyEmailFolderController::class,
                            'destroy',
                        ]
                    )->name(
                        'admin.my-email.trash.destroy'
                    );

                    Route::get(
                        'my-email/attachments/{id}/download',
                        [
                            MyEmailAttachmentController::class,
                            'download',
                        ]
                    )->name(
                        'admin.my-email.attachments.download'
                    );
                }
            );
    }
}
