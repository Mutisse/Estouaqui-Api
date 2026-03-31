<?php
// app/Http/Controllers/Api/RaioOpcaoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RaioOpcao;
use Illuminate\Http\Request;

class RaioOpcaoController extends Controller
{
    /**
     * Listar todas as opções de raio
     * GET /api/raio-opcoes
     */
    public function index()
    {
        $opcoes = RaioOpcao::where('ativo', true)
            ->orderBy('ordem')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $opcoes
        ]);
    }

    /**
     * Listar opções para select
     * GET /api/raio-opcoes/options
     */
    public function options()
    {
        $opcoes = RaioOpcao::where('ativo', true)
            ->orderBy('ordem')
            ->get();

        $options = $opcoes->map(function ($opcao) {
            return [
                'label' => $opcao->label,
                'value' => $opcao->valor
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }
}
