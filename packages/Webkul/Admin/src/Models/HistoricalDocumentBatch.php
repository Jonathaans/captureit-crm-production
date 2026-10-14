<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalDocumentBatch extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'result' => 'array', 'expires_at' => 'datetime'];
}
