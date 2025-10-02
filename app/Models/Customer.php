<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';
    protected $primaryKey = 'customer_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'full_name',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id', 'customer_id');
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class, 'customer_id', 'customer_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'customer_id', 'customer_id');
    }
}
