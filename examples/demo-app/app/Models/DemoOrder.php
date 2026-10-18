<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DemoOrder extends Model
{
    protected $guarded = ['*'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'completion_count' => 'integer'];
    }
}
