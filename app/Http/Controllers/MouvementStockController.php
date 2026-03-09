<?php

namespace App\Http\Controllers;

use App\Models\MouvementStock;
use App\Models\Article;
use App\Models\Vente;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MouvementStockController extends Controller
{
    public function index()
    {
        return response()->json(MouvementStock::with('article', 'user')->latest('date')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'article_id'   => 'required|exists:articles,id',
            'type'         => 'required|in:entrée,sortie,crédit,perte,autre',
            'quantite'     => 'required|numeric|min:0.01',
            'prix_unitaire'=> 'nullable|numeric',
            'fournisseur'  => 'nullable|string',
            'facture_num'  => 'nullable|string',
            'date'         => 'sometimes|date',
            'notes'        => 'nullable|string',
            // Fields for credit creation (optional)
            'client_nom'       => 'nullable|string|max:255',
            'client_telephone'  => 'nullable|string|max:20',
            'montant_paye'     => 'nullable|numeric|min:0',
            'echeance'         => 'nullable|date',
            'avec_livraison'   => 'sometimes|boolean',
        ]);

        $validated['user_id'] = $request->user()->id;

        // Map frontend "sortie", "crédit", "perte", "autre" to DB type "sortie"
        $dbType = $validated['type'] === 'entrée' ? 'entrée' : 'sortie';
        $originalType = $validated['type'];
        $validated['type'] = $dbType;

        // Add type label to notes for traceability
        if ($originalType !== $dbType) {
            $label = strtoupper($originalType);
            $validated['notes'] = "[{$label}] " . ($validated['notes'] ?? '');
        }

        DB::beginTransaction();
        try {
            $mouvement = MouvementStock::create($validated);

            $article = Article::lockForUpdate()->find($validated['article_id']);

            if ($dbType === 'entrée') {
                // Recalcul du PUMP
                $valeur_actuelle = $article->qte_actuelle * $article->prix_achat;
                $valeur_entree = $validated['quantite'] * ($validated['prix_unitaire'] ?? 0);
                $nouvelle_qte = $article->qte_actuelle + $validated['quantite'];
                $nouveau_pump = $nouvelle_qte > 0
                    ? ($valeur_actuelle + $valeur_entree) / $nouvelle_qte
                    : $article->prix_achat;

                $article->update(['qte_actuelle' => $nouvelle_qte, 'prix_achat' => $nouveau_pump]);
                // Approvisionnement : on décrémente les vides
                $article->decrement('qte_vide', $validated['quantite']);
            } else {
                $article->decrement('qte_actuelle', $validated['quantite']);
                // Sortie/Vente/Crédit : on incrémente les vides
                if (in_array($originalType, ['sortie', 'crédit'])) {
                    $article->increment('qte_vide', $validated['quantite']);
                }
            }

            // ─── Create Vente entry for sortie, crédit, perte, autre ──────────
            if ($dbType === 'sortie') {
                $prixUnitaire = $validated['prix_unitaire'] ?? $article->prix_vente;
                $fraisLivraison = ($validated['avec_livraison'] ?? false) ? ($article->cout_livraison * $validated['quantite']) : 0;
                $totalArticles = $prixUnitaire * $validated['quantite'];
                $totalVente = $totalArticles + $fraisLivraison;

                $vente = Vente::create([
                    'reference'       => 'MVT-' . $mouvement->id . '-' . time(),
                    'user_id'         => $request->user()->id,
                    'type'            => $originalType === 'crédit' ? 'credit' : 'comptant',
                    'total'           => $totalVente,
                    'avec_livraison'  => $validated['avec_livraison'] ?? false,
                    'frais_livraison' => $fraisLivraison,
                    'montant_recu'    => $originalType === 'crédit' ? ($validated['montant_paye'] ?? 0) : $totalVente,
                    'monnaie_rendue'  => 0,
                    'statut'          => 'complétée',
                ]);

                $benefice = ($prixUnitaire - $article->prix_achat) * $validated['quantite'];
                $vente->lignes()->create([
                    'article_id'         => $article->id,
                    'quantite'           => $validated['quantite'],
                    'prix_unitaire'      => $prixUnitaire,
                    'prix_achat_moment'  => $article->prix_achat,
                    'benefice_unitaire'  => $validated['quantite'] > 0 ? $benefice / $validated['quantite'] : 0,
                ]);

                // ─── Create Credit entry if type is crédit ────────────────────
                if ($originalType === 'crédit') {
                    $montantPaye = $validated['montant_paye'] ?? 0;
                    Credit::create([
                        'vente_id'         => $vente->id,
                        'client_nom'       => $validated['client_nom'] ?? 'Client Inconnu',
                        'client_telephone' => $validated['client_telephone'] ?? null,
                        'montant_total'    => $totalVente,
                        'montant_paye'     => $montantPaye,
                        'statut'           => $montantPaye >= $totalVente ? 'solde' : 'en_cours',
                        'echeance'         => $validated['echeance'] ?? null,
                        'notes'            => $validated['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();
            return response()->json($mouvement->load('article'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function show(MouvementStock $mouvementStock)
    {
        return response()->json($mouvementStock->load('article', 'user'));
    }

    public function update(Request $request, MouvementStock $mouvementStock)
    {
        $validated = $request->validate([
            'article_id'   => 'sometimes|exists:articles,id',
            'type'         => 'sometimes|in:entrée,sortie,crédit,perte,autre',
            'quantite'     => 'sometimes|numeric|min:0.01',
            'prix_unitaire'=> 'nullable|numeric',
            'date'         => 'sometimes|date',
            'notes'        => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $article = Article::lockForUpdate()->find($mouvementStock->article_id);
            $est_reutilisable = $article->categorie?->est_reutilisable ?? false;

            // 1. REVERT old movement effects
            if ($mouvementStock->type === 'entrée') {
                $article->decrement('qte_actuelle', $mouvementStock->quantite);
                if ($est_reutilisable) $article->increment('qte_vide', $mouvementStock->quantite);
            } else {
                $article->increment('qte_actuelle', $mouvementStock->quantite);
                if ($est_reutilisable) $article->decrement('qte_vide', $mouvementStock->quantite);
            }

            // 2. PREPARE new data
            $newType = $validated['type'] ?? $mouvementStock->type;
            $newQuantite = $validated['quantite'] ?? $mouvementStock->quantite;
            
            // Map newType if it changed
            $dbType = $newType === 'entrée' ? 'entrée' : 'sortie';
            
            // 3. APPLY new movement effects
            if ($dbType === 'entrée') {
                $article->increment('qte_actuelle', $newQuantite);
                if ($est_reutilisable) $article->decrement('qte_vide', $newQuantite);
            } else {
                $article->decrement('qte_actuelle', $newQuantite);
                // For simplified logic, if sortie-like, we assume it's a sale/usage that adds to empty stock
                if ($est_reutilisable) $article->increment('qte_vide', $newQuantite);
            }

            // 4. UPDATE movement record
            $mouvementStock->update(array_merge($validated, ['type' => $dbType]));

            DB::commit();
            return response()->json($mouvementStock->load('article'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de la modification: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(MouvementStock $mouvementStock)
    {
        DB::beginTransaction();
        try {
            $article = Article::lockForUpdate()->find($mouvementStock->article_id);

            if ($mouvementStock->type === 'entrée') {
                $article->decrement('qte_actuelle', $mouvementStock->quantite);
            } else {
                $article->increment('qte_actuelle', $mouvementStock->quantite);
            }

            $mouvementStock->delete();
            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }
}
