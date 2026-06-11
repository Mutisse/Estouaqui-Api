<?php
// app/Http/Controllers/Api/PrestadorController.php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Favorito;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PrestadorController extends BaseController
{
    /**
     * Listar prestadores em destaque (melhores avaliados)
     * GET /api/prestadores/destaque
     */
    public function destaque()
    {
        $prestadores = User::prestadores()
            ->with('categorias')
            ->where('verificado', true)
            ->orderBy('media_avaliacao', 'desc')
            ->orderBy('total_avaliacoes', 'desc')
            ->limit(6)
            ->get();

        $prestadores->each(function ($prestador) {
            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Listar top prestadores (ranking)
     * GET /api/prestadores/top
     */
    public function top()
    {
        $prestadores = User::prestadores()
            ->with('categorias')
            ->where('media_avaliacao', '>=', 4)
            ->orderBy('media_avaliacao', 'desc')
            ->orderBy('total_avaliacoes', 'desc')
            ->limit(5)
            ->get();

        $prestadores->each(function ($prestador) {
            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Mostrar detalhes de um prestador específico
     * GET /api/prestadores/{id}
     */
    public function show($id)
    {
        try {
            $prestador = User::prestadores()
                ->with('categorias')
                ->find($id);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            if (method_exists($prestador, 'servicos')) {
                $prestador->load('servicos');
            } else {
                $prestador->servicos = [];
            }

            if (method_exists($prestador, 'avaliacoesRecebidas')) {
                $prestador->load('avaliacoesRecebidas');
            } else {
                $prestador->avaliacoes = [];
            }

            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
            $prestador->portfolio = [];

            return response()->json([
                'success' => true,
                'data' => $prestador
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar prestador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar dados do prestador'
            ], 500);
        }
    }

    /**
     * Listar prestadores favoritos do usuário
     * GET /api/prestadores/favoritos
     */
    public function favoritos(Request $request)
    {
        $user = $request->user();

        $favoritos = $user->prestadoresFavoritos()
            ->with('categorias')
            ->get();

        $favoritos->each(function ($prestador) {
            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
        });

        return response()->json([
            'success' => true,
            'data' => $favoritos,
            'count' => $favoritos->count()
        ]);
    }

    /**
     * Adicionar ou remover prestador dos favoritos
     * POST /api/prestadores/{id}/favorito
     */
    public function toggleFavorito(Request $request, $prestadorId)
    {
        $user = $request->user();

        $prestador = User::prestadores()->find($prestadorId);

        if (!$prestador) {
            return response()->json([
                'success' => false,
                'message' => 'Prestador não encontrado'
            ], 404);
        }

        $favorito = Favorito::where('cliente_id', $user->id)
            ->where('prestador_id', $prestadorId)
            ->first();

        if ($favorito) {
            $favorito->delete();
            $isFavorito = false;
            $message = 'Removido dos favoritos';

            NotificationService::send('favorito.removido', $prestadorId, [
                'cliente_nome' => $user->nome,
                'cliente_id' => $user->id
            ]);
        } else {
            Favorito::create([
                'cliente_id' => $user->id,
                'prestador_id' => $prestadorId
            ]);
            $isFavorito = true;
            $message = 'Adicionado aos favoritos';

            NotificationService::send('favorito.adicionado', $prestadorId, [
                'cliente_nome' => $user->nome,
                'cliente_id' => $user->id
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_favorito' => $isFavorito
        ]);
    }

    /**
     * Listar prestadores disponíveis
     * GET /api/prestadores/disponiveis
     */
    public function disponiveis()
    {
        $prestadores = User::prestadores()
            ->with('categorias')
            ->disponiveis()
            ->orderBy('media_avaliacao', 'desc')
            ->limit(10)
            ->get();

        $prestadores->each(function ($prestador) {
            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Listar prestadores por categoria
     * GET /api/prestadores/categoria/{categoriaId}
     */
    public function porCategoria($categoriaId)
    {
        $prestadores = User::prestadores()
            ->with('categorias')
            ->whereHas('categorias', function ($query) use ($categoriaId) {
                $query->where('categoria_id', $categoriaId);
            })
            ->orderBy('media_avaliacao', 'desc')
            ->get();

        $prestadores->each(function ($prestador) {
            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Atualizar disponibilidade do prestador
     * PATCH /api/prestadores/disponibilidade
     */
    public function updateDisponibilidade(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas prestadores podem alterar disponibilidade'
            ], 403);
        }

        $request->validate([
            'disponivel' => 'required|boolean'
        ]);

        $user->disponivel = $request->disponivel;
        $user->save();

        $status = $request->disponivel ? 'disponível' : 'indisponível';

        NotificationService::send('sistema.perfil_atualizado', $user->id, [
            'nome' => $user->nome
        ]);

        return response()->json([
            'success' => true,
            'message' => "Você agora está {$status} para novos serviços",
            'disponivel' => $user->disponivel
        ]);
    }

    /**
     * Buscar prestadores próximos (por localização)
     * GET /api/prestadores/proximos
     */
    public function proximos(Request $request)
{
    try {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'raio' => 'nullable|numeric|min:1|max:50'
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $raio = $request->get('raio', 10);

        $prestadores = User::prestadores()
            ->with('categorias')
            ->where('verificado', true)
            ->where('disponivel', true)
            ->selectRaw("*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance", [$latitude, $longitude, $latitude])
            ->having('distance', '<', $raio)
            ->orderBy('distance', 'asc')
            ->limit(20)
            ->get();

        // ✅ CONVERTER TIPOS PARA O FRONTEND
        $prestadores->each(function ($prestador) {
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->disponivel = (bool) $prestador->disponivel;
            $prestador->verificado = (bool) $prestador->verificado;
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores,
            'meta' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'raio' => $raio,
                'total' => $prestadores->count()
            ]
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erro ao buscar prestadores',
            'data' => []
        ], 500);
    }
}

    /**
     * Listar prestadores com filtros e paginação
     * GET /api/prestadores
     */
    public function index(Request $request)
    {
        $query = User::prestadores()
            ->with('categorias')
            ->where('verificado', true);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('profissao', 'like', "%{$search}%");
            });
        }

        if ($request->has('latitude') && $request->has('longitude')) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $distanciaMax = $request->get('distancia_max', 50);

            $query->selectRaw("*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance", [$latitude, $longitude, $latitude])
                ->having('distance', '<', $distanciaMax)
                ->orderBy('distance', 'asc');
        }

        if ($request->has('rating_min') && $request->rating_min > 0) {
            $query->where('media_avaliacao', '>=', $request->rating_min);
        }

        if ($request->has('disponivel')) {
            $disponivel = filter_var($request->disponivel, FILTER_VALIDATE_BOOLEAN);
            $query->where('disponivel', $disponivel);
        }

        if ($request->has('categoria_id') && $request->categoria_id) {
            $query->whereHas('categorias', function ($q) use ($request) {
                $q->where('categoria_id', $request->categoria_id);
            });
        }

        if ($request->has('subcategoria_id') && $request->subcategoria_id) {
            $query->whereHas('categorias', function ($q) use ($request) {
                $q->where('id', $request->subcategoria_id);
            });
        }

        $ordenarPor = $request->get('ordenar_por', 'rating_desc');
        switch ($ordenarPor) {
            case 'rating_desc':
                $query->orderBy('media_avaliacao', 'desc')
                    ->orderBy('total_avaliacoes', 'desc');
                break;
            case 'servicos_desc':
                $query->withCount('servicos')
                    ->orderBy('servicos_count', 'desc');
                break;
            case 'distancia_asc':
                if (!$request->has('latitude')) {
                    $query->orderBy('media_avaliacao', 'desc');
                }
                break;
            default:
                $query->orderBy('media_avaliacao', 'desc');
        }

        $perPage = $request->get('per_page', 20);
        $prestadores = $query->paginate($perPage);

        $prestadores->getCollection()->each(function ($prestador) {
            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
            $prestador->disponivel = (bool) $prestador->disponivel;
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores->items(),
            'pagination' => [
                'current_page' => $prestadores->currentPage(),
                'last_page' => $prestadores->lastPage(),
                'per_page' => $prestadores->perPage(),
                'total' => $prestadores->total(),
            ]
        ]);
    }
}
