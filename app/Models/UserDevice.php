<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory;

    protected $table = 'user_devices';
    protected $primaryKey = 'device_id';
    public $timestamps = true; 

    protected $fillable = [
        'user_id',
        'device_token',
        'device_type',
        'app_version',
        'last_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}