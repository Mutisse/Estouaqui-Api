<?php
// app/Http/Controllers/Api/AuxiliarController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiaSemana;
use App\Models\Mes;
use App\Models\HorarioPadrao;


class AuxiliarController extends Controller
{
    /**
     * Listar dias da semana
     * GET /api/auxiliar/dias-semana
     */
    public function diasSemana()
    {
        $dias = DiaSemana::where('ativo', true)
            ->orderBy('ordem')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dias
        ]);
    }

    /**
     * Listar meses
     * GET /api/auxiliar/meses
     */
    public function meses()
    {
        $meses = Mes::where('ativo', true)
            ->orderBy('numero')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $meses
        ]);
    }

    /**
     * Listar opções de dias para select
     * GET /api/auxiliar/dias-options
     */
    public function diasOptions()
    {
        $dias = DiaSemana::where('ativo', true)
            ->orderBy('ordem')
            ->get();

        $options = $dias->map(function ($dia) {
            return [
                'label' => $dia->nome,
                'value' => strtolower(str_replace('-', '', $dia->nome_curto))
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * Listar horários padrão
     * GET /api/auxiliar/horarios-padrao
     */
    public function horariosPadrao()
    {
        $horarios = HorarioPadrao::where('ativo', true)
            ->orderBy('ordem')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $horarios
        ]);
    }

    /**
     * Listar opções de horários para select
     * GET /api/auxiliar/horarios-options
     */
    public function horariosOptions()
    {
        $horarios = HorarioPadrao::where('ativo', true)
            ->orderBy('ordem')
            ->get();

        $options = $horarios->map(function ($horario) {
            return [
                'label' => $horario->label,
                'value' => $horario->horario
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }
}
