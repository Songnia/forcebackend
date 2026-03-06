<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LigneVente extends Model
{
    use HasFactory;

    protected $fillable = [
        'vente_id',
        'article_id',
        'quantite',
        'prix_unitaire',
        'prix_achat_moment',
        'benefice_unitaire'
    ];

    public function vente()
    {
        return $this->belongsTo(Vente::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
