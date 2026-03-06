<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Categorie::with('articles')->where('user_id', $request->user()->id)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'couleur' => 'nullable|string|max:7',
        ]);

        $categorie = $request->user()->categories()->create($validated);
        
        return response()->json($categorie, 201);
    }

    public function show(Categorie $category)
    {
        return response()->json($category);
    }

    public function update(Request $request, Categorie $category)
    {
        $validated = $request->validate([
            'nom' => 'sometimes|required|string|max:255',
            'couleur' => 'nullable|string|max:7',
        ]);

        $category->update($validated);
        return response()->json($category);
    }

    public function destroy(Categorie $category)
    {
        $category->delete();
        return response()->json(null, 204);
    }
}
