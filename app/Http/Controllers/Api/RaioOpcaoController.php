<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RaioOpcao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RaioOpcaoController extends Controller
{
    /**
     * Listar todas as opções de raio - COM CACHE
     * GET /api/raio-opcoes
     */
    public function index()
    {
        $opcoes = Cache::remember('raio_opcoes_all', 3600, function() {
            return RaioOpcao::where('ativo', true)
                ->orderBy('ordem')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $opcoes
        ]);
    }

    /**
     * Listar opções para select - COM CACHE
     * GET /api/raio-opcoes/options
     */
    public function options()
    {
        $options = Cache::remember('raio_opcoes_options', 3600, function() {
            $opcoes = RaioOpcao::where('ativo', true)
                ->orderBy('ordem')
                ->get();

            return $opcoes->map(function ($opcao) {
                return [
                    'label' => $opcao->label,
                    'value' => $opcao->valor
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }
}
