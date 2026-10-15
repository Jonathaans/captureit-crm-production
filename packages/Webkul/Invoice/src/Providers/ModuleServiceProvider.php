<?php

namespace Webkul\Invoice\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Invoice\Models\DeliveryOrder;
use Webkul\Invoice\Models\DeliveryOrderItem;
use Webkul\Invoice\Models\Expense;
use Webkul\Invoice\Models\ExpenseCategory;
use Webkul\Invoice\Models\Invoice;
use Webkul\Invoice\Models\InvoiceItem;
use Webkul\Invoice\Models\Payment;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Invoice::class,
        InvoiceItem::class,
        Payment::class,
        Expense::class,
        ExpenseCategory::class,
        DeliveryOrder::class,
        DeliveryOrderItem::class,
    ];
}
