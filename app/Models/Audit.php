<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audit extends Model
{
    protected $table = 'audits';

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // Relacionamento com o objeto que foi alterado (User ou Certificado)
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // Quem fez a alteração
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
