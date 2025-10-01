<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    protected $primaryKey = 'order_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'driver_id',
        'address_id',
        'total_amount',
        'delivery_fee',
        'order_status',
        'delivery_address',
        'order_date',
        'delivery_date',
        'delivery_time',
        'payment_method',
        'payment_status',
        'special_instructions',
        'rating',
        'review',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'driver_id');
    }

    public function address()
    {
        return $this->belongsTo(UserAddress::class, 'address_id', 'address_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'related_order_id', 'order_id');
    }
}
