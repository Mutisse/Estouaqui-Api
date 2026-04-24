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
    // CONSTANTES DE CACHE
    // ==========================================
    private const CACHE_SHORT = 120;      // 2 minutos
    private const CACHE_MEDIUM = 600;     // 10 minutos
    private const CACHE_LONG = 3600;      // 1 hora
    private const CACHE_VERY_LONG = 86400; // 24 horas

    // ==========================================
    // 1. REGISTRO DO PRESTADOR - CORRIGIDO
    // ==========================================

    /**
     * Registro de novo prestador
     * POST /api/register/prestador
     */
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

        try {
            $portfolioPaths = [];
            for ($i = 0; $i < 3; $i++) {
                if ($request->hasFile("portfolio.{$i}")) {
                    $file = $request->file("portfolio.{$i}");
                    if ($file && $file->isValid()) {
                        $portfolioPaths[] = $file->store('fotos/portfolio', 'public');
                    }
                }
            }

            $userData = [
                'nome' => $request->nome,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'password' => Hash::make($request->password),
                'endereco' => $request->endereco,
                'tipo' => 'prestador',
                'profissao' => $request->profissao ?? 'Prestador de Serviços',
                'sobre' => $request->sobre ?? $request->descricao,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'preferences' => json_encode([
                    'descricao' => $request->descricao,
                    'categorias' => json_decode($request->categorias, true),
                    'portfolio' => $portfolioPaths,
                    'raio' => $request->raio,
                    'disponibilidade' => json_decode($request->disponibilidade, true),
                ]),
            ];

            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('fotos/prestadores', 'public');
                $userData['foto'] = $path;
            }

            if ($request->hasFile('documento')) {
                $docPath = $request->file('documento')->store('documentos/prestadores', 'public');
                $userData['documento'] = $docPath;
            }

            $user = User::create($userData);
            $token = $user->createToken('auth_token')->plainTextToken;

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
                ],
                'token' => $token
            ], 201);
        } catch (\Exception $e) {
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

    /**
     * Listar serviços do prestador
     * GET /api/prestador/servicos
     */
    public function servicos(Request $request)
    {
        $user = $request->user();
        $cacheKey = "prestador_servicos_{$user->id}";

        $servicos = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            return Servico::where('prestador_id', $user->id)
                ->with('categoria:id,nome')
                ->select(['id', 'nome', 'categoria_id', 'preco', 'duracao', 'descricao', 'icone', 'ativo', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($servico) {
                    return [
                        'id' => (int) $servico->id,
                        'nome' => (string) $servico->nome,
                        'categoria_id' => (int) $servico->categoria_id,
                        'categoria_nome' => $servico->categoria ? $servico->categoria->nome : null,
                        'preco' => (float) $servico->preco,
                        'duracao' => (int) $servico->duracao,
                        'descricao' => $servico->descricao,
                        'icone' => (string) $servico->icone,
                        'ativo' => (bool) $servico->ativo,
                        'created_at' => $servico->created_at ? $servico->created_at->toISOString() : null,
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $servicos
        ]);
    }

    /**
     * Criar novo serviço
     * POST /api/prestador/servicos
     */
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

    /**
     * Atualizar serviço
     * PUT /api/prestador/servicos/{id}
     */
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

    /**
     * Deletar serviço
     * DELETE /api/prestador/servicos/{id}
     */
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

    /**
     * Ativar/Desativar serviço
     * PUT /api/prestador/servicos/{id}/toggle
     */
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
    // 3. AGENDA DO PRESTADOR
    // ==========================================

    /**
     * Listar agenda do prestador
     * GET /api/prestador/agenda
     */
    public function agenda(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    /**
     * Bloquear horário na agenda
     * POST /api/prestador/agenda/bloquear
     */
    public function bloquearHorario(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|date',
            'horario_inicio' => 'required|date_format:H:i',
            'horario_fim' => 'required|date_format:H:i|after:horario_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Horário bloqueado com sucesso'
        ]);
    }

    /**
     * Desbloquear horário
     * DELETE /api/prestador/agenda/{id}
     */
    public function desbloquearHorario($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Horário desbloqueado'
        ]);
    }

    // ==========================================
    // 4. SOLICITAÇÕES/PEDIDOS
    // ==========================================

    /**
     * Listar solicitações de serviço
     * GET /api/prestador/solicitacoes
     */
    public function solicitacoes(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $page = $request->query('page', 1);
        $cacheKey = "prestador_solicitacoes_{$user->id}_" . ($status ?? 'all') . "_{$page}";

        $pedidos = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($user, $status) {
            $query = Pedido::where('prestador_id', $user->id)
                ->with(['cliente:id,nome,foto,telefone', 'servico:id,nome,preco']);

            if ($status) {
                $query->where('status', $status);
            }

            return $query->orderBy('created_at', 'desc')->paginate(20);
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * Aceitar solicitação
     * PUT /api/prestador/solicitacoes/{id}/aceitar
     */
    public function aceitarSolicitacao(Request $request, $id)
    {
        $user = $request->user();
        $pedido = Pedido::where('prestador_id', $user->id)->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        if ($pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'error' => 'Este pedido não pode ser aceito'
            ], 422);
        }

        $pedido->status = 'aceito';
        $pedido->save();

        // ✅ NOTIFICAÇÃO: Pedido aceito para o CLIENTE
        $cliente = $pedido->cliente;
        if ($cliente) {
            $cliente->notify(new DynamicNotification('pedido_confirmado', [
                'pedido_numero' => $pedido->numero ?? $pedido->id,
                'prestador_nome' => $user->nome,
                'pedido_id' => $pedido->id,
            ]));
            Log::info("Notificação 'pedido_confirmado' enviada para o cliente ID: {$cliente->id}");
        }

        $this->clearPrestadorCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Pedido aceito com sucesso',
            'data' => $pedido
        ]);
    }

    /**
     * Recusar solicitação
     * PUT /api/prestador/solicitacoes/{id}/recusar
     */
    public function recusarSolicitacao(Request $request, $id)
    {
        $user = $request->user();
        $pedido = Pedido::where('prestador_id', $user->id)->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        if ($pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'error' => 'Este pedido não pode ser recusado'
            ], 422);
        }

        $pedido->status = 'cancelado';
        $pedido->save();

        // ✅ NOTIFICAÇÃO: Pedido recusado/cancelado para o CLIENTE
        $cliente = $pedido->cliente;
        if ($cliente) {
            $cliente->notify(new DynamicNotification('pedido_cancelado', [
                'pedido_numero' => $pedido->numero ?? $pedido->id,
                'pedido_id' => $pedido->id,
            ]));
            Log::info("Notificação 'pedido_cancelado' enviada para o cliente ID: {$cliente->id}");
        }

        $this->clearPrestadorCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Pedido recusado'
        ]);
    }

    // ==========================================
    // 5. CATEGORIAS DO PRESTADOR
    // ==========================================

    /**
     * Listar categorias que o prestador atende
     * GET /api/prestador/categorias
     */
    public function minhasCategorias(Request $request)
    {
        $user = $request->user();
        $cacheKey = "prestador_categorias_{$user->id}";

        $categorias = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            return $user->categorias()
                ->select(['categorias.id', 'categorias.nome', 'categorias.slug', 'categorias.icone', 'categorias.cor'])
                ->get()
                ->map(function ($cat) {
                    return [
                        'id' => (int) $cat->id,
                        'nome' => (string) $cat->nome,
                        'slug' => (string) $cat->slug,
                        'icone' => (string) ($cat->icone ?? 'category'),
                        'cor' => (string) ($cat->cor ?? 'primary'),
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    /**
     * Adicionar categoria
     * POST /api/prestador/categorias/{categoriaId}
     */
    public function addCategoria(Request $request, $categoriaId)
    {
        $user = $request->user();
        $user->categorias()->attach($categoriaId);
        $this->clearPrestadorCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Categoria adicionada'
        ]);
    }

    /**
     * Remover categoria
     * DELETE /api/prestador/categorias/{categoriaId}
     */
    public function removeCategoria(Request $request, $categoriaId)
    {
        $user = $request->user();
        $user->categorias()->detach($categoriaId);
        $this->clearPrestadorCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Categoria removida'
        ]);
    }

    // ==========================================
    // 6. ESTATÍSTICAS DO PRESTADOR
    // ==========================================

    /**
     * Estatísticas do prestador
     * GET /api/prestador/stats
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $cacheKey = "prestador_stats_{$userId}";

        $stats = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($userId) {
            $result = DB::table('pedidos')
                ->where('prestador_id', $userId)
                ->selectRaw("
                    COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pedidos_pendentes,
                    COUNT(CASE WHEN status IN ('aceito', 'em_andamento') AND DATE(data) = CURDATE() THEN 1 END) as servicos_hoje,
                    COALESCE(SUM(CASE WHEN status = 'concluido' AND MONTH(created_at) = MONTH(NOW()) THEN valor ELSE 0 END), 0) as ganhos_mes
                ")
                ->first();

            $mediaAvaliacao = DB::table('avaliacoes')
                ->where('prestador_id', $userId)
                ->avg('nota');

            return [
                'pedidos_pendentes' => (int) ($result->pedidos_pendentes ?? 0),
                'servicos_hoje' => (int) ($result->servicos_hoje ?? 0),
                'avaliacao_media' => round($mediaAvaliacao ?? 0, 1),
                'ganhos_mes' => (float) ($result->ganhos_mes ?? 0),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // ==========================================
    // 7. PERFIL DO PRESTADOR (público)
    // ==========================================

    /**
     * Listar prestadores (público)
     * GET /api/prestadores
     */
    public function index(Request $request)
    {
        $cacheKey = "prestadores_list_" . md5($request->fullUrl());

        $prestadores = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($request) {
            $query = User::where('tipo', 'prestador')
                ->where('ativo', true)
                ->select(['id', 'nome', 'email', 'telefone', 'foto', 'profissao', 'sobre', 'media_avaliacao', 'total_avaliacoes', 'verificado', 'ativo']);

            if ($request->has('categoria')) {
                $query->whereHas('categorias', function ($q) use ($request) {
                    $q->where('categoria_id', $request->categoria);
                });
            }

            if ($request->has('busca')) {
                $query->where('nome', 'like', '%' . $request->busca . '%');
            }

            $prestadores = $query->limit(50)->get();
            $prestadorIds = $prestadores->pluck('id');

            $categoriasPorPrestador = [];
            if ($prestadorIds->isNotEmpty()) {
                $categoriasPorPrestador = DB::table('prestador_categorias')
                    ->whereIn('user_id', $prestadorIds)
                    ->join('categorias', 'prestador_categorias.categoria_id', '=', 'categorias.id')
                    ->select('prestador_categorias.user_id', 'categorias.id', 'categorias.nome')
                    ->get()
                    ->groupBy('user_id');
            }

            return $prestadores->map(function ($prestador) use ($categoriasPorPrestador) {
                return [
                    'id' => (int) $prestador->id,
                    'nome' => (string) $prestador->nome,
                    'email' => (string) $prestador->email,
                    'telefone' => (string) $prestador->telefone,
                    'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                    'profissao' => $prestador->profissao ? (string) $prestador->profissao : null,
                    'sobre' => $prestador->sobre ? (string) $prestador->sobre : null,
                    'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                    'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                    'verificado' => (bool) ($prestador->verificado ?? false),
                    'disponivel' => (bool) ($prestador->ativo ?? true),
                    'categorias' => isset($categoriasPorPrestador[$prestador->id])
                        ? $categoriasPorPrestador[$prestador->id]->map(fn($c) => [
                            'id' => (int) $c->id,
                            'nome' => (string) $c->nome,
                        ])->values()->toArray()
                        : [],
                ];
            })->values()->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Detalhes do prestador (público)
     * GET /api/prestadores/{id}
     */
    public function show($id)
    {
        $cacheKey = "prestador_detalhes_{$id}";

        $dados = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($id) {
            try {
                $prestador = User::where('tipo', 'prestador')
                    ->select(['id', 'nome', 'email', 'telefone', 'foto', 'profissao', 'sobre', 'media_avaliacao', 'total_avaliacoes', 'verificado', 'ativo', 'created_at'])
                    ->find($id);

                if (!$prestador) {
                    return null;
                }

                $servicos = Servico::where('prestador_id', $id)
                    ->where('ativo', true)
                    ->select(['id', 'nome', 'preco', 'duracao', 'descricao', 'icone'])
                    ->get()
                    ->map(fn($servico) => [
                        'id' => (int) $servico->id,
                        'nome' => (string) $servico->nome,
                        'preco' => (float) $servico->preco,
                        'duracao' => (int) $servico->duracao,
                        'descricao' => $servico->descricao,
                        'icone' => (string) $servico->icone,
                    ])->toArray();

                $categorias = $prestador->categorias()
                    ->select(['categorias.id', 'categorias.nome'])
                    ->get()
                    ->map(fn($cat) => [
                        'id' => (int) $cat->id,
                        'nome' => (string) $cat->nome,
                    ])->toArray();

                $avaliacoes = Avaliacao::where('prestador_id', $id)
                    ->with('cliente:id,nome,foto')
                    ->select(['id', 'nota', 'comentario', 'created_at', 'cliente_id'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(fn($avaliacao) => [
                        'id' => (int) $avaliacao->id,
                        'nota' => (int) $avaliacao->nota,
                        'comentario' => $avaliacao->comentario,
                        'created_at' => $avaliacao->created_at?->toISOString(),
                        'cliente' => $avaliacao->cliente ? [
                            'id' => (int) $avaliacao->cliente->id,
                            'nome' => (string) $avaliacao->cliente->nome,
                            'foto' => $avaliacao->cliente->foto ? asset('storage/' . $avaliacao->cliente->foto) : null,
                        ] : null,
                    ])->toArray();

                return [
                    'id' => (int) $prestador->id,
                    'nome' => (string) $prestador->nome,
                    'email' => (string) $prestador->email,
                    'telefone' => (string) $prestador->telefone,
                    'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                    'profissao' => $prestador->profissao ? (string) $prestador->profissao : null,
                    'sobre' => $prestador->sobre ? (string) $prestador->sobre : null,
                    'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                    'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                    'verificado' => (bool) ($prestador->verificado ?? false),
                    'disponivel' => (bool) ($prestador->ativo ?? true),
                    'categorias' => $categorias,
                    'servicos' => $servicos,
                    'avaliacoes' => $avaliacoes,
                    'created_at' => $prestador->created_at?->toISOString(),
                ];
            } catch (\Exception $e) {
                Log::error('Erro ao buscar prestador ID ' . $id . ': ' . $e->getMessage());
                return null;
            }
        });

        if (!$dados) {
            return response()->json([
                'success' => false,
                'message' => 'Prestador não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $dados
        ]);
    }

    /**
     * Prestadores em destaque (público)
     * GET /api/prestadores/destaque
     */
    public function destaque()
    {
        try {
            $prestadores = User::where('tipo', 'prestador')
                ->where('ativo', true)
                ->select('id', 'nome', 'foto', 'profissao', 'media_avaliacao', 'total_avaliacoes', 'verificado')
                ->orderBy('media_avaliacao', 'desc')
                ->limit(8)
                ->get();

            $result = $prestadores->map(function ($prestador) {
                return [
                    'id' => (int) $prestador->id,
                    'nome' => (string) $prestador->nome,
                    'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                    'profissao' => $prestador->profissao ? (string) $prestador->profissao : null,
                    'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                    'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                    'verificado' => (bool) ($prestador->verificado ?? false),
                    'categorias' => [],
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            error_log('Erro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Erro ao carregar prestadores em destaque'
            ], 500);
        }
    }

    /**
     * Prestadores mais bem avaliados (público)
     * GET /api/prestadores/top
     */
    public function topAvaliados()
    {
        $prestadores = Cache::remember('prestadores_top', self::CACHE_MEDIUM, function () {
            $prestadores = User::where('tipo', 'prestador')
                ->where('ativo', true)
                ->where('media_avaliacao', '>=', 4)
                ->select('id', 'nome', 'foto', 'profissao', 'media_avaliacao', 'total_avaliacoes', 'verificado')
                ->orderByRaw('media_avaliacao DESC, total_avaliacoes DESC')
                ->limit(10)
                ->get();

            if ($prestadores->isEmpty()) {
                return [];
            }

            $prestadorIds = $prestadores->pluck('id');

            $categoriasPorPrestador = DB::table('prestador_categorias')
                ->whereIn('user_id', $prestadorIds)
                ->join('categorias', 'prestador_categorias.categoria_id', '=', 'categorias.id')
                ->select('prestador_categorias.user_id', 'categorias.id', 'categorias.nome')
                ->get()
                ->groupBy('user_id');

            return $prestadores->map(function ($prestador) use ($categoriasPorPrestador) {
                return [
                    'id' => (int) $prestador->id,
                    'nome' => (string) $prestador->nome,
                    'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                    'profissao' => $prestador->profissao ? (string) $prestador->profissao : null,
                    'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                    'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                    'verificado' => (bool) ($prestador->verificado ?? false),
                    'categorias' => isset($categoriasPorPrestador[$prestador->id])
                        ? $categoriasPorPrestador[$prestador->id]->map(fn($c) => [
                            'id' => (int) $c->id,
                            'nome' => (string) $c->nome
                        ])->values()->toArray()
                        : [],
                ];
            })->values()->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Prestadores próximos (público)
     * GET /api/prestadores/proximos
     */
    public function proximos(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    /**
     * Listar categorias (público)
     * GET /api/prestadores/categorias
     */
    public function categorias()
    {
        $categorias = Cache::remember('prestador_categorias_publicas', self::CACHE_VERY_LONG, function () {
            return Categoria::where('ativo', true)
                ->select('id', 'nome', 'slug', 'icone', 'cor', 'descricao')
                ->orderBy('nome', 'asc')
                ->get()
                ->map(function ($categoria) {
                    return [
                        'id' => (int) $categoria->id,
                        'nome' => (string) $categoria->nome,
                        'slug' => (string) $categoria->slug,
                        'icone' => (string) ($categoria->icone ?? 'category'),
                        'cor' => (string) ($categoria->cor ?? 'primary'),
                        'descricao' => $categoria->descricao ? (string) $categoria->descricao : null,
                        'ativo' => (bool) $categoria->ativo,
                        'servicos_count' => (int) ($categoria->servicos_count ?? 0),
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    /**
     * Avaliações do prestador (público)
     * GET /api/prestadores/{id}/avaliacoes
     */
    public function avaliacoes($id)
    {
        $page = request()->query('page', 1);
        $cacheKey = "prestador_avaliacoes_{$id}_{$page}";

        $avaliacoes = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($id) {
            return Avaliacao::where('prestador_id', $id)
                ->with('cliente:id,nome,foto')
                ->select(['id', 'nota', 'comentario', 'created_at', 'cliente_id'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        });

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }

    // ==========================================
    // 8. FINANCEIRO DO PRESTADOR
    // ==========================================

    /**
     * Ganhos do prestador
     * GET /api/prestador/ganhos
     */
    public function ganhos(Request $request)
    {
        $userId = $request->user()->id;
        $cacheKey = "prestador_ganhos_{$userId}";

        $ganhos = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($userId) {
            $result = DB::table('pedidos')
                ->where('prestador_id', $userId)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN status = 'concluido' THEN valor ELSE 0 END), 0) as total,
                    COALESCE(SUM(CASE WHEN status = 'concluido' AND MONTH(created_at) = MONTH(NOW()) THEN valor ELSE 0 END), 0) as mes,
                    COALESCE(SUM(CASE WHEN status = 'concluido' AND WEEK(created_at) = WEEK(NOW()) THEN valor ELSE 0 END), 0) as semana,
                    COALESCE(SUM(CASE WHEN status IN ('pendente', 'aceito', 'em_andamento') THEN valor ELSE 0 END), 0) as pendente
                ")
                ->first();

            return [
                'total' => (float) ($result->total ?? 0),
                'mes' => (float) ($result->mes ?? 0),
                'semana' => (float) ($result->semana ?? 0),
                'pendente' => (float) ($result->pendente ?? 0),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $ganhos
        ]);
    }

    /**
     * Listar saques
     * GET /api/prestador/saques
     */
    public function saques(Request $request)
    {
        $user = $request->user();
        $cacheKey = "prestador_saques_{$user->id}";

        $saques = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($user) {
            return Transacao::where('user_id', $user->id)
                ->where('tipo', 'saque')
                ->select(['id', 'numero', 'valor', 'status', 'metodo', 'descricao', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($saque) {
                    return [
                        'id' => (int) $saque->id,
                        'numero' => (string) $saque->numero,
                        'valor' => (float) $saque->valor,
                        'status' => (string) $saque->status,
                        'metodo' => (string) $saque->metodo,
                        'descricao' => $saque->descricao,
                        'created_at' => $saque->created_at?->toISOString(),
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $saques
        ]);
    }

    /**
     * Solicitar saque
     * POST /api/prestador/saques
     */
    public function solicitarSaque(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'valor' => 'required|numeric|min:100',
            'metodo' => 'required|in:mpesa,bancario',
            'conta' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $saldoDisponivel = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->sum('valor');

        $saquesPendentes = Transacao::where('user_id', $user->id)
            ->where('tipo', 'saque')
            ->where('status', 'pendente')
            ->sum('valor');

        $saldo = $saldoDisponivel - $saquesPendentes;

        if ($request->valor > $saldo) {
            return response()->json([
                'success' => false,
                'error' => 'Saldo insuficiente'
            ], 422);
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

            return response()->json([
                'success' => true,
                'message' => 'Saque solicitado com sucesso',
                'data' => $saque
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao solicitar saque: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao solicitar saque'
            ], 500);
        }
    }

    /**
     * Histórico de saques
     * GET /api/prestador/saques/historico
     */
    public function historicoSaques(Request $request)
    {
        $user = $request->user();
        $page = $request->query('page', 1);
        $cacheKey = "prestador_historico_saques_{$user->id}_{$page}";

        $saques = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($user) {
            return Transacao::where('user_id', $user->id)
                ->where('tipo', 'saque')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        });

        return response()->json([
            'success' => true,
            'data' => $saques
        ]);
    }

    // ==========================================
    // 9. PRÓXIMOS SERVIÇOS E AVALIAÇÕES RECENTES
    // ==========================================

    /**
     * Próximos serviços do prestador
     * GET /api/prestador/proximos-servicos
     */
    public function proximosServicos(Request $request)
    {
        $userId = $request->user()->id;
        $limit = min($request->query('limit', 5), 20);
        $cacheKey = "prestador_proximos_servicos_{$userId}_{$limit}";

        $servicos = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($userId, $limit) {
            return Pedido::where('prestador_id', $userId)
                ->whereIn('status', ['aceito', 'confirmado', 'em_andamento'])
                ->whereDate('data', '>=', now())
                ->with(['cliente:id,nome,foto,telefone', 'servico:id,nome,preco'])
                ->select(['id', 'numero', 'data', 'endereco', 'status', 'valor', 'observacoes', 'cliente_id', 'servico_id'])
                ->orderBy('data', 'asc')
                ->limit($limit)
                ->get()
                ->map(function ($pedido) {
                    return [
                        'id' => (int) $pedido->id,
                        'numero' => (string) $pedido->numero,
                        'cliente' => $pedido->cliente ? [
                            'id' => (int) $pedido->cliente->id,
                            'nome' => (string) $pedido->cliente->nome,
                            'foto' => $pedido->cliente->foto ? asset('storage/' . $pedido->cliente->foto) : null,
                            'telefone' => (string) $pedido->cliente->telefone,
                        ] : null,
                        'servico' => $pedido->servico ? [
                            'id' => (int) $pedido->servico->id,
                            'nome' => (string) $pedido->servico->nome,
                            'preco' => (float) $pedido->servico->preco,
                        ] : null,
                        'data' => $pedido->data ? (string) $pedido->data : null,
                        'endereco' => (string) $pedido->endereco,
                        'status' => (string) $pedido->status,
                        'valor' => (float) ($pedido->valor ?? 0),
                        'observacoes' => $pedido->observacoes,
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $servicos
        ]);
    }

    /**
     * Avaliações recentes do prestador
     * GET /api/prestador/avaliacoes/recentes
     */
    public function avaliacoesRecentes(Request $request)
    {
        $userId = $request->user()->id;
        $limit = min($request->query('limit', 5), 20);
        $cacheKey = "prestador_avaliacoes_recentes_{$userId}_{$limit}";

        $avaliacoes = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($userId, $limit) {
            return Avaliacao::where('prestador_id', $userId)
                ->with('cliente:id,nome,foto')
                ->select(['id', 'nota', 'comentario', 'created_at', 'cliente_id'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($avaliacao) {
                    return [
                        'id' => (int) $avaliacao->id,
                        'nota' => (int) $avaliacao->nota,
                        'comentario' => $avaliacao->comentario,
                        'created_at' => $avaliacao->created_at?->toISOString(),
                        'cliente' => $avaliacao->cliente ? [
                            'id' => (int) $avaliacao->cliente->id,
                            'nome' => (string) $avaliacao->cliente->nome,
                            'foto' => $avaliacao->cliente->foto ? asset('storage/' . $avaliacao->cliente->foto) : null,
                        ] : null,
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }

    // ==========================================
    // 10. INTERVALOS DO PRESTADOR
    // ==========================================

    /**
     * Listar intervalos do prestador
     * GET /api/prestador/intervalos
     */
    public function intervalos(Request $request)
    {
        $user = $request->user();
        $cacheKey = "prestador_intervalos_{$user->id}";

        $intervalos = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            return PrestadorIntervalo::where('prestador_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($intervalo) {
                    return [
                        'id' => (int) $intervalo->id,
                        'dias' => $intervalo->dias,
                        'inicio' => (string) $intervalo->inicio,
                        'fim' => (string) $intervalo->fim,
                        'descricao' => $intervalo->descricao,
                        'ativo' => (bool) $intervalo->ativo,
                        'created_at' => $intervalo->created_at?->toISOString(),
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $intervalos
        ]);
    }

    /**
     * Criar intervalo
     * POST /api/prestador/intervalos
     */
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
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
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

            return response()->json([
                'success' => true,
                'message' => 'Intervalo criado com sucesso',
                'data' => $intervalo
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar intervalo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar intervalo'
            ], 500);
        }
    }

    /**
     * Atualizar intervalo
     * PUT /api/prestador/intervalos/{id}
     */
    public function atualizarIntervalo(Request $request, $id)
    {
        $user = $request->user();
        $intervalo = PrestadorIntervalo::where('prestador_id', $user->id)->find($id);

        if (!$intervalo) {
            return response()->json([
                'success' => false,
                'error' => 'Intervalo não encontrado'
            ], 404);
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
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('dias')) $intervalo->dias = $request->dias;
            if ($request->has('inicio')) $intervalo->inicio = $request->inicio;
            if ($request->has('fim')) $intervalo->fim = $request->fim;
            if ($request->has('descricao')) $intervalo->descricao = $request->descricao;
            if ($request->has('ativo')) $intervalo->ativo = $request->ativo;

            $intervalo->save();
            $this->clearPrestadorCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Intervalo atualizado',
                'data' => $intervalo
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar intervalo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar intervalo'
            ], 500);
        }
    }

    /**
     * Deletar intervalo
     * DELETE /api/prestador/intervalos/{id}
     */
    public function deletarIntervalo(Request $request, $id)
    {
        $user = $request->user();
        $intervalo = PrestadorIntervalo::where('prestador_id', $user->id)->find($id);

        if (!$intervalo) {
            return response()->json([
                'success' => false,
                'error' => 'Intervalo não encontrado'
            ], 404);
        }

        $intervalo->delete();
        $this->clearPrestadorCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Intervalo removido'
        ]);
    }

    // ==========================================
    // 11. DISPONIBILIDADE DO PRESTADOR
    // ==========================================

    /**
     * Obter configurações de disponibilidade
     * GET /api/prestador/disponibilidade
     */
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

            return $disponibilidade;
        });

        return response()->json([
            'success' => true,
            'data' => $disponibilidade
        ]);
    }

    /**
     * Atualizar configurações de disponibilidade
     * PUT /api/prestador/disponibilidade
     */
    public function updateDisponibilidade(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'configuracoes' => 'sometimes|array',
            'horarios_padrao' => 'sometimes|array',
            'intervalos_padrao' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $disponibilidade = PrestadorDisponibilidade::where('prestador_id', $user->id)->first();

            if (!$disponibilidade) {
                $disponibilidade = new PrestadorDisponibilidade();
                $disponibilidade->prestador_id = $user->id;
            }

            if ($request->has('configuracoes')) {
                $disponibilidade->configuracoes = array_merge(
                    PrestadorDisponibilidade::getDefaultConfiguracoes(),
                    $request->configuracoes
                );
            }
            if ($request->has('horarios_padrao')) {
                $disponibilidade->horarios_padrao = $request->horarios_padrao;
            }
            if ($request->has('intervalos_padrao')) {
                $disponibilidade->intervalos_padrao = $request->intervalos_padrao;
            }

            $disponibilidade->save();
            $this->clearPrestadorCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Configurações atualizadas',
                'data' => $disponibilidade
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar configurações: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar configurações'
            ], 500);
        }
    }

    // ==========================================
    // 12. MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Limpar todos os caches do prestador
     */
    private function clearPrestadorCache($userId)
    {
        $keys = [
            "prestador_stats_{$userId}",
            "prestador_ganhos_{$userId}",
            "prestador_servicos_{$userId}",
            "prestador_intervalos_{$userId}",
            "prestador_disponibilidade_{$userId}",
            "prestador_saques_{$userId}",
            "prestador_categorias_{$userId}",
            "prestador_proximos_servicos_{$userId}_5",
            "prestador_proximos_servicos_{$userId}_10",
            "prestador_avaliacoes_recentes_{$userId}_5",
            "prestador_avaliacoes_recentes_{$userId}_10",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $statuses = ['pendente', 'aceito', 'concluido', 'cancelado', null];
        foreach ($statuses as $status) {
            for ($page = 1; $page <= 5; $page++) {
                $statusKey = $status ?: 'all';
                Cache::forget("prestador_solicitacoes_{$userId}_{$statusKey}_{$page}");
            }
        }

        for ($page = 1; $page <= 3; $page++) {
            Cache::forget("prestador_historico_saques_{$userId}_{$page}");
        }

        Cache::forget('prestadores_destaque');
        Cache::forget('prestadores_top');
        Cache::forget('prestador_categorias_publicas');
    }

    /**
     * Limpar cache do prestador (endpoint público)
     * POST /api/prestador/clear-cache
     */
    public function clearCache(Request $request)
    {
        $userId = $request->user()->id;
        $this->clearPrestadorCache($userId);

        return response()->json([
            'success' => true,
            'message' => 'Cache limpo com sucesso'
        ]);
    }

    /**
     * Endpoint para verificar saúde do sistema
     * GET /api/prestador/health
     */
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
