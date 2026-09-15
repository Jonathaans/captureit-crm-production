<?php

namespace Webkul\Admin\Http\Controllers\User;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Notifications\User\UserResetPassword;
use Webkul\Admin\Services\AclLandingPageService;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    public function __construct(
        protected AclLandingPageService $landingPage,
    ) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (auth()->guard('user')->check()) {
            if ($landingUrl = $this->landingPage->getUrl()) {
                return redirect()->to($landingUrl);
            }

            auth()->guard('user')->logout();

            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        session()->forget('url.intended');

        return view('admin::sessions.forgot-password');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        try {
            $this->validate(request(), [
                'email' => 'required|email',
            ]);

            $response = $this->broker()->sendResetLink(request(['email']), function ($user, $token) {
                $user->notify(new UserResetPassword($token));
            });

            if ($response == Password::RESET_LINK_SENT) {
                session()->flash('success', trans('admin::app.users.forget-password.create.reset-link-sent'));

                return back();
            }

            return back()
                ->withInput(request(['email']))
                ->withErrors([
                    'email' => trans('admin::app.users.forget-password.create.email-not-exist'),
                ]);
        } catch (\Exception $exception) {
            session()->flash('error', trans($exception->getMessage()));

            return redirect()->back();
        }
    }

    /**
     * Get the broker to be used during password reset.
     *
     * @return PasswordBroker
     */
    public function broker()
    {
        return Password::broker('users');
    }
}
