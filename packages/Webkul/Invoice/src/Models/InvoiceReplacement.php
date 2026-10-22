<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceReplacement extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['before' => 'array', 'created_at' => 'datetime'];
}
