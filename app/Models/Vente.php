<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vente extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope('user', function ($builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    protected $fillable = [
        'reference',
        'user_id',
        'client_id',
        'type',
        'total',
        'avec_livraison',
        'frais_livraison',
        'montant_recu',
        'monnaie_rendue',
        'statut'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lignes()
    {
        return $this->hasMany(LigneVente::class);
    }

    public function credit()
    {
        return $this->hasOne(Credit::class);
    }
}
