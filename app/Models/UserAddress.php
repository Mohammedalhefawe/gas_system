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
        'user_id',
        'address',
        'city',
        'latitude',
        'longitude',
        'is_default',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}