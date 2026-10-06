<?php

use Tests\Support\CrmCorrectionScenarios;

it('archives wrong payments, reduces period cash and releases quote billing without reusing the number', fn () => CrmCorrectionScenarios::run('invoiceCorrection'));
it('rejects changed invoice data and linked operational documents', fn () => CrmCorrectionScenarios::run('invoiceGuards'));
it('rolls back all financial changes if removal fails', fn () => CrmCorrectionScenarios::run('invoiceRollback'));
it('renumbers colliding SKUs and EAV values while preserving document snapshots and stock', fn () => CrmCorrectionScenarios::run('catalogCorrection'));
it('rejects stale catalogs and incompatible inventory tracking', fn () => CrmCorrectionScenarios::run('catalogGuards'));
it('rolls back SKUs and templates together on a late failure', fn () => CrmCorrectionScenarios::run('catalogRollback'));
it('creates a missing inventory master only from an explicit mapping and never invents stock', fn () => CrmCorrectionScenarios::run('catalogNewMaster'));
