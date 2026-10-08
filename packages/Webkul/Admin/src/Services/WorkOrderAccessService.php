<?php

namespace Webkul\Admin\Services;

class WorkOrderAccessService
{
    public function user()
    {
        $user = auth()->guard('user')->user();

        abort_unless($user, 403);

        $user->loadMissing('role');

        return $user;
    }

    public function roleName($user): string
    {
        return strtolower(trim((string) ($user->role?->name ?? '')));
    }

    public function canView($user): bool
    {
        return $this->hasPermission($user, 'work-orders.view');
    }

    public function canEditSpk($user): bool
    {
        return $this->hasPermission($user, 'work-orders.edit');
    }

    public function canGenerateSpk($user): bool
    {
        return $this->hasPermission($user, 'work-orders.generate');
    }

    public function canPrint($user): bool
    {
        return $this->hasPermission($user, 'work-orders.print');
    }

    public function canUpdateStatus($user): bool
    {
        return $this->hasPermission($user, 'work-orders.status');
    }

    public function canGenerateDeliveryOrder($user): bool
    {
        return $this->hasPermission($user, 'work-orders.delivery-orders');
    }

    public function assertView($user): void
    {
        abort_unless($this->canView($user), 403);
    }

    public function assertEditSpk($user): void
    {
        abort_unless($this->canEditSpk($user), 403);
    }

    public function assertGenerateSpk($user): void
    {
        abort_unless($this->canGenerateSpk($user), 403);
    }

    public function assertPrint($user): void
    {
        abort_unless($this->canPrint($user), 403);
    }

    public function assertUpdateStatus($user): void
    {
        abort_unless($this->canUpdateStatus($user), 403);
    }

    public function assertGenerateDeliveryOrder($user): void
    {
        abort_unless($this->canGenerateDeliveryOrder($user), 403);
    }

    protected function hasPermission($user, string $permission): bool
    {
        if (! $user?->role) {
            return false;
        }

        return $user->role->permission_type === 'all'
            || $user->hasPermission($permission);
    }
}
