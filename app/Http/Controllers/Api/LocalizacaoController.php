<?php
// app/Http/Controllers/Api/LocalizacaoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocalizacaoController extends Controller
{
    /**
     * Atualizar localização do usuário autenticado
     * POST /api/localizacao
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'raio' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $user->latitude = $request->latitude;
            $user->longitude = $request->longitude;

            if ($request->has('raio')) {
                $user->raio = $request->raio;
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Localização atualizada com sucesso',
                'data' => [
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'raio' => $user->raio
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar localização'
            ], 500);
        }
    }

    /**
     * Buscar localização do usuário
     * GET /api/localizacao
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'raio' => $user->raio ?? 10
            ]
        ]);
    }

    /**
     * Buscar prestadores próximos
     * GET /api/localizacao/prestadores-proximos
     */
    public function prestadoresProximos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'raio' => 'nullable|integer|min:1|max:100',
            'categoria_id' => 'nullable|exists:categorias,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $radius = $request->raio ?? 10;

        $query = User::where('tipo', 'prestador')
            ->where('ativo', true)
            ->nearby($request->latitude, $request->longitude, $radius);

        if ($request->has('categoria_id')) {
            $query->whereHas('categorias', function ($q) use ($request) {
                $q->where('categoria_id', $request->categoria_id);
            });
        }

        $prestadores = $query->with(['categorias', 'servicos'])
            ->orderByRaw("ST_Distance_Sphere(point(longitude, latitude), point(?, ?))", [
                $request->longitude, $request->latitude
            ])
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $prestadores->map(function ($prestador) use ($request) {
                return [
                    'id' => $prestador->id,
                    'nome' => $prestador->nome,
                    'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                    'profissao' => $prestador->profissao,
                    'media_avaliacao' => $prestador->media_avaliacao,
                    'total_avaliacoes' => $prestador->total_avaliacoes,
                    'distancia' => round($prestador->distanceTo($request->latitude, $request->longitude), 2),
                    'categorias' => $prestador->categorias,
                    'servicos' => $prestador->servicos,
                ];
            }),
            'meta' => [
                'current_page' => $prestadores->currentPage(),
                'last_page' => $prestadores->lastPage(),
                'total' => $prestadores->total(),
            ]
        ]);
    }
}
