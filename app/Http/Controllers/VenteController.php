<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VenteController extends Controller
{
    public function index()
    {
        return response()->json(Vente::with('lignes.article', 'user', 'credit')->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string|unique:ventes',
            'client_id' => 'nullable|integer',
            'type' => 'required|in:comptant,credit',
            'total' => 'required|numeric',
            'avec_livraison' => 'sometimes|boolean',
            'frais_livraison' => 'sometimes|numeric',
            'montant_recu' => 'required|numeric',
            'monnaie_rendue' => 'required|numeric',
            'lignes' => 'required|array|min:1',
            'lignes.*.article_id' => 'required|exists:articles,id',
            'lignes.*.quantite' => 'required|numeric|min:0.01',
            'lignes.*.prix_unitaire' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            $vente = Vente::create([
                'reference' => $validated['reference'],
                'user_id' => $request->user()->id,
                'client_id' => $validated['client_id'] ?? null,
                'type' => $validated['type'],
                'total' => $validated['total'],
                'avec_livraison' => $validated['avec_livraison'] ?? false,
                'frais_livraison' => $validated['frais_livraison'] ?? 0,
                'montant_recu' => $validated['montant_recu'],
                'monnaie_rendue' => $validated['monnaie_rendue'],
                'statut' => 'complétée',
            ]);

            foreach ($validated['lignes'] as $ligne) {
                $article = Article::lockForUpdate()->find($ligne['article_id']);
                
                $benefice = max(0, ($ligne['prix_unitaire'] - $article->prix_achat) * $ligne['quantite']);
                
                $vente->lignes()->create([
                    'article_id' => $article->id,
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'prix_achat_moment' => $article->prix_achat,
                    'benefice_unitaire' => $benefice / ($ligne['quantite'] > 0 ? $ligne['quantite'] : 1),
                ]);

                // Decrement stock and increment empty stock
                $article->decrement('qte_actuelle', $ligne['quantite']);
                $article->increment('qte_vide', $ligne['quantite']);
            }

            DB::commit();
            return response()->json($vente->load('lignes.article'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de l\'enregistrement de la vente: ' . $e->getMessage()], 500);
        }
    }

    public function show(Vente $vente)
    {
        return response()->json($vente->load('lignes.article', 'user'));
    }
}
