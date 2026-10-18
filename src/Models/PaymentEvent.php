<?php

namespace Vmt\PayFast\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    protected $table = 'payfast_events';

    public $timestamps = false;

    protected $guarded = ['*'];
}
