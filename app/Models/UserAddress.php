<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;

    protected $table = 'user_addresses';
    protected $primaryKey = 'address_id';
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 
        'address',
        'city',
        'latitude',
        'longitude',
        'is_default',
        'created_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
