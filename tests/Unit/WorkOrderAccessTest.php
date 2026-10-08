<?php

use Webkul\Admin\Services\WorkOrderAccessService;

it('keeps each SPK permission independent of the role name and other actions', function (): void {
    $permissions = [
        'work-orders.view' => 'canView',
        'work-orders.edit' => 'canEditSpk',
        'work-orders.generate' => 'canGenerateSpk',
        'work-orders.print' => 'canPrint',
        'work-orders.status' => 'canUpdateStatus',
        'work-orders.delivery-orders' => 'canGenerateDeliveryOrder',
    ];
    $access = new WorkOrderAccessService;

    foreach (['Warehouse Staff', 'Head Warehouse', 'Sales User', 'Administrator', 'Custom Operations'] as $name) {
        foreach ($permissions as $granted => $method) {
            $user = new class($name, $granted)
            {
                public object $role;

                public function __construct(string $name, string $permission)
                {
                    $this->role = (object) ['name' => $name, 'permission_type' => 'custom', 'permissions' => [$permission]];
                }

                public function hasPermission(string $permission): bool
                {
                    return in_array($permission, $this->role->permissions, true);
                }
            };

            foreach ($permissions as $permission => $check) {
                expect($access->$check($user))->toBe($permission === $granted);
            }
        }
    }
});

it('honors full access while rejecting users without a role', function (): void {
    $access = new WorkOrderAccessService;
    $admin = (object) ['role' => (object) ['permission_type' => 'all']];

    foreach (['canView', 'canEditSpk', 'canGenerateSpk', 'canPrint', 'canUpdateStatus', 'canGenerateDeliveryOrder'] as $method) {
        expect($access->$method($admin))->toBeTrue();
        expect($access->$method((object) ['role' => null]))->toBeFalse();
        expect($access->$method(null))->toBeFalse();
    }
});
