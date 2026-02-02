<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ACCMaterial extends Model
{
    use HasFactory;

    protected $table = 'acc_materials';

    protected $fillable = [
        'acc_id',
        'course_id',
        'material_type',
        'name',
        'description',
        'price',
        'file_url',
        'preview_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(TrainingCenterPurchase::class, 'item_id')->where('purchase_type', 'material');
    }
}

