<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Vente;
use App\Models\LigneVente;
use App\Models\MouvementStock;
use App\Models\Categorie;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $periode = $request->query('periode', '7j');
        $startDate = match($periode) {
            '7j' => Carbon::now()->subDays(7),
            '30j' => Carbon::now()->subMonths(1),
            '3m' => Carbon::now()->subMonths(3),
            default => null,
        };

        $query = Vente::query();
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $totalRevenue = (float) $query->sum('total');
        $totalDeliveryFees = (float) $query->sum('frais_livraison');
        
        // Calculate profit (sum of benefice_unitaire * quantite from ligne_ventes)
        $itemsProfit = (float) DB::table('ligne_ventes')
            ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
            ->when($startDate, function($q) use ($startDate) {
                return $q->where('ventes.created_at', '>=', $startDate);
            })
            ->select(DB::raw('SUM(benefice_unitaire * quantite) as items_profit'))
            ->value('items_profit');

        $totalProfit = $itemsProfit + $totalDeliveryFees;

        // Stock value (prix_achat * qte_actuelle)
        $stockValue = (float) Article::all()->sum(function($article) {
            return $article->prix_achat * $article->qte_actuelle;
        });

        $activeArticlesCount = Article::where('statut', 'actif')->count();
        $stockAlertsCount = Article::whereRaw('qte_actuelle <= seuil_alerte')->count();
        
        $alerts = Article::whereRaw('qte_actuelle <= seuil_alerte')
            ->select('nom', 'qte_actuelle', 'seuil_alerte')
            ->limit(10)
            ->get();

        // Daily Stats grouping
        $dailyData = [];
        
        // 1. Sales & Delivery Fees & Transactions by day (from ventes table)
        $salesByDay = Vente::query()
            ->when($startDate, function($q) use ($startDate) {
                return $q->where('created_at', '>=', $startDate);
            })
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('SUM(frais_livraison) as delivery_fees'),
                DB::raw('COUNT(*) as transactions_count')
            )
            ->groupBy('day')
            ->get();

        foreach ($salesByDay as $row) {
            $dailyData[$row->day] = [
                'date' => $row->day,
                'revenue' => (float)$row->revenue,
                'profit' => (float)$row->delivery_fees, // start with delivery fees
                'supply' => 0,
                'loss' => 0,
                'transactions_count' => (int)$row->transactions_count
            ];
        }

        // 2. Profit from items by day
        $itemProfitsByDay = LigneVente::query()
            ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
            // Add user_id check explicitly because LigneVente doesn't have it directly but Vente does
            ->where('ventes.user_id', Auth::id())
            ->when($startDate, function($q) use ($startDate) {
                return $q->where('ventes.created_at', '>=', $startDate);
            })
            ->select(
                DB::raw('DATE(ventes.created_at) as day'),
                DB::raw('SUM(ligne_ventes.benefice_unitaire * ligne_ventes.quantite) as items_profit')
            )
            ->groupBy('day')
            ->get();

        foreach ($itemProfitsByDay as $row) {
            if (isset($dailyData[$row->day])) {
                $dailyData[$row->day]['profit'] += (float)$row->items_profit;
            }
        }

        // 3. Movements (Supply and Loss) by day
        $mvtsByDay = MouvementStock::query()
            ->when($startDate, function($q) use ($startDate) {
                return $q->where('date', '>=', $startDate);
            })
            ->select(
                DB::raw('DATE(date) as day'),
                'type',
                'notes',
                DB::raw('SUM(quantite * prix_unitaire) as total_val')
            )
            ->groupBy('day', 'type', 'notes')
            ->get();

        foreach ($mvtsByDay as $row) {
            if (!isset($dailyData[$row->day])) {
                $dailyData[$row->day] = [
                    'date' => $row->day,
                    'revenue' => 0,
                    'profit' => 0,
                    'supply' => 0,
                    'loss' => 0,
                    'transactions_count' => 0
                ];
            }

            if ($row->type === 'entrée') {
                $dailyData[$row->day]['supply'] += (float)$row->total_val;
            } else if ($row->type === 'sortie' && str_contains(strtoupper($row->notes), '[PERTE]')) {
                $dailyData[$row->day]['loss'] += (float)$row->total_val;
            }
        }

        // Sort by date descending
        krsort($dailyData);

        // Transactions count
        $transactionsCount = (int) $query->count();

        // Total Pleins and Vides (Current state)
        $totalPleins = (float) Article::sum('qte_actuelle');
        $totalVides = (float) Article::sum('qte_vide');

        // Top 3 Products in period
        $topProduits = LigneVente::query()
            ->join('ventes', 'ligne_ventes.vente_id', '=', 'ventes.id')
            ->join('articles', 'ligne_ventes.article_id', '=', 'articles.id')
            ->where('ventes.user_id', Auth::id()) // Manually scope LigneVente aggregation
            ->when($startDate, function($q) use ($startDate) {
                return $q->where('ventes.created_at', '>=', $startDate);
            })
            ->select(
                'articles.nom',
                DB::raw('SUM(ligne_ventes.quantite) as total_qte'),
                DB::raw('SUM(ligne_ventes.quantite * ligne_ventes.prix_unitaire) as total_revenu')
            )
            ->groupBy('articles.id', 'articles.nom')
            ->orderByDesc('total_qte')
            ->limit(3)
            ->get();

        $hasReusableProducts = Categorie::where('est_reutilisable', true)->exists();

        return response()->json([
            'has_reusable_products' => $hasReusableProducts,
            'stock_value' => $stockValue,
            'revenue' => $totalRevenue,
            'profit' => $totalProfit,
            'transactions_count' => $transactionsCount,
            'total_pleins' => $totalPleins,
            'total_vides' => $totalVides,
            'top_produits' => $topProduits,
            'active_articles' => $activeArticlesCount,
            'stock_alerts_count' => $stockAlertsCount,
            'alerts' => $alerts,
            'daily_stats' => array_values($dailyData),
            'periode' => $periode
        ]);
    }
}
