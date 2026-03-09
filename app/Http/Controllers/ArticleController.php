<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::with('categorie')->whereHas('categorie', function($q) {
            $q->where('user_id', Auth::id());
        })->get()->map(function($article) {
            $stats = $article->ligneVentes()
                ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
                ->where('ventes.user_id', Auth::id())
                ->selectRaw('SUM(ligne_ventes.quantite) as total_vendu, SUM(ligne_ventes.quantite * ligne_ventes.prix_unitaire) as revenue, SUM(ligne_ventes.quantite * ligne_ventes.benefice_unitaire) as profit')
                ->first();
            
            $article->total_vendu = (float) ($stats->total_vendu ?? 0);
            $article->revenue = (float) ($stats->revenue ?? 0);
            $article->profit = (float) ($stats->profit ?? 0);

            // Today's stats
            $statsToday = $article->ligneVentes()
                ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
                ->where('ventes.user_id', Auth::id())
                ->whereDate('ventes.created_at', Carbon::today())
                ->selectRaw('SUM(ligne_ventes.quantite) as total_vendu, SUM(ligne_ventes.quantite * ligne_ventes.prix_unitaire) as revenue, SUM(ligne_ventes.quantite * ligne_ventes.benefice_unitaire) as profit')
                ->first();
            
            $article->vendu_aujourdhui = (float) ($statsToday->total_vendu ?? 0);
            $article->revenue_aujourdhui = (float) ($statsToday->revenue ?? 0);
            $article->profit_aujourdhui = (float) ($statsToday->profit ?? 0);
            
            return $article;
        });

        return response()->json($articles);
    }

    public function store(Request $request)
    {
        if (!$request->filled('reference')) {
            $request->merge([
                'reference' => 'ART-' . strtoupper(\Illuminate\Support\Str::random(6))
            ]);
        }

        $validated = $request->validate([
            'categorie_id' => 'required|exists:categories,id',
            'nom' => 'required|string|max:255',
            'reference' => 'required|string|unique:articles',
            'unite' => 'required|string',
            'prix_achat' => 'required|numeric',
            'prix_vente' => 'required|numeric',
            'cout_livraison' => 'nullable|numeric|min:0',
            'qte_actuelle' => 'required|numeric',
            'qte_vide' => 'nullable|numeric|min:0',
            'seuil_alerte' => 'required|numeric',
            'photo_url' => 'nullable|string',
            'statut' => 'required|in:actif,archivé',
        ]);

        $article = Article::create($validated);
        return response()->json($article, 201);
    }

    public function show(Article $article)
    {
        return response()->json($article->load('categorie'));
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'categorie_id' => 'sometimes|exists:categories,id',
            'nom' => 'sometimes|string|max:255',
            'reference' => 'sometimes|string|unique:articles,reference,' . $article->id,
            'unite' => 'sometimes|string',
            'prix_achat' => 'sometimes|numeric',
            'prix_vente' => 'sometimes|numeric',
            'cout_livraison' => 'nullable|numeric|min:0',
            'qte_actuelle' => 'sometimes|numeric',
            'qte_vide' => 'nullable|numeric|min:0',
            'seuil_alerte' => 'sometimes|numeric',
            'statut' => 'sometimes|in:actif,archivé',
        ]);

        $article->update($validated);
        return response()->json($article);
    }

    public function destroy(Article $article)
    {
        $article->delete();
        return response()->json(null, 204);
    }
}
