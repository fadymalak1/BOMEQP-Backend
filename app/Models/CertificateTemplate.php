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
        'created_by',
        'is_group_admin_template',
        'template_type',
        'orientation',
        'category_id',
        'course_id',
        'name',
        'template_html',
        'background_image_url',
        'logo_positions',
        'signature_positions',
        'config_json',
        'status',
        'include_card',
        'card_template_html',
        'card_background_image_url',
        'card_config_json',
        'card_back_template_html',
        'card_back_background_image_url',
        'card_back_config_json',
    ];

    protected function casts(): array
    {
        return [
            'logo_positions' => 'array',
            'signature_positions' => 'array',
            'config_json' => 'array',
            'card_config_json' => 'array',
            'card_back_config_json' => 'array',
            'is_group_admin_template' => 'boolean',
            'include_card' => 'boolean',
        ];
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

