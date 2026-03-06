<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope('user', function ($builder) {
            if (auth()->check()) {
                $builder->whereHas('categorie', function ($q) {
                    $q->where('user_id', auth()->id());
                });
            }
        });
    }

    protected $fillable = [
        'categorie_id',
        'nom',
        'reference',
        'unite',
        'prix_achat',
        'prix_vente',
        'cout_livraison',
        'qte_actuelle',
        'qte_vide',
        'seuil_alerte',
        'photo_url',
        'statut'
    ];

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    public function ligneVentes()
    {
        return $this->hasMany(LigneVente::class);
    }
}
