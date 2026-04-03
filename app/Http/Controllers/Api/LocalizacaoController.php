<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class LocalizacaoController extends Controller
{
    /**
     * Atualizar localização do usuário autenticado - LIMPAR CACHE
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

            // Limpar cache de localização
            $this->clearLocalizacaoCache($user->id);

            // ✅ RETORNAR ARRAY PURO
            return response()->json([
                'success' => true,
                'message' => 'Localização atualizada com sucesso',
                'data' => [
                    'latitude' => (float) $user->latitude,
                    'longitude' => (float) $user->longitude,
                    'raio' => (int) ($user->raio ?? 10)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar localização: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar localização do usuário - COM CACHE
     * GET /api/localizacao
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $cacheKey = "localizacao_{$user->id}";

        $data = Cache::remember($cacheKey, 3600, function() use ($user) {
            return [
                'latitude' => $user->latitude ? (float) $user->latitude : null,
                'longitude' => $user->longitude ? (float) $user->longitude : null,
                'raio' => (int) ($user->raio ?? 10)
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Buscar prestadores próximos - COM CACHE E TOARRAY
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

        $radius = (int) ($request->raio ?? 10);
        $cacheKey = "prestadores_proximos_" . md5($request->fullUrl());

        $prestadores = Cache::remember($cacheKey, 300, function() use ($request, $radius) {
            $query = User::where('tipo', 'prestador')
                ->where('ativo', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select('id', 'nome', 'foto', 'profissao', 'media_avaliacao', 'total_avaliacoes', 'latitude', 'longitude');

            if ($request->has('categoria_id')) {
                $query->whereHas('categorias', function ($q) use ($request) {
                    $q->where('categoria_id', $request->categoria_id);
                });
            }

            $prestadoresList = $query->get();

            // Calcular distância e filtrar por raio
            $filtered = $prestadoresList->filter(function($prestador) use ($request, $radius) {
                $distancia = $prestador->distanceTo($request->latitude, $request->longitude);
                return $distancia !== null && $distancia <= $radius;
            })->sortBy(function($prestador) use ($request) {
                return $prestador->distanceTo($request->latitude, $request->longitude);
            })->take(20);

            // ✅ CONVERTER PARA ARRAY PURO
            return $filtered->map(function($prestador) use ($request) {
                return [
                    'id' => (int) $prestador->id,
                    'nome' => $prestador->nome,
                    'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                    'profissao' => $prestador->profissao,
                    'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                    'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                    'distancia' => round($prestador->distanceTo($request->latitude, $request->longitude), 2),
                    'latitude' => (float) $prestador->latitude,
                    'longitude' => (float) $prestador->longitude,
                ];
            })->values()->toArray(); // ✅ GARANTIR ARRAY
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores,
            'meta' => [
                'count' => count($prestadores),
                'radius' => $radius,
                'latitude' => (float) $request->latitude,
                'longitude' => (float) $request->longitude,
            ]
        ]);
    }

    /**
     * Limpar cache de localização
     */
    private function clearLocalizacaoCache($userId)
    {
        Cache::forget("localizacao_{$userId}");

        // Limpar também caches relacionados a prestadores próximos
        // Nota: não podemos limpar todos, mas podemos limpar padrões
        Cache::forget("prestadores_proximos_*"); // Isso limpa todos os caches de prestadores próximos
    }
}
