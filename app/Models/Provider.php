<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    use HasFactory;

    protected $table = 'providers';
    protected $primaryKey = 'provider_id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'sector_id',
        'full_name',
        'is_available',
        'blocked',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'blocked' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id', 'sector_id');
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'provider_products', 'provider_id', 'product_id')
            ->withPivot('is_available')
            ->withTimestamps();
    }
}
