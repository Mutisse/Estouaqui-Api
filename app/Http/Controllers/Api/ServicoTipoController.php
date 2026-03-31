<?php
// app/Http/Controllers/Api/ServicoTipoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicoTipo;
use Illuminate\Http\Request;

class ServicoTipoController extends Controller
{
    /**
     * Listar todos os tipos de serviço ativos
     * GET /api/servico-tipos
     */
    public function index()
    {
        $tipos = ServicoTipo::where('ativo', true)
            ->orderBy('ordem')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tipos
        ]);
    }

    /**
     * Listar opções para select
     * GET /api/servico-tipos/options
     */
    public function options()
    {
        $tipos = ServicoTipo::where('ativo', true)
            ->orderBy('ordem')
            ->get();

        $options = $tipos->map(function ($tipo) {
            return [
                'label' => $tipo->nome,
                'value' => $tipo->slug,
                'icone' => $tipo->icone,
                'cor' => $tipo->cor
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }
}
