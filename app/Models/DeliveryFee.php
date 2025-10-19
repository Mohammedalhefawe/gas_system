<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryFee extends Model
{
    use HasFactory;

    protected $table = 'delivery_fees';
    protected $primaryKey = 'fee_id';
    public $timestamps = false;

    protected $fillable = [
        'fee',   
        'date',   
    ];
}
