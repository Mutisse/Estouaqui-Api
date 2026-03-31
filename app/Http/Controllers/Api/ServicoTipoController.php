<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicoTipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServicoTipoController extends Controller
{
    /**
     * Listar todos os tipos de serviço ativos - COM CACHE
     * GET /api/servico-tipos
     */
    public function index()
    {
        $tipos = Cache::remember('servico_tipos_all', 3600, function() {
            return ServicoTipo::where('ativo', true)
                ->orderBy('ordem')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $tipos
        ]);
    }

    /**
     * Listar opções para select - COM CACHE
     * GET /api/servico-tipos/options
     */
    public function options()
    {
        $options = Cache::remember('servico_tipos_options', 3600, function() {
            $tipos = ServicoTipo::where('ativo', true)
                ->orderBy('ordem')
                ->get();

            return $tipos->map(function ($tipo) {
                return [
                    'label' => $tipo->nome,
                    'value' => $tipo->slug,
                    'icone' => $tipo->icone,
                    'cor' => $tipo->cor
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }
}
