<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementCredit extends Model
{
    protected $fillable = [
        'credit_id',
        'montant',
        'mode_paiement',
        'notes',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }
}
