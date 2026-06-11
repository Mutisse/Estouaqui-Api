<?php
// app/Http/Controllers/Api/CategoriaController.php

namespace App\Http\Controllers\Api;

use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends BaseController
{
    public function index(Request $request)
    {
        $categorias = Categoria::where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->withCount('servicos')  // ✅ Melhor performance
            ->get();

        // Renomear o campo para o que o frontend espera
        $categorias->each(function ($categoria) {
            $categoria->servicos_count = $categoria->servicos_count;
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    public function show($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoria não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $categoria
        ]);
    }
}
