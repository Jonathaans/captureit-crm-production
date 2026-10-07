<?php

use Tests\Support\DeliveryOrderEquipmentScenarios;

it('adds requirements after scanning without losing allocations and releases only when additions are complete', fn () => DeliveryOrderEquipmentScenarios::run('editAfterScan'));
it('rejects edits that invalidate allocated equipment and rolls back every row', fn () => DeliveryOrderEquipmentScenarios::run('allocatedItemGuards'));
it('allows unallocated edits and per-item reset while preserving mandatory manual selections', fn () => DeliveryOrderEquipmentScenarios::run('unallocatedAndManualItems'));
it('protects colleague edits and rechecks order state under the lock', fn () => DeliveryOrderEquipmentScenarios::run('staleFormsAndOrderState'));
it('uses current requirements for scans and quantity reservations after editing', fn () => DeliveryOrderEquipmentScenarios::run('staleAllocationRequirements'));
it('rolls back existing equipment edits if a later new row fails to save', fn () => DeliveryOrderEquipmentScenarios::run('editRollback'));
