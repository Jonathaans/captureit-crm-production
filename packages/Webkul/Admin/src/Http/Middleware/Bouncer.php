<?php

namespace Webkul\Admin\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class Bouncer
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, \Closure $next, $guard = 'user')
    {
        if (! auth()->guard($guard)->check()) {
            return redirect()->route('admin.session.create');
        }

        /**
         * If user status is changed by admin. Then session should be
         * logged out.
         */
        if (! (bool) auth()->guard($guard)->user()->status) {
            auth()->guard($guard)->logout();

            session()->flash('error', trans('admin::app.errors.401'));

            return redirect()->route('admin.session.create');
        }

        /**
         * If somehow the user deleted all permissions, then it should be
         * auto logged out and need to contact the administrator again.
         */
        if ($this->isPermissionsEmpty()) {
            auth()->guard($guard)->logout();

            session()->flash('error', trans('admin::app.errors.401'));

            return redirect()->route('admin.session.create');
        }

        return $next($request);
    }

    /**
     * Check for user, if they have empty permissions or not except admin.
     *
     * @return bool
     */
    public function isPermissionsEmpty()
    {
        if (! $role = auth()->guard('user')->user()->role) {
            abort(401, 'This action is unauthorized.');
        }

        if ($role->permission_type === 'all') {
            return false;
        }

        if ($role->permission_type !== 'all' && empty($role->permissions)) {
            return true;
        }

        $this->checkIfAuthorized();

        return false;
    }

    /**
     * Check authorization.
     *
     * @return null
     */
    public function checkIfAuthorized()
    {
        $route = Route::currentRouteName();

        if ($route === 'admin.mail.index') {
            bouncer()->allow('mail.'.(request()->route('route') ?: 'inbox'));

            return;
        }

        $quickPermission = config('acl_routes.quick_create', [])[$route] ?? null;

        if ($quickPermission && request()->filled('quick_add') && bouncer()->hasPermission($quickPermission)) {
            return;
        }

        if (in_array($route, config('acl_routes.authenticated', []), true)) {
            return;
        }

        $roles = acl()->getRoles();
        $permission = $roles->get($route) ?? (config('acl_routes.permissions', [])[$route] ?? null);

        if (in_array($route, ['admin.settings.attributes.lookup', 'admin.settings.attributes.lookup_entity'], true)) {
            $permission = config('acl_routes.lookups', [])[request()->route('lookup')] ?? null;
        }

        abort_unless(
            $permission && collect((array) $permission)->contains(fn ($key) => bouncer()->hasPermission($key)),
            401,
            'This action is unauthorized.'
        );
    }
}
