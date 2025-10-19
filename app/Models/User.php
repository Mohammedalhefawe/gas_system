<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $fillable = [
        'phone_number',
        'password',
        'is_verified',
        'verification_pin',
        'pin_expires_at',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'verification_pin',
        'pin_expires_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    // Relations
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function driver()
    {
        return $this->hasOne(Driver::class, 'user_id', 'user_id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'user_id', 'user_id');
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id', 'user_id');
    }

    // JWTSubject methods
    public function getJWTIdentifier()
    {
        return $this->getKey(); // عادة user_id
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
