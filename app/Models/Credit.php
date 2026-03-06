<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Credit extends Model
{
    protected $fillable = [
        'vente_id',
        'client_nom',
        'client_telephone',
        'montant_total',
        'montant_paye',
        'statut',
        'echeance',
        'notes',
    ];

    protected $casts = [
        'echeance' => 'date',
        'montant_total' => 'decimal:2',
        'montant_paye' => 'decimal:2',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementCredit::class);
    }
}
