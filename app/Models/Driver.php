<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $table = 'drivers';
    protected $primaryKey = 'driver_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'license_number',
        'is_available',
        'current_location',
        'rating',
        'max_capacity',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'driver_id', 'driver_id');
    }
}
