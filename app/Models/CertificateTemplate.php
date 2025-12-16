<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'acc_id',
        'category_id',
        'name',
        'template_html',
        'template_variables',
        'background_image_url',
        'logo_positions',
        'signature_positions',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'template_variables' => 'array',
            'logo_positions' => 'array',
            'signature_positions' => 'array',
        ];
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }
}

