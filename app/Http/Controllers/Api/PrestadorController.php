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
     * Registro de novo prestador - CORRIGIDO (salva categorias corretamente)
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

        DB::beginTransaction();
        try {
            // ==========================================
            // PROCESSAR CATEGORIAS
            // ==========================================
            $categoriasIds = [];
            if ($request->categorias) {
                $categoriasData = is_string($request->categorias)
                    ? json_decode($request->categorias, true)
                    : $request->categorias;

                if (is_array($categoriasData)) {
                    if (isset($categoriasData[0]['value'])) {
                        $categoriasIds = array_column($categoriasData, 'value');
                    } else {
                        $categoriasIds = $categoriasData;
                    }
                }
                Log::info('📌 Categorias recebidas: ', $categoriasIds);
            }

            // ==========================================
            // GERAR PROFISSÃO AUTOMATICAMENTE A PARTIR DAS CATEGORIAS
            // ==========================================
            $profissaoGerada = 'Prestador de Serviços';

            if (!empty($categoriasIds)) {
                $categoriasNomes = DB::table('categorias')
                    ->whereIn('id', $categoriasIds)
                    ->pluck('nome')
                    ->toArray();

                if (!empty($categoriasNomes)) {
                    $count = count($categoriasNomes);

                    if ($count === 1) {
                        // Uma categoria: "Eletricista Profissional"
                        $profissaoGerada = $categoriasNomes[0] . ' Profissional';
                    } elseif ($count === 2) {
                        // Duas categorias: "Eletricista e Canalizador"
                        $profissaoGerada = $categoriasNomes[0] . ' e ' . $categoriasNomes[1];
                    } else {
                        // Três ou mais: "Eletricista e outros serviços"
                        $profissaoGerada = $categoriasNomes[0] . ' e outros serviços';
                    }
                }
            }

            // Se o usuário enviou uma profissão manualmente, usa ela (prioridade)
            if ($request->has('profissao') && !empty($request->profissao)) {
                $profissaoGerada = $request->profissao;
            }

            // ==========================================
            // PROCESSAR PORTFOLIO
            // ==========================================
            $portfolioPaths = [];
            for ($i = 0; $i < 3; $i++) {
                if ($request->hasFile("portfolio.{$i}")) {
                    $file = $request->file("portfolio.{$i}");
                    if ($file && $file->isValid()) {
                        $portfolioPaths[] = $file->store('fotos/portfolio', 'public');
                    }
                }
            }

            // ==========================================
            // PROCESSAR DISPONIBILIDADE
            // ==========================================
            $disponibilidadeData = [];
            if ($request->disponibilidade) {
                $disponibilidadeData = is_string($request->disponibilidade)
                    ? json_decode($request->disponibilidade, true)
                    : $request->disponibilidade;
            }

            // ==========================================
            // CRIAR USUÁRIO
            // ==========================================
            $userData = [
                'nome' => $request->nome,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'password' => Hash::make($request->password),
                'endereco' => $request->endereco,
                'tipo' => 'prestador',
                'profissao' => $profissaoGerada,
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

            if ($request->hasFile('foto')) {
                $userData['foto'] = $request->file('foto')->store('fotos/prestadores', 'public');
            }

            if ($request->hasFile('documento')) {
                $userData['documento'] = $request->file('documento')->store('documentos/prestadores', 'public');
            }

            $user = User::create($userData);

            // ==========================================
            // SALVAR CATEGORIAS
            // ==========================================
            if (!empty($categoriasIds)) {
                $insertData = [];
                foreach ($categoriasIds as $categoriaId) {
                    $categoria = Categoria::find($categoriaId);
                    if ($categoria) {
                        $insertData[] = [
                            'user_id' => $user->id,
                            'categoria_id' => $categoriaId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($insertData)) {
                    DB::table('prestador_categorias')->insert($insertData);
                }
                Log::info("✅ Categorias salvas para prestador {$user->id}: " . count($insertData) . " de " . count($categoriasIds));
            } else {
                Log::warning("⚠️ Nenhuma categoria para salvar para prestador {$user->id}");
            }

            // ==========================================
            // SALVAR DISPONIBILIDADE
            // ==========================================
            if (!empty($disponibilidadeData)) {
                PrestadorDisponibilidade::updateOrCreate(
                    ['prestador_id' => $user->id],
                    [
                        'configuracoes' => PrestadorDisponibilidade::getDefaultConfiguracoes(),
                        'horarios_padrao' => $disponibilidadeData,
                        'intervalos_padrao' => [],
                        'ativo' => true,
                    ]
                );
            }

            // ==========================================
            // SALVAR RAIO DE ATUAÇÃO
            // ==========================================
            if ($request->raio) {
                $user->update(['raio_atuacao' => $request->raio]);
            }

            DB::commit();

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
                    'categorias' => $categoriasIds,
                ],
                'token' => $token
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
    /**
     * Listar prestadores (público)
     * GET /api/prestadores
     */
    public function index(Request $request)
    {
        try {
            $cacheKey = "prestadores_list_" . md5($request->fullUrl());

            $prestadores = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($request) {
                $query = DB::table('users')
                    ->where('tipo', 'prestador')
                    ->where('ativo', true)
                    ->select([
                        'users.id',
                        'users.nome',
                        'users.email',
                        'users.telefone',
                        'users.foto',
                        'users.profissao',
                        'users.sobre',
                        'users.media_avaliacao',
                        'users.total_avaliacoes',
                        'users.verificado',
                        'users.ativo',
                        'users.preferences'
                    ]);

                // ✅ FILTRO POR CATEGORIA - USANDO DB DIRETO (SEM whereHas)
                if ($request->has('categoria')) {
                    $categoriaId = (int) $request->categoria;
                    $query->whereExists(function ($q) use ($categoriaId) {
                        $q->select(DB::raw(1))
                            ->from('prestador_categorias')
                            ->whereColumn('prestador_categorias.user_id', 'users.id')
                            ->where('prestador_categorias.categoria_id', $categoriaId);
                    });
                }

                // ✅ FILTRO POR BUSCA
                if ($request->has('busca') && !empty($request->busca)) {
                    $busca = '%' . addcslashes($request->busca, '%_') . '%';
                    $query->where('users.nome', 'like', $busca);
                }

                $prestadores = $query->limit(50)->get();
                $prestadorIds = $prestadores->pluck('id')->toArray();

                // Buscar categorias em lote
                $categoriasMap = [];
                if (!empty($prestadorIds)) {
                    $categorias = DB::table('prestador_categorias')
                        ->whereIn('user_id', $prestadorIds)
                        ->join('categorias', 'prestador_categorias.categoria_id', '=', 'categorias.id')
                        ->select('prestador_categorias.user_id', 'categorias.id', 'categorias.nome', 'categorias.icone', 'categorias.cor')
                        ->get();

                    foreach ($categorias as $cat) {
                        $categoriasMap[$cat->user_id][] = [
                            'id' => (int) $cat->id,
                            'nome' => (string) $cat->nome,
                            'icone' => (string) ($cat->icone ?? 'category'),
                            'cor' => (string) ($cat->cor ?? 'primary'),
                        ];
                    }
                }

                $resultado = [];
                foreach ($prestadores as $prestador) {
                    $preferences = json_decode($prestador->preferences, true);
                    $portfolio = isset($preferences['portfolio']) && is_array($preferences['portfolio'])
                        ? array_map(fn($path) => asset('storage/' . $path), $preferences['portfolio'])
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
            return response()->json([
                'success' => false,
                'error' => 'Erro ao carregar prestadores: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Detalhes do prestador (público)
     * GET /api/prestadores/{id}
     */
    public function show($id)
    {
        $cacheKey = "prestador_detalhes_{$id}";

        $dados = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($id) {
            $prestador = DB::table('users')
                ->where('tipo', 'prestador')
                ->where('id', $id)
                ->select([
                    'id',
                    'nome',
                    'email',
                    'telefone',
                    'foto',
                    'profissao',
                    'sobre',
                    'media_avaliacao',
                    'total_avaliacoes',
                    'verificado',
                    'ativo',
                    'created_at',
                    'preferences'
                ])
                ->first();

            if (!$prestador) return null;

            $preferences = json_decode($prestador->preferences, true);

            // Portfolio
            $portfolio = isset($preferences['portfolio']) && is_array($preferences['portfolio'])
                ? array_map(fn($path) => asset('storage/' . $path), $preferences['portfolio'])
                : [];

            // ==========================================
            // BUSCAR SERVIÇOS DO PRESTADOR
            // ==========================================
            $servicos = DB::table('servicos')
                ->where('prestador_id', $id)
                ->where('ativo', true)
                ->select(['id', 'nome', 'preco', 'duracao', 'descricao', 'icone'])
                ->get()
                ->map(fn($s) => [
                    'id' => (int) $s->id,
                    'nome' => (string) $s->nome,
                    'preco' => (float) $s->preco,
                    'duracao' => (int) $s->duracao,
                    'descricao' => $s->descricao,
                    'icone' => (string) ($s->icone ?? 'handyman'),
                ])->toArray();

            // ==========================================
            // BUSCAR CATEGORIAS
            // ==========================================
            $categorias = DB::table('prestador_categorias')
                ->join('categorias', 'prestador_categorias.categoria_id', '=', 'categorias.id')
                ->where('prestador_categorias.user_id', $id)
                ->select(['categorias.id', 'categorias.nome', 'categorias.icone', 'categorias.cor'])
                ->get()
                ->map(fn($cat) => [
                    'id' => (int) $cat->id,
                    'nome' => (string) $cat->nome,
                    'icone' => (string) ($cat->icone ?? 'category'),
                    'cor' => (string) ($cat->cor ?? 'primary'),
                ])->toArray();

            // ==========================================
            // BUSCAR AVALIAÇÕES (últimas 10)
            // ==========================================
            $avaliacoes = DB::table('avaliacoes')
                ->where('prestador_id', $id)
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
                ->limit(10)
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
                ])->toArray();

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
                'categorias' => $categorias,
                'servicos' => $servicos,
                'avaliacoes' => $avaliacoes,
                'portfolio' => $portfolio,
                'created_at' => $prestador->created_at,
            ];
        });

        if (!$dados) {
            return response()->json(['success' => false, 'message' => 'Prestador não encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $dados]);
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
                ->limit(8)  // ← **LIMITE DE 8**
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
            Log::error('Erro em destaque: ' . $e->getMessage());
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
    /**
     * Prestadores próximos (público)
     * GET /api/prestadores/proximos
     */
    /**
     * Prestadores próximos (público)
     * GET /api/prestadores/proximos
     */
    public function proximos(Request $request)
    {
        $latitude = $request->query('latitude');
        $longitude = $request->query('longitude');
        $radius = $request->query('radius', 10);
        $categoria = $request->query('categoria');
        $busca = $request->query('busca');

        if (!$latitude || !$longitude) {
            return response()->json([
                'success' => false,
                'error' => 'Latitude e longitude são obrigatórias'
            ], 422);
        }

        $cacheKey = "prestadores_proximos_" . md5("{$latitude}_{$longitude}_{$radius}_{$categoria}_{$busca}");

        $prestadores = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($latitude, $longitude, $radius, $categoria, $busca) {

            $query = DB::table('users')
                ->where('users.tipo', 'prestador')
                ->where('users.ativo', true)
                ->whereNotNull('users.latitude')
                ->whereNotNull('users.longitude')
                ->select([
                    'users.id',
                    'users.nome',
                    'users.email',
                    'users.telefone',
                    'users.foto',
                    'users.profissao',
                    'users.sobre',
                    'users.media_avaliacao',
                    'users.total_avaliacoes',
                    'users.verificado',
                    'users.ativo as disponivel',
                    'users.latitude',
                    'users.longitude',
                    'users.preferences'
                ]);

            if ($categoria) {
                $query->whereExists(function ($q) use ($categoria) {
                    $q->select(DB::raw(1))
                        ->from('prestador_categorias')
                        ->whereColumn('prestador_categorias.user_id', 'users.id')
                        ->where('prestador_categorias.categoria_id', $categoria);
                });
            }

            if ($busca) {
                $buscaTermo = '%' . addcslashes($busca, '%_') . '%';
                $query->where('users.nome', 'like', $buscaTermo);
            }

            $prestadores = $query->get();
            $prestadorIds = $prestadores->pluck('id')->toArray();

            $categoriasMap = [];
            $servicosMap = [];
            $avaliacoesMap = [];
            $portfolioMap = [];

            if (!empty($prestadorIds)) {
                // ==========================================
                // 1. BUSCAR CATEGORIAS
                // ==========================================
                $categorias = DB::table('prestador_categorias')
                    ->whereIn('user_id', $prestadorIds)
                    ->join('categorias', 'prestador_categorias.categoria_id', '=', 'categorias.id')
                    ->select('prestador_categorias.user_id', 'categorias.id', 'categorias.nome', 'categorias.icone', 'categorias.cor')
                    ->get();

                foreach ($categorias as $cat) {
                    $categoriasMap[$cat->user_id][] = [
                        'id' => (int) $cat->id,
                        'nome' => (string) $cat->nome,
                        'icone' => (string) ($cat->icone ?? 'category'),
                        'cor' => (string) ($cat->cor ?? 'primary'),
                    ];
                }

                // ==========================================
                // 2. BUSCAR SERVIÇOS
                // ==========================================
                $servicos = DB::table('servicos')
                    ->whereIn('prestador_id', $prestadorIds)
                    ->where('ativo', true)
                    ->select(['id', 'prestador_id', 'nome', 'preco', 'duracao', 'descricao', 'icone'])
                    ->get();

                foreach ($servicos as $serv) {
                    $servicosMap[$serv->prestador_id][] = [
                        'id' => (int) $serv->id,
                        'nome' => (string) $serv->nome,
                        'preco' => (float) $serv->preco,
                        'duracao' => (int) $serv->duracao,
                        'descricao' => $serv->descricao,
                        'icone' => (string) ($serv->icone ?? 'handyman'),
                    ];
                }

                // ==========================================
                // 3. BUSCAR AVALIAÇÕES (últimas 3)
                // ==========================================
                $avaliacoes = DB::table('avaliacoes')
                    ->whereIn('prestador_id', $prestadorIds)
                    ->leftJoin('users as clientes', 'avaliacoes.cliente_id', '=', 'clientes.id')
                    ->select([
                        'avaliacoes.id',
                        'avaliacoes.prestador_id',
                        'avaliacoes.nota',
                        'avaliacoes.comentario',
                        'avaliacoes.created_at',
                        'clientes.id as cliente_id',
                        'clientes.nome as cliente_nome',
                        'clientes.foto as cliente_foto'
                    ])
                    ->orderBy('avaliacoes.created_at', 'desc')
                    ->get();

                foreach ($avaliacoes as $aval) {
                    if (!isset($avaliacoesMap[$aval->prestador_id])) {
                        $avaliacoesMap[$aval->prestador_id] = [];
                    }
                    if (count($avaliacoesMap[$aval->prestador_id]) < 3) {
                        $avaliacoesMap[$aval->prestador_id][] = [
                            'id' => (int) $aval->id,
                            'nota' => (int) $aval->nota,
                            'comentario' => $aval->comentario,
                            'created_at' => $aval->created_at,
                            'cliente' => $aval->cliente_id ? [
                                'id' => (int) $aval->cliente_id,
                                'nome' => (string) $aval->cliente_nome,
                                'foto' => $aval->cliente_foto ? asset('storage/' . $aval->cliente_foto) : null,
                            ] : null,
                        ];
                    }
                }

                // ==========================================
                // 4. BUSCAR PORTFOLIO
                // ==========================================
                foreach ($prestadores as $prestador) {
                    $preferences = json_decode($prestador->preferences, true);
                    if (isset($preferences['portfolio']) && is_array($preferences['portfolio'])) {
                        $portfolioMap[$prestador->id] = array_map(function ($path) {
                            return asset('storage/' . $path);
                        }, $preferences['portfolio']);
                    } else {
                        $portfolioMap[$prestador->id] = [];
                    }
                }
            }

            // ==========================================
            // 5. MONTAR RESPOSTA COMPLETA
            // ==========================================
            $resultado = [];
            foreach ($prestadores as $prestador) {
                $distancia = $this->calcularDistancia(
                    (float) $latitude,
                    (float) $longitude,
                    (float) $prestador->latitude,
                    (float) $prestador->longitude
                );

                if ($distancia <= $radius) {
                    $resultado[] = [
                        'id' => (int) $prestador->id,
                        'nome' => (string) $prestador->nome,
                        'email' => (string) $prestador->email,
                        'telefone' => (string) $prestador->telefone,
                        'foto' => $prestador->foto ? asset('storage/' . $prestador->foto) : null,
                        'profissao' => (string) ($prestador->profissao ?? 'Prestador de Serviços'),
                        'sobre' => $prestador->sobre,
                        'media_avaliacao' => (float) ($prestador->media_avaliacao ?? 0),
                        'total_avaliacoes' => (int) ($prestador->total_avaliacoes ?? 0),
                        'verificado' => (bool) ($prestador->verificado ?? false),
                        'disponivel' => (bool) ($prestador->disponivel ?? true),
                        'distancia' => round($distancia, 2),
                        'latitude' => (float) $prestador->latitude,
                        'longitude' => (float) $prestador->longitude,
                        'categorias' => $categoriasMap[$prestador->id] ?? [],
                        'servicos' => $servicosMap[$prestador->id] ?? [],
                        'avaliacoes' => $avaliacoesMap[$prestador->id] ?? [],
                        'portfolio' => $portfolioMap[$prestador->id] ?? [],
                    ];
                }
            }

            return array_values($resultado);
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores,
            'meta' => [
                'count' => count($prestadores),
                'radius' => (float) $radius,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ]
        ]);
    }

    /**
     * Calcular distância entre dois pontos (Haversine formula)
     */
    private function calcularDistancia($lat1, $lon1, $lat2, $lon2)
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return 9999;
        }

        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
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
