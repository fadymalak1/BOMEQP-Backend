<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ACCDocument extends Model
{
    use HasFactory;

    protected $table = 'acc_documents';

    protected $fillable = [
        'acc_id',
        'document_type',
        'document_url',
        'uploaded_at',
        'verified',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

