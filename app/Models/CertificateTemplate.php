<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'acc_id',
        'template_type',
        'category_id',
        'course_id',
        'name',
        'template_html',
        'background_image_url',
        'logo_positions',
        'signature_positions',
        'config_json',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'logo_positions' => 'array',
            'signature_positions' => 'array',
            'config_json' => 'array',
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

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'certificate_template_course', 'certificate_template_id', 'course_id')
            ->withTimestamps();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }
}

