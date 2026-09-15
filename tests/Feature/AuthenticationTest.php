<?php

it('can see the admin login page', function () {
    test()->get(route('admin.session.create'))
        ->assertOK();
});

it('can see the dashboard page after login', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->get(route('admin.dashboard.index'))
        ->assertOK();

    expect(auth()->guard('user')->user()->name)->toBe($admin->name);
});

it('can logout from the admin panel', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->withSession(['url.intended' => route('admin.dashboard.index')])
        ->delete(route('admin.session.destroy'), [
            '_token' => csrf_token(),
        ])
        ->assertRedirect(route('admin.session.create'))
        ->assertSessionMissing('url.intended');

    expect(auth()->guard('user')->user())->toBeNull();
});
