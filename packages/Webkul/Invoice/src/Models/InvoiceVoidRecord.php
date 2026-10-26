<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;

/** Read-only presentation of an audited invoice correction or cancellation. */
class InvoiceVoidRecord extends Model
{
    protected $table = 'crm_data_corrections';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = ['created_at' => 'datetime', 'snapshot' => 'array', 'result' => 'array'];

    protected static function booted(): void
    {
        static::saving(fn () => throw new \LogicException('Riwayat koreksi tidak dapat diubah.'));
        static::deleting(fn () => throw new \LogicException('Riwayat koreksi tidak dapat dihapus.'));
    }
}
