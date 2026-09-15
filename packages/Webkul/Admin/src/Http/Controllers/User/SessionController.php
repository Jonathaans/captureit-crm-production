<?php

namespace Webkul\Admin\Http\Controllers\User;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\AclLandingPageService;

class SessionController extends Controller
{
    public function __construct(
        protected AclLandingPageService $landingPage,
    ) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(): RedirectResponse|View
    {
        if (auth()->guard('user')->check()) {
            if ($landingUrl = $this->landingPage->getUrl()) {
                return redirect()->to($landingUrl);
            }

            $this->invalidateUserSession();

            session()->flash('error', trans('admin::app.users.not-permission'));

            return redirect()->route('admin.session.create');
        }

        session()->forget('url.intended');

        return view('admin::sessions.login');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): RedirectResponse
    {
        $this->validate(request(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! auth()->guard('user')->attempt(request(['email', 'password']), request('remember'))) {
            session()->flash('error', trans('admin::app.users.login-error'));

            return redirect()->back();
        }

        if (auth()->guard('user')->user()->status == 0) {
            $this->invalidateUserSession();

            session()->flash('warning', trans('admin::app.users.activate-warning'));

            return redirect()->route('admin.session.create');
        }

        request()->session()->regenerate();
        request()->session()->forget('url.intended');

        if ($landingUrl = $this->landingPage->getUrl()) {
            return redirect()->to($landingUrl);
        }

        $this->invalidateUserSession();

        session()->flash('error', trans('admin::app.users.not-permission'));

        return redirect()->route('admin.session.create');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(): RedirectResponse
    {
        $this->invalidateUserSession();

        return redirect()->route('admin.session.create');
    }

    /**
     * Logout and remove all state belonging to the previous account.
     */
    protected function invalidateUserSession(): void
    {
        auth()->guard('user')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
