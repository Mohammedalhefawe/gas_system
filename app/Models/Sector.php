<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory;

    protected $table = 'sectors';
    protected $primaryKey = 'sector_id';
    public $timestamps = true;

    protected $fillable = [
        'sector_name',
        'areas',   // JSON
        'polygon', // JSON
        'is_active',
        'delivery_fee',
    ];

    protected $casts = [
        'areas' => 'array',
        'polygon' => 'array',
        'is_active' => 'boolean',
        'delivery_fee' => 'decimal:2', // optional, ensures decimal format

    ];

    // Relations
    public function providers()
    {
        return $this->hasMany(Provider::class, 'sector_id', 'sector_id');
    }
}
