<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiaSemana;
use App\Models\Mes;
use App\Models\HorarioPadrao;
use Illuminate\Support\Facades\Cache;

class AuxiliarController extends Controller
{
    /**
     * Listar dias da semana - COM CACHE
     * GET /api/auxiliar/dias-semana
     */
    public function diasSemana()
    {
        $dias = Cache::remember('auxiliar_dias_semana', 86400, function() {
            return DiaSemana::where('ativo', true)
                ->orderBy('ordem')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $dias
        ]);
    }

    /**
     * Listar meses - COM CACHE
     * GET /api/auxiliar/meses
     */
    public function meses()
    {
        $meses = Cache::remember('auxiliar_meses', 86400, function() {
            return Mes::where('ativo', true)
                ->orderBy('numero')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $meses
        ]);
    }

    /**
     * Listar opções de dias para select - COM CACHE
     * GET /api/auxiliar/dias-options
     */
    public function diasOptions()
    {
        $options = Cache::remember('auxiliar_dias_options', 86400, function() {
            $dias = DiaSemana::where('ativo', true)
                ->orderBy('ordem')
                ->get();

            return $dias->map(function ($dia) {
                return [
                    'label' => $dia->nome,
                    'value' => strtolower(str_replace('-', '', $dia->nome_curto))
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * Listar horários padrão - COM CACHE
     * GET /api/auxiliar/horarios-padrao
     */
    public function horariosPadrao()
    {
        $horarios = Cache::remember('auxiliar_horarios_padrao', 86400, function() {
            return HorarioPadrao::where('ativo', true)
                ->orderBy('ordem')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $horarios
        ]);
    }

    /**
     * Listar opções de horários para select - COM CACHE
     * GET /api/auxiliar/horarios-options
     */
    public function horariosOptions()
    {
        $options = Cache::remember('auxiliar_horarios_options', 86400, function() {
            $horarios = HorarioPadrao::where('ativo', true)
                ->orderBy('ordem')
                ->get();

            return $horarios->map(function ($horario) {
                return [
                    'label' => $horario->label,
                    'value' => $horario->horario
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }
}
