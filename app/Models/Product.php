<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $primaryKey = 'product_id';
    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'product_name',
        'description',
        'image_url',
        'price',
        'is_available',
        'special_notes',
        'created_at',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'category_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_id', 'product_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'product_id', 'product_id');
    }
}