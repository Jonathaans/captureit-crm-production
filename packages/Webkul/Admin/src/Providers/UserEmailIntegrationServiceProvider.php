<?php

namespace Webkul\Admin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Console\Commands\CrmUserEmailSyncCommand;
use Webkul\Admin\Http\Controllers\UserEmail\MyEmailAccountController;
use Webkul\Admin\Http\Controllers\UserEmail\MyEmailInboxController;
use Webkul\Admin\Http\Controllers\UserEmail\UserEmailAdminStatusController;

class UserEmailIntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(
                'admin'
            )
            ->group(
                function () {
                    Route::get(
                        'my-email',
                        [
                            MyEmailInboxController::class,
                            'index',
                        ]
                    )->name(
                        'admin.my-email.inbox'
                    );

                    Route::post(
                        'my-email/sync',
                        [
                            MyEmailInboxController::class,
                            'sync',
                        ]
                    )->name(
                        'admin.my-email.sync'
                    );

                    Route::get(
                        'my-email/messages/{id}',
                        [
                            MyEmailInboxController::class,
                            'show',
                        ]
                    )->name(
                        'admin.my-email.messages.show'
                    );

                    Route::get(
                        'my-email/settings',
                        [
                            MyEmailAccountController::class,
                            'edit',
                        ]
                    )->name(
                        'admin.my-email.settings'
                    );

                    Route::put(
                        'my-email/settings',
                        [
                            MyEmailAccountController::class,
                            'update',
                        ]
                    )->name(
                        'admin.my-email.settings.update'
                    );

                    Route::post(
                        'my-email/test-imap',
                        [
                            MyEmailAccountController::class,
                            'testImap',
                        ]
                    )->name(
                        'admin.my-email.test-imap'
                    );

                    Route::post(
                        'my-email/test-smtp',
                        [
                            MyEmailAccountController::class,
                            'testSmtp',
                        ]
                    )->name(
                        'admin.my-email.test-smtp'
                    );

                    Route::get(
                        'system-control/email-accounts',
                        [
                            UserEmailAdminStatusController::class,
                            'index',
                        ]
                    )->name(
                        'admin.system-control.email-accounts'
                    );
                }
            );

        if (
            $this->app
                ->runningInConsole()
        ) {
            $this->commands([
                CrmUserEmailSyncCommand::class,
            ]);
        }
    }
}
