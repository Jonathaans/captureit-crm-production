<?php

namespace Webkul\Admin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\Http\Controllers\UserEmail\MyEmailComposeController;

class UserEmailComposeServiceProvider extends ServiceProvider
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
                        'my-email/compose',
                        [
                            MyEmailComposeController::class,
                            'create',
                        ]
                    )->name(
                        'admin.my-email.compose'
                    );

                    Route::post(
                        'my-email/send',
                        [
                            MyEmailComposeController::class,
                            'send',
                        ]
                    )->name(
                        'admin.my-email.send'
                    );

                    Route::get(
                        'my-email/sent',
                        [
                            MyEmailComposeController::class,
                            'sent',
                        ]
                    )->name(
                        'admin.my-email.sent'
                    );

                    Route::get(
                        'my-email/sent/{id}',
                        [
                            MyEmailComposeController::class,
                            'showSent',
                        ]
                    )->name(
                        'admin.my-email.sent.show'
                    );
                }
            );
    }
}
