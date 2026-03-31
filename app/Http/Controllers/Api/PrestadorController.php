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

class PrestadorController extends Controller
{
    // ==========================================
    // 1. REGISTRO DO PRESTADOR
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
                ],
                'token' => $token
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao registar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 2. SERVIÇOS DO PRESTADOR (COM CACHE)
    // ==========================================

    /**
     * Listar serviços do prestador
     * GET /api/prestador/servicos
     */
    public function servicos(Request $request)
    {
        $user = $request->user();
        $cacheKey = "prestador_servicos_{$user->id}";

        $servicos = Cache::remember($cacheKey, 300, function () use ($user) {
            return Servico::where('prestador_id', $user->id)
                ->with('categoria')
                ->orderBy('created_at', 'desc')
                ->get();
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

            Cache::forget("prestador_servicos_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Serviço criado com sucesso',
                'data' => $servico
            ], 201);
        } catch (\Exception $e) {
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

            $servico->save();

            Cache::forget("prestador_servicos_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Serviço atualizado com sucesso',
                'data' => $servico
            ]);
        } catch (\Exception $e) {
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

        Cache::forget("prestador_servicos_{$user->id}");

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

        Cache::forget("prestador_servicos_{$user->id}");

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
        $user = $request->user();
        $semana = $request->query('semana');

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
        $user = $request->user();

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
    // 4. SOLICITAÇÕES/PEDIDOS (COM CACHE)
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

        $pedidos = Cache::remember($cacheKey, 120, function () use ($user, $status) {
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

        $categorias = Cache::remember($cacheKey, 3600, function () use ($user) {
            return $user->categorias()->get();
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

        Cache::forget("prestador_categorias_{$user->id}");

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

        Cache::forget("prestador_categorias_{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Categoria removida'
        ]);
    }

    // ==========================================
    // 6. ESTATÍSTICAS DO PRESTADOR (OTIMIZADO)
    // ==========================================

    /**
     * Estatísticas do prestador
     * GET /api/prestador/stats
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $cacheKey = "prestador_stats_{$userId}";

        $stats = Cache::remember($cacheKey, 300, function () use ($userId) {
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

        $prestadores = Cache::remember($cacheKey, 600, function () use ($request) {
            $query = User::where('tipo', 'prestador')
                ->where('ativo', true)
                ->with('categorias');

            if ($request->has('categoria')) {
                $query->whereHas('categorias', function ($q) use ($request) {
                    $q->where('categoria_id', $request->categoria);
                });
            }

            if ($request->has('busca')) {
                $query->where('nome', 'like', '%' . $request->busca . '%');
            }

            return $query->paginate(20);
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

        $dados = Cache::remember($cacheKey, 600, function () use ($id) {
            try {
                $prestador = User::where('tipo', 'prestador')->find($id);

                if (!$prestador) {
                    return null;
                }

                $servicos = Servico::where('prestador_id', $id)->get();
                $categorias = $prestador->categorias()->get();
                $avaliacoes = Avaliacao::where('prestador_id', $id)
                    ->with('cliente')
                    ->orderBy('created_at', 'desc')
                    ->get();

                return [
                    'id' => $prestador->id,
                    'nome' => $prestador->nome,
                    'email' => $prestador->email,
                    'telefone' => $prestador->telefone,
                    'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                    'profissao' => $prestador->profissao,
                    'sobre' => $prestador->sobre,
                    'media_avaliacao' => $prestador->media_avaliacao ?? 0,
                    'total_avaliacoes' => $prestador->total_avaliacoes ?? 0,
                    'verificado' => $prestador->verificado ?? false,
                    'disponivel' => $prestador->ativo ?? true,
                    'categorias' => $categorias,
                    'servicos' => $servicos,
                    'avaliacoes' => $avaliacoes,
                    'created_at' => $prestador->created_at,
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
        $prestadores = Cache::remember('prestadores_destaque', 3600, function () {
            return User::where('tipo', 'prestador')
                ->where('ativo', true)
                ->orderBy('media_avaliacao', 'desc')
                ->limit(10)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Prestadores mais bem avaliados (público)
     * GET /api/prestadores/top
     */
    public function topAvaliados()
    {
        $prestadores = Cache::remember('prestadores_top', 3600, function () {
            return User::where('tipo', 'prestador')
                ->where('ativo', true)
                ->orderBy('media_avaliacao', 'desc')
                ->limit(10)
                ->get();
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
        $lat = $request->query('lat');
        $lng = $request->query('lng');

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
        $categorias = Cache::remember('categorias_publicas', 3600, function () {
            return Categoria::where('ativo', true)->get();
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

        $avaliacoes = Cache::remember($cacheKey, 600, function () use ($id) {
            return Avaliacao::where('prestador_id', $id)
                ->with('cliente')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        });

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }

    // ==========================================
    // 8. FINANCEIRO DO PRESTADOR (OTIMIZADO)
    // ==========================================

    /**
     * Ganhos do prestador
     * GET /api/prestador/ganhos
     */
    public function ganhos(Request $request)
    {
        $userId = $request->user()->id;
        $cacheKey = "prestador_ganhos_{$userId}";

        $ganhos = Cache::remember($cacheKey, 300, function () use ($userId) {
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

        $saques = Cache::remember($cacheKey, 300, function () use ($user) {
            return Transacao::where('user_id', $user->id)
                ->where('tipo', 'saque')
                ->orderBy('created_at', 'desc')
                ->get();
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

            Cache::forget("prestador_saques_{$user->id}");
            Cache::forget("prestador_ganhos_{$user->id}");

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

        $saques = Cache::remember($cacheKey, 300, function () use ($user) {
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
    // 9. PRÓXIMOS SERVIÇOS E AVALIAÇÕES RECENTES (OTIMIZADO)
    // ==========================================

    /**
     * Próximos serviços do prestador
     * GET /api/prestador/proximos-servicos
     */
    public function proximosServicos(Request $request)
    {
        $userId = $request->user()->id;
        $limit = $request->query('limit', 5);
        $cacheKey = "prestador_proximos_servicos_{$userId}_{$limit}";

        $servicos = Cache::remember($cacheKey, 180, function () use ($userId, $limit) {
            return Pedido::where('prestador_id', $userId)
                ->whereIn('status', ['aceito', 'confirmado', 'em_andamento'])
                ->whereDate('data', '>=', now())
                ->with(['cliente:id,nome,foto,telefone', 'servico:id,nome,preco'])
                ->orderBy('data', 'asc')
                ->limit($limit)
                ->get()
                ->map(function ($pedido) {
                    return [
                        'id' => $pedido->id,
                        'numero' => $pedido->numero,
                        'cliente' => [
                            'id' => $pedido->cliente->id,
                            'nome' => $pedido->cliente->nome,
                            'foto' => $pedido->cliente->foto ? asset('storage/' . $pedido->cliente->foto) : null,
                            'telefone' => $pedido->cliente->telefone,
                        ],
                        'servico' => [
                            'id' => $pedido->servico->id,
                            'nome' => $pedido->servico->nome,
                            'preco' => $pedido->servico->preco,
                        ],
                        'data' => $pedido->data,
                        'endereco' => $pedido->endereco,
                        'status' => $pedido->status,
                        'valor' => $pedido->valor,
                        'observacoes' => $pedido->observacoes,
                    ];
                });
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
        $limit = $request->query('limit', 5);
        $cacheKey = "prestador_avaliacoes_recentes_{$userId}_{$limit}";

        $avaliacoes = Cache::remember($cacheKey, 300, function () use ($userId, $limit) {
            return Avaliacao::where('prestador_id', $userId)
                ->with('cliente:id,nome,foto')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($avaliacao) {
                    return [
                        'id' => $avaliacao->id,
                        'nota' => $avaliacao->nota,
                        'comentario' => $avaliacao->comentario,
                        'created_at' => $avaliacao->created_at,
                        'cliente' => [
                            'id' => $avaliacao->cliente->id,
                            'nome' => $avaliacao->cliente->nome,
                            'foto' => $avaliacao->cliente->foto ? asset('storage/' . $avaliacao->cliente->foto) : null,
                        ],
                    ];
                });
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

        $intervalos = Cache::remember($cacheKey, 300, function () use ($user) {
            return PrestadorIntervalo::where('prestador_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
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

            Cache::forget("prestador_intervalos_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Intervalo criado com sucesso',
                'data' => $intervalo
            ], 201);
        } catch (\Exception $e) {
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

            Cache::forget("prestador_intervalos_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Intervalo atualizado',
                'data' => $intervalo
            ]);
        } catch (\Exception $e) {
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

        Cache::forget("prestador_intervalos_{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Intervalo removido'
        ]);
    }

    // ==========================================
    // 11. DISPONIBILIDADE DO PRESTADOR
    // ==========================================

    /**
     * Obter configurações de disponibilidade do prestador
     * GET /api/prestador/disponibilidade
     */
    public function getDisponibilidade(Request $request)
    {
        $user = $request->user();
        $cacheKey = "prestador_disponibilidade_{$user->id}";

        $disponibilidade = Cache::remember($cacheKey, 3600, function () use ($user) {
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
            'configuracoes.tempo_minimo_agendamento' => 'sometimes|integer|min:15|max:1440',
            'configuracoes.tempo_entre_servicos' => 'sometimes|integer|min:0|max:120',
            'configuracoes.notificar_antes' => 'sometimes|integer|min:5|max:1440',
            'configuracoes.aceitar_agendamento_automatico' => 'sometimes|boolean',
            'configuracoes.dias_antecedencia' => 'sometimes|integer|min:1|max:365',
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
                $configuracoes = array_merge(
                    PrestadorDisponibilidade::getDefaultConfiguracoes(),
                    $request->configuracoes
                );
                $disponibilidade->configuracoes = $configuracoes;
            }

            if ($request->has('horarios_padrao')) {
                $disponibilidade->horarios_padrao = $request->horarios_padrao;
            }

            if ($request->has('intervalos_padrao')) {
                $disponibilidade->intervalos_padrao = $request->intervalos_padrao;
            }

            $disponibilidade->save();

            Cache::forget("prestador_disponibilidade_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Configurações atualizadas',
                'data' => $disponibilidade
            ]);
        } catch (\Exception $e) {
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
            "prestador_avaliacoes_recentes_{$userId}_5",
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
}
