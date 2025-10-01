<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Driver extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $table = 'drivers';
    protected $primaryKey = 'driver_id';
    public $timestamps = false;

    protected $fillable = [
        'full_name',
        'phone_number',
        'vehicle_type',
        'license_number',
        'password',
        'is_available',
        'current_location',
        'rating',
        'max_capacity',
    ];
    
    protected $hidden = [
        'password',
    ];

    // Implement JWTSubject methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'driver_id', 'driver_id');
    }
}
