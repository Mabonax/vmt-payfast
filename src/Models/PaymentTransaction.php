<?php

namespace Vmt\PayFast\Models;

use Illuminate\Database\Eloquent\Model;
use Vmt\PayFast\Enums\PaymentStatus;

class PaymentTransaction extends Model
{
    protected $table = 'payfast_transactions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected $hidden = ['credential_snapshot'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'status' => PaymentStatus::class, 'metadata' => 'array', 'credential_snapshot' => 'encrypted:array', 'completed_at' => 'datetime'];
    }
}
