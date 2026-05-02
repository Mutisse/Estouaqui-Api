<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Servico;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Transacao;
use App\Models\PrestadorIntervalo;
use App\Models\PrestadorDisponibilidade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Notifications\DynamicNotification;

class PrestadorController extends Controller
{
    // ==========================================
    // CONSTANTES DE CACHE - OTIMIZADAS
    // ==========================================
    private const CACHE_SHORT = 60;       // 1 minuto
    private const CACHE_MEDIUM = 300;     // 5 minutos
    private const CACHE_LONG = 1800;      // 30 minutos
    private const CACHE_VERY_LONG = 43200; // 12 horas
    private const MAX_LIMIT = 50;
    private const DEFAULT_LIMIT = 20;

    // Mapeamento de dias do banco para o frontend
    private const DIAS_MAP = [
        'seg' => 'segunda',
        'ter' => 'terca',
        'qua' => 'quarta',
        'qui' => 'quinta',
        'sex' => 'sexta',
        'sab' => 'sabado',
        'dom' => 'domingo',
    ];

    // ==========================================
    // 1. REGISTRO DO PRESTADOR
    // ==========================================
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'telefone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6',
            'endereco' => 'nullable|string',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'profissao' => 'nullable|string|max:255',
            'sobre' => 'nullable|string',
            'descricao' => 'nullable|string',
            'categorias' => 'nullable|json',
            'raio' => 'nullable|integer|min:1|max:100',
            'disponibilidade' => 'nullable|json',
            'documento' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'portfolio.0' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'portfolio.1' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'portfolio.2' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $categoriasIds = $this->processCategorias($request->categorias);
            $profissaoGerada = $this->gerarProfissao($categoriasIds, $request->profissao);
            $portfolioPaths = $this->processPortfolio($request);
            $disponibilidadeData = $this->processDisponibilidadeData($request->disponibilidade);
            $userData = $this->buildUserData($request, $profissaoGerada, $categoriasIds, $portfolioPaths, $disponibilidadeData);

            if ($request->hasFile('foto')) {
                $userData['foto'] = $request->file('foto')->store('fotos/prestadores', 'public');
            }
            if ($request->hasFile('documento')) {
                $userData['documento'] = $request->file('documento')->store('documentos/prestadores', 'public');
            }

            $user = User::create($userData);
            $this->saveCategorias($user->id, $categoriasIds);
            $this->saveDisponibilidade($user->id, $disponibilidadeData);

            if ($request->raio) {
                $user->update(['raio_atuacao' => $request->raio]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prestador registado com sucesso!',
                'data' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                    'profissao' => $user->profissao,
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'categorias' => $categoriasIds,
                ],
                'token' => $user->createToken('auth_token')->plainTextToken
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao registar prestador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao registar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 2. SERVIÇOS DO PRESTADOR
    // ==========================================
    public function servicos(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Usuário não autenticado'], 401);
        }

        $cacheKey = "prestador_servicos_{$user->id}";
        $servicos = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            return Servico::where('prestador_id', $user->id)
                ->with('categoria:id,nome')
                ->select(['id', 'nome', 'categoria_id', 'preco', 'duracao', 'descricao', 'icone', 'ativo', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($servico) => [
                    'id' => (int) $servico->id,
                    'nome' => (string) $servico->nome,
                    'categoria_id' => (int) $servico->categoria_id,
                    'categoria_nome' => $servico->categoria?->nome,
                    'preco' => (float) $servico->preco,
                    'duracao' => (int) $servico->duracao,
                    'descricao' => $servico->descricao,
                    'icone' => (string) $servico->icone,
                    'ativo' => (bool) $servico->ativo,
                    'created_at' => $servico->created_at?->toISOString(),
                ])
                ->toArray();
        });

        return response()->json(['success' => true, 'data' => $servicos]);
    }

    public function createServico(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'categoria_id' => 'required|exists:categorias,id',
            'preco' => 'required|numeric|min:0',
            'duracao' => 'required|integer|min:5',
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $servico = Servico::create([
                'prestador_id' => $user->id,
                'categoria_id' => $request->categoria_id,
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'preco' => $request->preco,
                'duracao' => $request->duracao,
                'icone' => $request->icone ?? 'handyman',
                'ativo' => true,
            ]);

            $this->clearPrestadorCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Serviço criado com sucesso',
                'data' => $servico
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar serviço: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar serviço'
            ], 500);
        }
    }

    public function updateServico(Request $request, $id)
    {
        $user = $request->user();
        $servico = Servico::where('prestador_id', $user->id)->find($id);

        if (!$servico) {
            return response()->json([
                'success' => false,
                'error' => 'Serviço não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255',
            'categoria_id' => 'sometimes|exists:categorias,id',
            'preco' => 'sometimes|numeric|min:0',
            'duracao' => 'sometimes|integer|min:5',
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
            'ativo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('nome')) $servico->nome = $request->nome;
            if ($request->has('categoria_id')) $servico->categoria_id = $request->categoria_id;
            if ($request->has('preco')) $servico->preco = $request->preco;
            if ($request->has('duracao')) $servico->duracao = $request->duracao;
            if ($request->has('descricao')) $servico->descricao = $request->descricao;
            if ($request->has('icone')) $servico->icone = $request->icone;
            if ($request->has('ativo')) $servico->ativo = $request->ativo;

            $servico->save();
            $this->clearPrestadorCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Serviço atualizado com sucesso',
                'data' => $servico
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar serviço: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar serviço'
            ], 500);
        }
    }

    public function deleteServico(Request $request, $id)
    {
        $user = $request->user();
        $servico = Servico::where('prestador_id', $user->id)->find($id);

        if (!$servico) {
            return response()->json([
                'success' => false,
                'error' => 'Serviço não encontrado'
            ], 404);
        }

        $servico->delete();
        $this->clearPrestadorCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Serviço removido com sucesso'
        ]);
    }

    public function toggleServico(Request $request, $id)
    {
        $user = $request->user();
        $servico = Servico::where('prestador_id', $user->id)->find($id);

        if (!$servico) {
            return response()->json([
                'success' => false,
                'error' => 'Serviço não encontrado'
            ], 404);
        }

        $servico->ativo = !$servico->ativo;
        $servico->save();
        $this->clearPrestadorCache($user->id);

        return response()->json([
            'success' => true,
            'message' => $servico->ativo ? 'Serviço ativado' : 'Serviço desativado',
            'data' => $servico
        ]);
    }

    // ==========================================
    // 3. CATEGORIAS DO PRESTADOR
    // ==========================================
    public function minhasCategorias(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Usuário não autenticado'], 401);
        }

        $cacheKey = "prestador_categorias_{$user->id}";
        $categorias = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            $categoriasFromDb = DB::table('prestador_categorias')
                ->join('categorias', 'prestador_categorias.categoria_id', '=', 'categorias.id')
                ->where('prestador_categorias.user_id', $user->id)
                ->select(['categorias.id', 'categorias.nome', 'categorias.slug', 'categorias.icone', 'categorias.cor'])
                ->get();

            if ($categoriasFromDb->isNotEmpty()) {
                return $categoriasFromDb->map(fn($cat) => [
                    'id' => (int) $cat->id,
                    'nome' => (string) $cat->nome,
                    'slug' => (string) ($cat->slug ?? ''),
                    'icone' => (string) ($cat->icone ?? 'category'),
                    'cor' => (string) ($cat->cor ?? 'primary'),
                ])->toArray();
            }

            $userModel = User::find($user->id);
            if ($userModel && $userModel->preferences) {
                $preferences = is_array($userModel->preferences) ? $userModel->preferences : json_decode($userModel->preferences, true);
                if (!empty($preferences['categorias'])) {
                    // Remover categorias inválidas (que não existem no banco)
                    $categoriasValidas = array_filter($preferences['categorias'], function ($cat) {
                        return is_numeric($cat) && Categoria::where('id', $cat)->exists();
                    });

                    if (!empty($categoriasValidas)) {
                        $categoriasFromPreferences = DB::table('categorias')
                            ->whereIn('id', $categoriasValidas)
                            ->select(['id', 'nome', 'slug', 'icone', 'cor'])
                            ->get();

                        if ($categoriasFromPreferences->isNotEmpty()) {
                            $insertData = $categoriasFromPreferences->map(fn($cat) => [
                                'user_id' => $user->id,
                                'categoria_id' => $cat->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ])->toArray();
                            DB::table('prestador_categorias')->insert($insertData);

                            return $categoriasFromPreferences->map(fn($cat) => [
                                'id' => (int) $cat->id,
                                'nome' => (string) $cat->nome,
                                'slug' => (string) ($cat->slug ?? ''),
                                'icone' => (string) ($cat->icone ?? 'category'),
                                'cor' => (string) ($cat->cor ?? 'primary'),
                            ])->toArray();
                        }
                    }
                }
            }
            return [];
        });

        return response()->json(['success' => true, 'data' => $categorias, 'meta' => ['total' => count($categorias)]]);
    }

    public function addCategoria(Request $request, $categoriaId)
    {
        $user = $request->user();
        try {
            $categoria = Categoria::find($categoriaId);
            if (!$categoria) {
                return response()->json(['success' => false, 'error' => 'Categoria não encontrada'], 404);
            }

            DB::table('prestador_categorias')->updateOrInsert(
                ['user_id' => $user->id, 'categoria_id' => $categoriaId],
                ['created_at' => now(), 'updated_at' => now()]
            );

            $userModel = User::find($user->id);
            $preferences = $userModel->preferences ? json_decode($userModel->preferences, true) : [];
            if (!isset($preferences['categorias'])) {
                $preferences['categorias'] = [];
            }
            if (!in_array($categoriaId, $preferences['categorias'])) {
                $preferences['categorias'][] = $categoriaId;
                $userModel->preferences = json_encode($preferences);
                $userModel->save();
            }

            $this->clearPrestadorCache($user->id);
            return response()->json(['success' => true, 'message' => 'Categoria adicionada com sucesso']);
        } catch (\Exception $e) {
            Log::error('Erro ao adicionar categoria: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao adicionar categoria'], 500);
        }
    }

    public function removeCategoria(Request $request, $categoriaId)
    {
        $user = $request->user();
        try {
            DB::table('prestador_categorias')
                ->where('user_id', $user->id)
                ->where('categoria_id', $categoriaId)
                ->delete();

            $userModel = User::find($user->id);
            $preferences = $userModel->preferences ? json_decode($userModel->preferences, true) : [];
            if (isset($preferences['categorias'])) {
                $preferences['categorias'] = array_values(array_filter($preferences['categorias'], fn($id) => $id != $categoriaId));
                $userModel->preferences = json_encode($preferences);
                $userModel->save();
            }

            $this->clearPrestadorCache($user->id);
            return response()->json(['success' => true, 'message' => 'Categoria removida com sucesso']);
        } catch (\Exception $e) {
            Log::error('Erro ao remover categoria: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao remover categoria'], 500);
        }
    }

    // ==========================================
    // 4. PERFIL DO PRESTADOR (PÚBLICO)
    // ==========================================
    public function index(Request $request)
    {
        try {
            $cacheKey = "prestadores_list_" . md5($request->fullUrl());
            $prestadores = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($request) {
                $query = DB::table('users')
                    ->where('users.tipo', 'prestador')
                    ->where('users.ativo', true)
                    ->select(['users.id', 'users.nome', 'users.email', 'users.telefone', 'users.foto', 'users.profissao', 'users.sobre', 'users.media_avaliacao', 'users.total_avaliacoes', 'users.verificado', 'users.ativo', 'users.preferences']);

                if ($request->has('categoria')) {
                    $categoriaId = (int) $request->categoria;
                    $query->whereExists(function ($q) use ($categoriaId) {
                        $q->select(DB::raw(1))->from('prestador_categorias')
                            ->whereColumn('prestador_categorias.user_id', 'users.id')
                            ->where('prestador_categorias.categoria_id', $categoriaId);
                    });
                }

                if ($request->filled('busca')) {
                    $busca = '%' . addcslashes($request->busca, '%_') . '%';
                    $query->where('users.nome', 'like', $busca);
                }

                $prestadores = $query->limit(self::MAX_LIMIT)->get();
                $prestadorIds = $prestadores->pluck('id')->toArray();
                $categoriasMap = $this->buscarCategoriasEmLote($prestadorIds);

                $resultado = [];
                foreach ($prestadores as $prestador) {
                    $preferences = json_decode($prestador->preferences, true);
                    $portfolio = isset($preferences['portfolio']) && is_array($preferences['portfolio'])
                        ? array_map(fn($path) => asset('storage/' . $path), array_slice($preferences['portfolio'], 0, 3))
                        : [];

                    $resultado[] = [
                        'id' => (int) $prestador->id,
                        'nome' => (string) ($prestador->nome ?? ''),
                        'email' => (string) ($prestador->email ?? ''),
                        'telefone' => (string) ($prestador->telefone ?? ''),
                        'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                        'profissao' => (string) ($prestador->profissao ?? ''),
                        'sobre' => (string) ($prestador->sobre ?? ''),
                        'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                        'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                        'verificado' => (bool) ($prestador->verificado ?? false),
                        'disponivel' => (bool) ($prestador->ativo ?? true),
                        'portfolio' => $portfolio,
                        'categorias' => $categoriasMap[$prestador->id] ?? [],
                    ];
                }
                return $resultado;
            });
            return response()->json(['success' => true, 'data' => $prestadores]);
        } catch (\Exception $e) {
            Log::error('Erro no index de prestadores: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao carregar prestadores'], 500);
        }
    }

    public function show($id)
    {
        $cacheKey = "prestador_detalhes_{$id}";
        $dados = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($id) {
            $prestador = DB::table('users')
                ->where('tipo', 'prestador')->where('id', $id)
                ->select(['id', 'nome', 'email', 'telefone', 'foto', 'profissao', 'sobre', 'media_avaliacao', 'total_avaliacoes', 'verificado', 'ativo', 'created_at', 'preferences'])->first();
            if (!$prestador) return null;
            $preferences = json_decode($prestador->preferences, true) ?? [];
            return [
                'id' => (int) $prestador->id,
                'nome' => (string) $prestador->nome,
                'email' => (string) $prestador->email,
                'telefone' => (string) $prestador->telefone,
                'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                'profissao' => $prestador->profissao,
                'sobre' => $prestador->sobre,
                'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                'verificado' => (bool) ($prestador->verificado ?? false),
                'disponivel' => (bool) ($prestador->ativo ?? true),
                'categorias' => $this->getCategoriasPrestador($id),
                'servicos' => $this->getServicosPrestador($id),
                'avaliacoes' => $this->getUltimasAvaliacoes($id),
                'portfolio' => $this->getPortfolioUrls($preferences),
                'created_at' => $prestador->created_at,
            ];
        });
        if (!$dados) return response()->json(['success' => false, 'message' => 'Prestador não encontrado'], 404);
        return response()->json(['success' => true, 'data' => $dados]);
    }

    public function destaque()
    {
        $prestadores = Cache::remember('prestadores_destaque', self::CACHE_MEDIUM, function () {
            return User::where('tipo', 'prestador')->where('ativo', true)
                ->select('id', 'nome', 'foto', 'profissao', 'media_avaliacao', 'total_avaliacoes', 'verificado')
                ->orderBy('media_avaliacao', 'desc')->limit(8)->get()
                ->map(fn($p) => [
                    'id' => (int) $p->id,
                    'nome' => (string) $p->nome,
                    'foto' => $p->foto ? asset('storage/' . $p->foto) : null,
                    'profissao' => (string) ($p->profissao ?? null),
                    'media_avaliacao' => (float) ($p->media_avaliacao ?? 0),
                    'total_avaliacoes' => (int) ($p->total_avaliacoes ?? 0),
                    'verificado' => (bool) ($p->verificado ?? false)
                ])
                ->toArray();
        });
        return response()->json(['success' => true, 'data' => $prestadores]);
    }

    public function topAvaliados()
    {
        $prestadores = Cache::remember('prestadores_top', self::CACHE_MEDIUM, function () {
            return User::where('tipo', 'prestador')->where('ativo', true)->where('media_avaliacao', '>=', 4)
                ->select('id', 'nome', 'foto', 'profissao', 'media_avaliacao', 'total_avaliacoes', 'verificado')
                ->orderByRaw('media_avaliacao DESC, total_avaliacoes DESC')->limit(10)->get()
                ->map(fn($p) => [
                    'id' => (int) $p->id,
                    'nome' => (string) $p->nome,
                    'foto' => $p->foto ? asset('storage/' . $p->foto) : null,
                    'profissao' => (string) ($p->profissao ?? null),
                    'media_avaliacao' => (float) ($p->media_avaliacao ?? 0),
                    'total_avaliacoes' => (int) ($p->total_avaliacoes ?? 0),
                    'verificado' => (bool) ($p->verificado ?? false)
                ])
                ->toArray();
        });
        return response()->json(['success' => true, 'data' => $prestadores]);
    }

    public function proximos(Request $request)
    {
        $latitude = $request->query('latitude');
        $longitude = $request->query('longitude');
        $radius = $request->query('radius', 10);
        $categoria = $request->query('categoria');
        $busca = $request->query('busca');
        $limit = min($request->query('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);

        if (!$latitude || !$longitude) {
            return response()->json(['success' => false, 'error' => 'Latitude e longitude são obrigatórias'], 422);
        }

        $cacheKey = "prestadores_proximos_" . md5("{$latitude}_{$longitude}_{$radius}_{$categoria}_{$busca}_{$limit}");
        $prestadores = Cache::remember($cacheKey, self::CACHE_SHORT, function () use ($latitude, $longitude, $radius, $categoria, $busca, $limit) {
            $latRange = $radius / 111.045;
            $lonRange = $radius / (111.045 * cos(deg2rad($latitude)));
            $query = DB::table('users')
                ->where('users.tipo', 'prestador')->where('users.ativo', true)
                ->whereNotNull('users.latitude')->whereNotNull('users.longitude')
                ->whereBetween('users.latitude', [$latitude - $latRange, $latitude + $latRange])
                ->whereBetween('users.longitude', [$longitude - $lonRange, $longitude + $lonRange])
                ->select(['users.id', 'users.nome', 'users.email', 'users.telefone', 'users.foto', 'users.profissao', 'users.sobre', 'users.media_avaliacao', 'users.total_avaliacoes', 'users.verificado', 'users.ativo as disponivel', 'users.latitude', 'users.longitude', 'users.preferences']);

            if ($categoria) {
                $query->whereExists(function ($q) use ($categoria) {
                    $q->select(DB::raw(1))->from('prestador_categorias')
                        ->whereColumn('prestador_categorias.user_id', 'users.id')
                        ->where('prestador_categorias.categoria_id', $categoria);
                });
            }
            if ($busca) {
                $buscaTermo = '%' . addcslashes($busca, '%_') . '%';
                $query->where('users.nome', 'like', $buscaTermo);
            }

            $prestadores = $query->limit($limit)->get();
            if ($prestadores->isEmpty()) return [];

            $resultado = [];
            foreach ($prestadores as $prestador) {
                $distancia = $this->calcularDistancia((float)$latitude, (float)$longitude, (float)$prestador->latitude, (float)$prestador->longitude);
                if ($distancia <= $radius) {
                    $preferences = json_decode($prestador->preferences, true) ?? [];
                    $portfolio = isset($preferences['portfolio']) && is_array($preferences['portfolio'])
                        ? array_map(fn($path) => asset('storage/' . $path), array_slice($preferences['portfolio'], 0, 3)) : [];
                    $resultado[] = [
                        'id' => (int) $prestador->id,
                        'nome' => (string) $prestador->nome,
                        'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                        'profissao' => (string) ($prestador->profissao ?? 'Prestador de Serviços'),
                        'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                        'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                        'verificado' => (bool) ($prestador->verificado ?? false),
                        'disponivel' => (bool) ($prestador->disponivel ?? true),
                        'distancia' => round($distancia, 2),
                        'portfolio' => $portfolio,
                        'categorias' => [],
                    ];
                }
            }
            return array_values($resultado);
        });
        return response()->json(['success' => true, 'data' => $prestadores, 'meta' => ['count' => count($prestadores), 'radius' => (float) $radius]]);
    }

    public function categorias()
    {
        $categorias = Cache::remember('prestador_categorias_publicas', self::CACHE_VERY_LONG, function () {
            return Categoria::where('ativo', true)->select('id', 'nome', 'slug', 'icone', 'cor', 'descricao')->orderBy('nome', 'asc')->get()
                ->map(fn($c) => [
                    'id' => (int) $c->id,
                    'nome' => (string) $c->nome,
                    'slug' => (string) $c->slug,
                    'icone' => (string) ($c->icone ?? 'category'),
                    'cor' => (string) ($c->cor ?? 'primary'),
                    'descricao' => $c->descricao
                ])->toArray();
        });
        return response()->json(['success' => true, 'data' => $categorias]);
    }

    public function avaliacoes($id)
    {
        $page = request()->query('page', 1);
        $avaliacoes = Cache::remember("prestador_avaliacoes_{$id}_{$page}", self::CACHE_MEDIUM, function () use ($id) {
            return Avaliacao::where('prestador_id', $id)->with('cliente:id,nome,foto')
                ->select(['id', 'nota', 'comentario', 'created_at', 'cliente_id'])->orderBy('created_at', 'desc')->paginate(20);
        });
        return response()->json(['success' => true, 'data' => $avaliacoes]);
    }

    // ==========================================
    // 5. SOLICITAÇÕES/PEDIDOS
    // ==========================================
    public function solicitacoes(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $page = $request->query('page', 1);
        $pedidos = Cache::remember("prestador_solicitacoes_{$user->id}_" . ($status ?? 'all') . "_{$page}", self::CACHE_MEDIUM, function () use ($user, $status) {
            $query = Pedido::where('prestador_id', $user->id)->with(['cliente:id,nome,foto,telefone', 'servico:id,nome,preco']);
            if ($status) $query->where('status', $status);
            return $query->orderBy('created_at', 'desc')->paginate(20);
        });
        return response()->json(['success' => true, 'data' => $pedidos]);
    }

    public function aceitarSolicitacao(Request $request, $id)
    {
        $user = $request->user();
        $pedido = Pedido::where('prestador_id', $user->id)->find($id);
        if (!$pedido) return response()->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
        if ($pedido->status !== 'pendente') return response()->json(['success' => false, 'error' => 'Este pedido não pode ser aceito'], 422);
        $pedido->status = 'aceito';
        $pedido->save();
        $cliente = $pedido->cliente;
        if ($cliente) {
            try {
                $cliente->notify(new DynamicNotification('pedido_confirmado', ['pedido_numero' => $pedido->numero ?? $pedido->id, 'prestador_nome' => $user->nome, 'pedido_id' => $pedido->id]));
            } catch (\Exception $e) {
                Log::error("Erro ao enviar notificação: " . $e->getMessage());
            }
        }
        $this->clearPrestadorCache($user->id);
        return response()->json(['success' => true, 'message' => 'Pedido aceito com sucesso', 'data' => $pedido]);
    }

    public function recusarSolicitacao(Request $request, $id)
    {
        $user = $request->user();
        $pedido = Pedido::where('prestador_id', $user->id)->find($id);
        if (!$pedido) return response()->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
        if ($pedido->status !== 'pendente') return response()->json(['success' => false, 'error' => 'Este pedido não pode ser recusado'], 422);
        $pedido->status = 'cancelado';
        $pedido->save();
        $cliente = $pedido->cliente;
        if ($cliente) {
            try {
                $cliente->notify(new DynamicNotification('pedido_cancelado', ['pedido_numero' => $pedido->numero ?? $pedido->id, 'pedido_id' => $pedido->id]));
            } catch (\Exception $e) {
                Log::error("Erro ao enviar notificação: " . $e->getMessage());
            }
        }
        $this->clearPrestadorCache($user->id);
        return response()->json(['success' => true, 'message' => 'Pedido recusado']);
    }

    // ==========================================
    // 6. AGENDA DO PRESTADOR
    // ==========================================
    public function agenda(Request $request)
    {
        return response()->json(['success' => true, 'data' => []]);
    }
    public function bloquearHorario(Request $request)
    {
        $validator = Validator::make($request->all(), ['data' => 'required|date', 'horario_inicio' => 'required|date_format:H:i', 'horario_fim' => 'required|date_format:H:i|after:horario_inicio']);
        if ($validator->fails()) return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        return response()->json(['success' => true, 'message' => 'Horário bloqueado com sucesso']);
    }
    public function desbloquearHorario($id)
    {
        return response()->json(['success' => true, 'message' => 'Horário desbloqueado']);
    }

    // ==========================================
    // 7. ESTATÍSTICAS DO PRESTADOR
    // ==========================================
    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $stats = Cache::remember("prestador_stats_{$userId}", self::CACHE_SHORT, function () use ($userId) {
            $result = DB::table('pedidos')->where('prestador_id', $userId)
                ->selectRaw("COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pedidos_pendentes, COUNT(CASE WHEN status IN ('aceito', 'em_andamento') AND DATE(data) = CURDATE() THEN 1 END) as servicos_hoje, COALESCE(SUM(CASE WHEN status = 'concluido' AND MONTH(created_at) = MONTH(NOW()) THEN valor ELSE 0 END), 0) as ganhos_mes, COALESCE(AVG(CASE WHEN status = 'concluido' THEN valor ELSE NULL END), 0) as ticket_medio")->first();
            $mediaAvaliacao = DB::table('avaliacoes')->where('prestador_id', $userId)->avg('nota');
            return [
                'pedidos_pendentes' => (int) ($result->pedidos_pendentes ?? 0),
                'servicos_hoje' => (int) ($result->servicos_hoje ?? 0),
                'avaliacao_media' => round($mediaAvaliacao ?? 0, 1),
                'ganhos_mes' => (float) ($result->ganhos_mes ?? 0),
                'ticket_medio' => (float) ($result->ticket_medio ?? 0),
            ];
        });
        return response()->json(['success' => true, 'data' => $stats]);
    }

    // ==========================================
    // 8. FINANCEIRO DO PRESTADOR
    // ==========================================
    public function ganhos(Request $request)
    {
        $userId = $request->user()->id;
        $ganhos = Cache::remember("prestador_ganhos_{$userId}", self::CACHE_MEDIUM, function () use ($userId) {
            $result = DB::table('pedidos')->where('prestador_id', $userId)
                ->selectRaw("COALESCE(SUM(CASE WHEN status = 'concluido' THEN valor ELSE 0 END), 0) as total, COALESCE(SUM(CASE WHEN status = 'concluido' AND MONTH(created_at) = MONTH(NOW()) THEN valor ELSE 0 END), 0) as mes, COALESCE(SUM(CASE WHEN status = 'concluido' AND WEEK(created_at) = WEEK(NOW()) THEN valor ELSE 0 END), 0) as semana, COALESCE(SUM(CASE WHEN status IN ('pendente', 'aceito', 'em_andamento') THEN valor ELSE 0 END), 0) as pendente")->first();
            return [
                'total' => (float) ($result->total ?? 0),
                'mes' => (float) ($result->mes ?? 0),
                'semana' => (float) ($result->semana ?? 0),
                'pendente' => (float) ($result->pendente ?? 0),
            ];
        });
        return response()->json(['success' => true, 'data' => $ganhos]);
    }

    public function saques(Request $request)
    {
        $user = $request->user();
        $saques = Cache::remember("prestador_saques_{$user->id}", self::CACHE_MEDIUM, function () use ($user) {
            return Transacao::where('user_id', $user->id)->where('tipo', 'saque')->select(['id', 'numero', 'valor', 'status', 'metodo', 'descricao', 'created_at'])->orderBy('created_at', 'desc')->get()->map(fn($s) => [
                'id' => (int) $s->id,
                'numero' => (string) $s->numero,
                'valor' => (float) $s->valor,
                'status' => (string) $s->status,
                'metodo' => (string) $s->metodo,
                'descricao' => $s->descricao,
                'created_at' => $s->created_at?->toISOString(),
            ])->toArray();
        });
        return response()->json(['success' => true, 'data' => $saques]);
    }

    public function solicitarSaque(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'valor' => 'required|numeric|min:100',
            'metodo' => 'required|in:mpesa,bancario',
            'conta' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $saldoDisponivel = Pedido::where('prestador_id', $user->id)->where('status', 'concluido')->sum('valor');
        $saquesPendentes = Transacao::where('user_id', $user->id)->where('tipo', 'saque')->where('status', 'pendente')->sum('valor');
        $saldo = $saldoDisponivel - $saquesPendentes;

        if ($request->valor > $saldo) {
            return response()->json(['success' => false, 'error' => 'Saldo insuficiente'], 422);
        }

        try {
            $saque = Transacao::create([
                'user_id' => $user->id,
                'numero' => 'SAQ-' . strtoupper(uniqid()),
                'tipo' => 'saque',
                'status' => 'pendente',
                'valor' => $request->valor,
                'descricao' => "Solicitação de saque via {$request->metodo}",
                'metodo' => $request->metodo,
                'detalhes' => json_encode(['conta' => $request->conta]),
            ]);
            $this->clearPrestadorCache($user->id);
            return response()->json(['success' => true, 'message' => 'Saque solicitado com sucesso', 'data' => $saque], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao solicitar saque: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao solicitar saque'], 500);
        }
    }

    public function historicoSaques(Request $request)
    {
        $user = $request->user();
        $page = $request->query('page', 1);
        $saques = Cache::remember("prestador_historico_saques_{$user->id}_{$page}", self::CACHE_MEDIUM, function () use ($user) {
            return Transacao::where('user_id', $user->id)->where('tipo', 'saque')->orderBy('created_at', 'desc')->paginate(20);
        });
        return response()->json(['success' => true, 'data' => $saques]);
    }

    // ==========================================
    // 9. PRÓXIMOS SERVIÇOS E AVALIAÇÕES RECENTES
    // ==========================================
    public function proximosServicos(Request $request)
    {
        $userId = $request->user()->id;
        $limit = min($request->query('limit', 5), 20);
        $servicos = Cache::remember("prestador_proximos_servicos_{$userId}_{$limit}", self::CACHE_MEDIUM, function () use ($userId, $limit) {
            return Pedido::where('prestador_id', $userId)
                ->whereIn('status', ['aceito', 'confirmado', 'em_andamento'])
                ->whereDate('data', '>=', now())
                ->with(['cliente:id,nome,foto,telefone', 'servico:id,nome,preco'])
                ->select(['id', 'numero', 'data', 'endereco', 'status', 'valor', 'observacoes', 'cliente_id', 'servico_id'])
                ->orderBy('data', 'asc')
                ->limit($limit)
                ->get()
                ->map(fn($p) => [
                    'id' => (int) $p->id,
                    'numero' => (string) $p->numero,
                    'cliente' => $p->cliente ? [
                        'id' => (int) $p->cliente->id,
                        'nome' => (string) $p->cliente->nome,
                        'foto' => $p->cliente->foto ? asset('storage/' . $p->cliente->foto) : null,
                        'telefone' => (string) $p->cliente->telefone,
                    ] : null,
                    'servico' => $p->servico ? [
                        'id' => (int) $p->servico->id,
                        'nome' => (string) $p->servico->nome,
                        'preco' => (float) $p->servico->preco,
                    ] : null,
                    'data' => $p->data ? (string) $p->data : null,
                    'endereco' => (string) $p->endereco,
                    'status' => (string) $p->status,
                    'valor' => (float) ($p->valor ?? 0),
                    'observacoes' => $p->observacoes,
                ])
                ->toArray();
        });
        return response()->json(['success' => true, 'data' => $servicos]);
    }

    public function avaliacoesRecentes(Request $request)
    {
        $userId = $request->user()->id;
        $limit = min($request->query('limit', 5), 20);
        $avaliacoes = Cache::remember("prestador_avaliacoes_recentes_{$userId}_{$limit}", self::CACHE_MEDIUM, function () use ($userId, $limit) {
            return Avaliacao::where('prestador_id', $userId)
                ->with('cliente:id,nome,foto')
                ->select(['id', 'nota', 'comentario', 'created_at', 'cliente_id'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($a) => [
                    'id' => (int) $a->id,
                    'nota' => (int) $a->nota,
                    'comentario' => $a->comentario,
                    'created_at' => $a->created_at?->toISOString(),
                    'cliente' => $a->cliente ? [
                        'id' => (int) $a->cliente->id,
                        'nome' => (string) $a->cliente->nome,
                        'foto' => $a->cliente->foto ? asset('storage/' . $a->cliente->foto) : null,
                    ] : null,
                ])
                ->toArray();
        });
        return response()->json(['success' => true, 'data' => $avaliacoes]);
    }

    // ==========================================
    // 10. INTERVALOS DO PRESTADOR
    // ==========================================
    public function intervalos(Request $request)
    {
        $user = $request->user();
        $intervalos = Cache::remember("prestador_intervalos_{$user->id}", self::CACHE_LONG, function () use ($user) {
            return PrestadorIntervalo::where('prestador_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($i) => [
                    'id' => (int) $i->id,
                    'dias' => $i->dias,
                    'inicio' => (string) $i->inicio,
                    'fim' => (string) $i->fim,
                    'descricao' => $i->descricao,
                    'ativo' => (bool) $i->ativo,
                    'created_at' => $i->created_at?->toISOString(),
                ])
                ->toArray();
        });
        return response()->json(['success' => true, 'data' => $intervalos]);
    }

    public function criarIntervalo(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'dias' => 'required|array|min:1',
            'dias.*' => 'string',
            'inicio' => 'required|date_format:H:i',
            'fim' => 'required|date_format:H:i|after:inicio',
            'descricao' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }
        try {
            $intervalo = PrestadorIntervalo::create([
                'prestador_id' => $user->id,
                'dias' => $request->dias,
                'inicio' => $request->inicio,
                'fim' => $request->fim,
                'descricao' => $request->descricao,
                'ativo' => true,
            ]);
            $this->clearPrestadorCache($user->id);
            return response()->json(['success' => true, 'message' => 'Intervalo criado com sucesso', 'data' => $intervalo], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar intervalo: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao criar intervalo'], 500);
        }
    }

    public function atualizarIntervalo(Request $request, $id)
    {
        $user = $request->user();
        $intervalo = PrestadorIntervalo::where('prestador_id', $user->id)->find($id);
        if (!$intervalo) {
            return response()->json(['success' => false, 'error' => 'Intervalo não encontrado'], 404);
        }
        $validator = Validator::make($request->all(), [
            'dias' => 'sometimes|array|min:1',
            'dias.*' => 'string',
            'inicio' => 'sometimes|date_format:H:i',
            'fim' => 'sometimes|date_format:H:i|after:inicio',
            'descricao' => 'nullable|string|max:255',
            'ativo' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }
        try {
            if ($request->has('dias')) $intervalo->dias = $request->dias;
            if ($request->has('inicio')) $intervalo->inicio = $request->inicio;
            if ($request->has('fim')) $intervalo->fim = $request->fim;
            if ($request->has('descricao')) $intervalo->descricao = $request->descricao;
            if ($request->has('ativo')) $intervalo->ativo = $request->ativo;
            $intervalo->save();
            $this->clearPrestadorCache($user->id);
            return response()->json(['success' => true, 'message' => 'Intervalo atualizado', 'data' => $intervalo]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar intervalo: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao atualizar intervalo'], 500);
        }
    }

    public function deletarIntervalo(Request $request, $id)
    {
        $user = $request->user();
        $intervalo = PrestadorIntervalo::where('prestador_id', $user->id)->find($id);
        if (!$intervalo) {
            return response()->json(['success' => false, 'error' => 'Intervalo não encontrado'], 404);
        }
        $intervalo->delete();
        $this->clearPrestadorCache($user->id);
        return response()->json(['success' => true, 'message' => 'Intervalo removido']);
    }

    // ==========================================
    // 11. DISPONIBILIDADE DO PRESTADOR - CORRIGIDA
    // ==========================================
    public function getDisponibilidade(Request $request)
    {
        $user = $request->user();
        $cacheKey = "prestador_disponibilidade_{$user->id}";

        $disponibilidade = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            $disponibilidade = PrestadorDisponibilidade::where('prestador_id', $user->id)->first();

            if (!$disponibilidade) {
                $disponibilidade = PrestadorDisponibilidade::create([
                    'prestador_id' => $user->id,
                    'configuracoes' => PrestadorDisponibilidade::getDefaultConfiguracoes(),
                    'horarios_padrao' => PrestadorDisponibilidade::getDefaultHorariosPadrao(),
                    'intervalos_padrao' => PrestadorDisponibilidade::getDefaultIntervalosPadrao(),
                    'ativo' => true,
                ]);
            }

            // ✅ CONVERTER horarios_padrao para o formato do frontend
            $horariosConvertidos = $this->converterHorariosParaFrontend($disponibilidade->horarios_padrao);

            return [
                'id' => $disponibilidade->id,
                'prestador_id' => $disponibilidade->prestador_id,
                'configuracoes' => $disponibilidade->configuracoes,
                'horarios_padrao' => $horariosConvertidos,
                'intervalos_padrao' => $disponibilidade->intervalos_padrao ?? [],
                'ativo' => $disponibilidade->ativo,
                'created_at' => $disponibilidade->created_at?->toISOString(),
                'updated_at' => $disponibilidade->updated_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $disponibilidade
        ]);
    }

    /**
     * Converte horarios_padrao do formato do banco para o formato do frontend
     *
     * @param array|string|null $horariosPadrao
     * @return array
     */
    private function converterHorariosParaFrontend($horariosPadrao): array
    {
        if (empty($horariosPadrao)) {
            return [];
        }

        // Se for string JSON, decodificar
        if (is_string($horariosPadrao)) {
            $horariosPadrao = json_decode($horariosPadrao, true);
        }

        if (!is_array($horariosPadrao)) {
            return [];
        }

        $resultado = [];

        foreach ($horariosPadrao as $dia => $config) {
            // Usar o mapeamento para o nome do dia no frontend
            $nomeDia = self::DIAS_MAP[$dia] ?? $dia;

            // Se for o formato antigo {ativo: true, horario: "8h-17h"}
            if (is_array($config) && isset($config['ativo'])) {
                if ($config['ativo'] && !empty($config['horario'])) {
                    // Horário específico - converter string "8h-17h" para array de horas
                    $resultado[$nomeDia] = $this->converterHorarioStringParaArray($config['horario']);
                } elseif ($config['ativo']) {
                    // Ativo sem horário específico - usar horários padrão
                    $resultado[$nomeDia] = $this->getHorariosPadraoPorDia($nomeDia);
                }
            }
            // Se for o formato já correto (array de strings)
            else if (is_array($config) && !isset($config['ativo'])) {
                $resultado[$nomeDia] = $config;
            }
            // Se for string simples
            else if (is_string($config) && !empty($config)) {
                $resultado[$nomeDia] = $this->converterHorarioStringParaArray($config);
            }
        }

        return $resultado;
    }

    /**
     * Converte string de horário "8h-17h" para array de horas
     *
     * @param string $horarioString
     * @return array
     */
    private function converterHorarioStringParaArray(string $horarioString): array
    {
        // Se já estiver no formato "08:00", retornar como array
        if (preg_match('/^\d{2}:\d{2}$/', $horarioString)) {
            return [$horarioString];
        }

        // Tentar extrair horários no formato "8h-17h" ou "8h às 17h"
        if (preg_match('/(\d{1,2})h?\s*[-–—]\s*(\d{1,2})h?/', $horarioString, $matches)) {
            $inicio = (int) $matches[1];
            $fim = (int) $matches[2];

            $horarios = [];
            for ($h = $inicio; $h < $fim; $h++) {
                $horarios[] = sprintf("%02d:00", $h);
            }
            return $horarios;
        }

        // Fallback: horário comercial padrão
        return ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
    }

    /**
     * Retorna horários padrão por dia da semana
     *
     * @param string $dia
     * @return array
     */
    private function getHorariosPadraoPorDia(string $dia): array
    {
        $diasComHorarioCompleto = ['segunda', 'terca', 'quarta', 'quinta', 'sexta'];
        $diasComHorarioReduzido = ['sabado'];

        if (in_array($dia, $diasComHorarioCompleto)) {
            return ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
        }

        if (in_array($dia, $diasComHorarioReduzido)) {
            return ['08:00', '09:00', '10:00', '11:00'];
        }

        return [];
    }

    public function updateDisponibilidade(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'configuracoes' => 'sometimes|array',
            'horarios_padrao' => 'sometimes|array',
            'intervalos_padrao' => 'sometimes|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }
        try {
            $disponibilidade = PrestadorDisponibilidade::where('prestador_id', $user->id)->first();
            if (!$disponibilidade) {
                $disponibilidade = new PrestadorDisponibilidade();
                $disponibilidade->prestador_id = $user->id;
            }
            if ($request->has('configuracoes')) {
                $disponibilidade->configuracoes = array_merge(PrestadorDisponibilidade::getDefaultConfiguracoes(), $request->configuracoes);
            }
            if ($request->has('horarios_padrao')) {
                // Converter do formato do frontend para o formato do banco
                $disponibilidade->horarios_padrao = $request->horarios_padrao;
            }
            if ($request->has('intervalos_padrao')) {
                $disponibilidade->intervalos_padrao = $request->intervalos_padrao;
            }
            $disponibilidade->save();
            $this->clearPrestadorCache($user->id);
            return response()->json(['success' => true, 'message' => 'Configurações atualizadas', 'data' => $disponibilidade]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar configurações: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao atualizar configurações'], 500);
        }
    }

    // ==========================================
    // 12. MÉTODOS AUXILIARES
    // ==========================================
    private function processCategorias($categoriasInput): array
    {
        if (!$categoriasInput) return [];
        $categoriasData = is_string($categoriasInput) ? json_decode($categoriasInput, true) : $categoriasInput;
        if (!is_array($categoriasData)) return [];
        if (isset($categoriasData[0]['value'])) {
            return array_column($categoriasData, 'value');  // ✅ Extrai os IDs do formato [{value: 3}, {value: 5}]
        }
        return $categoriasData;
    }
    private function gerarProfissao(array $categoriasIds, ?string $profissaoManual): string
    {
        if (!empty($profissaoManual)) return $profissaoManual;
        if (empty($categoriasIds)) return 'Prestador de Serviços';

        $categoriasNomes = DB::table('categorias')
            ->whereIn('id', $categoriasIds)
            ->pluck('nome')
            ->toArray();

        if (empty($categoriasNomes)) return 'Prestador de Serviços';

        $count = count($categoriasNomes);
        if ($count === 1) {
            return $categoriasNomes[0] . ' Profissional';
        }
        if ($count === 2) {
            return $categoriasNomes[0] . ' e ' . $categoriasNomes[1];
        }
        return $categoriasNomes[0] . ' e outros serviços';
    }

    private function processPortfolio(Request $request): array
    {
        $portfolioPaths = [];
        for ($i = 0; $i < 3; $i++) {
            if ($request->hasFile("portfolio.{$i}")) {
                $file = $request->file("portfolio.{$i}");
                if ($file && $file->isValid()) {
                    $portfolioPaths[] = $file->store('fotos/portfolio', 'public');
                }
            }
        }
        return $portfolioPaths;
    }

    private function processDisponibilidadeData($disponibilidade): array
    {
        if (!$disponibilidade) return [];
        return is_string($disponibilidade) ? json_decode($disponibilidade, true) : $disponibilidade;
    }

    private function buildUserData(Request $request, string $profissao, array $categoriasIds, array $portfolioPaths, array $disponibilidadeData): array
    {
        return [
            'nome' => $request->nome,
            'email' => $request->email,
            'telefone' => $request->telefone,
            'password' => Hash::make($request->password),
            'endereco' => $request->endereco,
            'tipo' => 'prestador',
            'profissao' => $profissao,
            'sobre' => $request->sobre ?? $request->descricao,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'preferences' => json_encode([
                'descricao' => $request->descricao,
                'categorias' => $categoriasIds,
                'portfolio' => $portfolioPaths,
                'raio' => $request->raio,
                'disponibilidade' => $disponibilidadeData,
            ]),
        ];
    }

    private function saveCategorias(int $userId, array $categoriasIds): void
    {
        if (empty($categoriasIds)) return;

        $validCategories = [];
        foreach ($categoriasIds as $categoriaId) {
            if (Categoria::where('id', $categoriaId)->exists()) {
                $validCategories[] = $categoriaId;
            }
        }

        if (empty($validCategories)) return;

        $insertData = array_map(fn($catId) => [
            'user_id' => $userId,
            'categoria_id' => $catId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $validCategories);

        DB::table('prestador_categorias')->insert($insertData);
    }

    private function saveDisponibilidade(int $userId, array $disponibilidadeData): void
    {
        if (empty($disponibilidadeData)) return;
        try {
            PrestadorDisponibilidade::updateOrCreate(
                ['prestador_id' => $userId],
                [
                    'configuracoes' => PrestadorDisponibilidade::getDefaultConfiguracoes(),
                    'horarios_padrao' => $disponibilidadeData,
                    'intervalos_padrao' => [],
                    'ativo' => true,
                ]
            );
        } catch (\Exception $e) {
            Log::warning("Erro ao salvar disponibilidade: " . $e->getMessage());
        }
    }

    private function buscarCategoriasEmLote(array $prestadorIds): array
    {
        if (empty($prestadorIds)) return [];

        $categorias = DB::table('prestador_categorias')
            ->whereIn('user_id', $prestadorIds)
            ->join('categorias', 'prestador_categorias.categoria_id', '=', 'categorias.id')
            ->select('prestador_categorias.user_id', 'categorias.id', 'categorias.nome', 'categorias.icone', 'categorias.cor')
            ->get();

        $map = [];
        foreach ($categorias as $cat) {
            $map[$cat->user_id][] = [
                'id' => (int) $cat->id,
                'nome' => (string) $cat->nome,
                'icone' => (string) ($cat->icone ?? 'category'),
                'cor' => (string) ($cat->cor ?? 'primary'),
            ];
        }
        return $map;
    }

    private function getPortfolioUrls(array $preferences): array
    {
        if (empty($preferences['portfolio'])) return [];
        return array_map(
            fn($path) => asset('storage/' . $path),
            array_slice($preferences['portfolio'], 0, 6)
        );
    }

    private function getServicosPrestador(int $prestadorId): array
    {
        return DB::table('servicos')
            ->where('prestador_id', $prestadorId)
            ->where('ativo', true)
            ->select(['id', 'nome', 'preco', 'duracao', 'descricao', 'icone'])
            ->limit(10)
            ->get()
            ->map(fn($s) => [
                'id' => (int) $s->id,
                'nome' => (string) $s->nome,
                'preco' => (float) $s->preco,
                'duracao' => (int) $s->duracao,
                'descricao' => $s->descricao,
                'icone' => (string) ($s->icone ?? 'handyman'),
            ])
            ->toArray();
    }

    private function getCategoriasPrestador(int $prestadorId): array
    {
        return DB::table('prestador_categorias')
            ->join('categorias', 'prestador_categorias.categoria_id', '=', 'categorias.id')
            ->where('prestador_categorias.user_id', $prestadorId)
            ->select(['categorias.id', 'categorias.nome', 'categorias.icone', 'categorias.cor'])
            ->get()
            ->map(fn($cat) => [
                'id' => (int) $cat->id,
                'nome' => (string) $cat->nome,
                'icone' => (string) ($cat->icone ?? 'category'),
                'cor' => (string) ($cat->cor ?? 'primary'),
            ])
            ->toArray();
    }

    private function getUltimasAvaliacoes(int $prestadorId): array
    {
        return DB::table('avaliacoes')
            ->where('prestador_id', $prestadorId)
            ->leftJoin('users as clientes', 'avaliacoes.cliente_id', '=', 'clientes.id')
            ->select([
                'avaliacoes.id',
                'avaliacoes.nota',
                'avaliacoes.comentario',
                'avaliacoes.created_at',
                'clientes.id as cliente_id',
                'clientes.nome as cliente_nome',
                'clientes.foto as cliente_foto'
            ])
            ->orderBy('avaliacoes.created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'id' => (int) $a->id,
                'nota' => (int) $a->nota,
                'comentario' => $a->comentario,
                'created_at' => $a->created_at,
                'cliente' => $a->cliente_id ? [
                    'id' => (int) $a->cliente_id,
                    'nome' => (string) $a->cliente_nome,
                    'foto' => $a->cliente_foto ? asset('storage/' . $a->cliente_foto) : null,
                ] : null,
            ])
            ->toArray();
    }

    private function calcularDistancia($lat1, $lon1, $lat2, $lon2): float
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 9999;

        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function clearPrestadorCache($userId): void
    {
        $keys = [
            "prestador_stats_{$userId}",
            "prestador_ganhos_{$userId}",
            "prestador_servicos_{$userId}",
            "prestador_intervalos_{$userId}",
            "prestador_disponibilidade_{$userId}",
            "prestador_saques_{$userId}",
            "prestador_categorias_{$userId}",
            "prestador_detalhes_{$userId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        for ($page = 1; $page <= 3; $page++) {
            Cache::forget("prestador_solicitacoes_{$userId}_all_{$page}");
            Cache::forget("prestador_historico_saques_{$userId}_{$page}");
        }

        foreach (['pendente', 'aceito', 'concluido', 'cancelado'] as $status) {
            for ($page = 1; $page <= 3; $page++) {
                Cache::forget("prestador_solicitacoes_{$userId}_{$status}_{$page}");
            }
        }
    }

    public function clearCache(Request $request)
    {
        $this->clearPrestadorCache($request->user()->id);
        return response()->json(['success' => true, 'message' => 'Cache limpo com sucesso']);
    }

    public function health()
    {
        return response()->json([
            'success' => true,
            'message' => 'API funcionando',
            'timestamp' => now()->toISOString(),
            'cache_driver' => config('cache.default')
        ]);
    }
}
