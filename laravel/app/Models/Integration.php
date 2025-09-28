<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'provider',
        'shop',
        'access_token',
        'settings',
        'active'
    ];

    protected $casts = [
        'settings' => 'array',
        'active' => 'boolean',
        'access_token' => 'encrypted'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function isShopify()
    {
        return $this->provider === 'shopify';
    }

    public function isWordPress()
    {
        return in_array($this->provider, ['wordpress', 'woocommerce']);
    }
}
