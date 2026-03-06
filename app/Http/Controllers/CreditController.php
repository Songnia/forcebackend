<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Models\PaiementCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    public function index()
    {
        return Credit::with(['vente.lignes', 'paiements'])->latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vente_id' => 'nullable|exists:ventes,id',
            'client_nom' => 'required|string|max:255',
            'client_telephone' => 'nullable|string|max:20',
            'montant_total' => 'required|numeric|min:0',
            'montant_paye' => 'nullable|numeric|min:0',
            'echeance' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $validated['montant_paye'] = $validated['montant_paye'] ?? 0;
        
        if ($validated['montant_paye'] >= $validated['montant_total']) {
            $validated['statut'] = 'solde';
        } else {
            $validated['statut'] = 'en_cours';
        }

        $credit = Credit::create($validated);

        if ($validated['montant_paye'] > 0) {
            $credit->paiements()->create([
                'montant' => $validated['montant_paye'],
                'mode_paiement' => 'especes',
                'notes' => 'Paiement initial à la création du crédit'
            ]);
        }

        return response()->json($credit->load('paiements'), 201);
    }

    public function show(Credit $credit)
    {
        return $credit->load(['vente.lignes', 'paiements']);
    }

    public function update(Request $request, Credit $credit)
    {
        $validated = $request->validate([
            'client_nom' => 'sometimes|string|max:255',
            'client_telephone' => 'nullable|string|max:20',
            'echeance' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $credit->update($validated);

        return response()->json($credit);
    }

    public function destroy(Credit $credit)
    {
        $credit->delete();
        return response()->json(null, 204);
    }

    public function payer(Request $request, Credit $credit)
    {
        $validated = $request->validate([
            'montant' => 'required|numeric|min:1',
            'mode_paiement' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $credit) {
            $paiement = $credit->paiements()->create($validated);
            
            $credit->montant_paye += $validated['montant'];
            
            if ($credit->montant_paye >= $credit->montant_total) {
                // Pour éviter d'afficher un montant payé supérieur au total
                $credit->montant_paye = $credit->montant_total;
                $credit->statut = 'solde';
            }
            
            $credit->save();

            return response()->json($credit->load('paiements'));
        });
    }
}
