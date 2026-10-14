<?php

namespace Webkul\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Webkul\User\Models\UserProxy;

class PaymentVoidRecord extends Model
{
    protected $table = 'payment_voids';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = ['snapshot' => 'array', 'result' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(fn () => throw new LogicException('Payment correction history is read-only.'));
        static::deleting(fn () => throw new LogicException('Payment correction history is read-only.'));
    }

    public function actor()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'actor_id');
    }
}
