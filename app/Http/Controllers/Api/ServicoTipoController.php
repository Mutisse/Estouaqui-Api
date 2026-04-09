<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicoTipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServicoTipoController extends Controller
{
    public function index()
    {
        $tipos = Cache::remember('servico_tipos_all', 3600, function () {
            return ServicoTipo::where('ativo', true)
                ->orderBy('ordem')
                ->get()
                ->map(function ($tipo) {
                    return [
                        'id' => $tipo->id,
                        'nome' => $tipo->nome,
                        'slug' => $tipo->slug,
                        'icone' => $tipo->icone,
                        'cor' => $tipo->cor,
                    ];
                })
                ->toArray(); // ✅ OBRIGATÓRIO
        });

        return response()->json([
            'success' => true,
            'data' => $tipos
        ]);
    }

    public function options()
    {
        $options = Cache::remember('servico_tipos_options', 3600, function () {
            return ServicoTipo::where('ativo', true)
                ->orderBy('ordem')
                ->get()
                ->map(function ($tipo) {
                    return [
                        'label' => $tipo->nome,
                        'value' => $tipo->slug,
                        'icone' => $tipo->icone,
                        'cor' => $tipo->cor
                    ];
                })
                ->toArray(); // ← CONVERTE PARA ARRAY ANTES DO CACHE
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }
}
