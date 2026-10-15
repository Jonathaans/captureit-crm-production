<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Invoice\Contracts\ExpenseCategory as ExpenseCategoryContract;

class ExpenseCategory extends Model implements ExpenseCategoryContract
{
    protected $fillable = ['code', 'name', 'normalized_name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
