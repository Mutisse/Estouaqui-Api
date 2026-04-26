<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\Favorito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Notifications\DynamicNotification;

class ClienteController extends Controller
{
    // ==========================================
    // CONSTANTES DE CACHE OTIMIZADAS
    // ==========================================
    private const CACHE_SHORT = 120;
    private const CACHE_MEDIUM = 300;
    private const CACHE_LONG = 3600;

    // ==========================================
    // 1. REGISTRO DO CLIENTE
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
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        DB::beginTransaction();
        try {
            $userData = [
                'nome' => $request->nome,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'password' => Hash::make($request->password),
                'endereco' => $request->endereco,
                'tipo' => 'cliente',
            ];

            if ($request->hasFile('foto')) {
                $userData['foto'] = $request->file('foto')->store('fotos/clientes', 'public');
            }

            $user = User::create($userData);
            DB::commit();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Cliente registado com sucesso!',
                'data' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                ],
                'token' => $token
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => 'Erro ao registar cliente: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. PEDIDOS DO CLIENTE - OTIMIZADO
    // ==========================================

    /**
     * Listar pedidos do cliente - OTIMIZADO COM PAGINAÇÃO
     * GET /api/cliente/pedidos
     */
    public function pedidos(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(50, max(5, $perPage));

        $cacheKey = "cliente_pedidos_{$user->id}_" . ($status ?? 'all') . "_{$perPage}";

        $pedidos = Cache::remember($cacheKey, self::CACHE_SHORT, function () use ($user, $status, $perPage) {
            // ✅ USAR DB::table para performance
            $query = DB::table('pedidos')
                ->where('cliente_id', $user->id)
                ->leftJoin('users as prestadores', 'pedidos.prestador_id', '=', 'prestadores.id')
                ->select([
                    'pedidos.id',
                    'pedidos.status',
                    'pedidos.descricao',
                    'pedidos.data',
                    'pedidos.endereco',
                    'pedidos.created_at',
                    'prestadores.id as prestador_id',
                    'prestadores.nome as prestador_nome',
                    'prestadores.foto as prestador_foto',
                    'prestadores.telefone as prestador_telefone',
                    'prestadores.media_avaliacao as prestador_media'
                ]);

            if ($status) {
                $query->where('pedidos.status', $status);
            }

            return $query->orderBy('pedidos.created_at', 'desc')
                ->paginate($perPage);
        });

        return response()->json(['success' => true, 'data' => $pedidos]);
    }

    /**
     * Listar pedidos do cliente (formato mobile) - SUPER OTIMIZADO
     * GET /api/cliente/meus-pedidos
     */
    public function meusPedidos(Request $request)
    {
        try {
            $user = $request->user();

            // ✅ ADICIONAR LIMITE MÁXIMO DE 20 REGISTROS
            $pedidos = Pedido::where('cliente_id', $user->id)
                ->with(['prestador:id,nome,foto,telefone,media_avaliacao'])
                ->orderBy('created_at', 'desc')
                ->limit(20)  // ← **ADICIONAR ESTA LINHA**
                ->get();

            $pedidosFormatados = $pedidos->map(function ($pedido) {
                return [
                    'id' => $pedido->id,
                    'numero' => $pedido->numero ?? 'PED-' . str_pad($pedido->id, 6, '0', STR_PAD_LEFT),
                    'status' => $pedido->status,
                    'descricao' => $pedido->descricao,
                    'foto' => $pedido->foto ? asset('storage/' . $pedido->foto) : null,
                    'data' => $pedido->data,
                    'endereco' => $pedido->endereco,
                    'observacoes' => $pedido->observacoes,
                    'valor' => $pedido->valor,
                    'created_at' => $pedido->created_at,
                    'prestador' => $pedido->prestador ? [
                        'id' => $pedido->prestador->id,
                        'nome' => $pedido->prestador->nome,
                        'foto' => $pedido->prestador->foto ? asset('storage/' . $pedido->prestador->foto) : null,
                        'telefone' => $pedido->prestador->telefone,
                        'media_avaliacao' => (float) ($pedido->prestador->media_avaliacao ?? 0),
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $pedidosFormatados
            ]);
        } catch (\Exception $e) {
            Log::error('Erro em meusPedidos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar pedidos'
            ], 500);
        }
    }

    /**
     * Detalhes de um pedido específico - OTIMIZADO
     * GET /api/cliente/pedidos/{id}
     */
    public function showPedido(Request $request, $id)
    {
        $user = $request->user();
        $cacheKey = "cliente_pedido_{$user->id}_{$id}";

        $pedido = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($user, $id) {
            // ✅ OTIMIZADO: query única com joins
            $pedido = DB::table('pedidos')
                ->where('pedidos.id', $id)
                ->where('pedidos.cliente_id', $user->id)
                ->leftJoin('users as prestadores', 'pedidos.prestador_id', '=', 'prestadores.id')
                ->leftJoin('servicos', 'pedidos.servico_id', '=', 'servicos.id')
                ->leftJoin('avaliacoes', function ($join) use ($user) {
                    $join->on('avaliacoes.pedido_id', '=', 'pedidos.id')
                        ->where('avaliacoes.cliente_id', '=', $user->id);
                })
                ->select([
                    'pedidos.*',
                    'prestadores.id as prestador_id',
                    'prestadores.nome as prestador_nome',
                    'prestadores.foto as prestador_foto',
                    'prestadores.telefone as prestador_telefone',
                    'prestadores.media_avaliacao as prestador_media',
                    'servicos.id as servico_id',
                    'servicos.nome as servico_nome',
                    'servicos.preco as servico_preco',
                    'servicos.duracao as servico_duracao',
                    'avaliacoes.id as avaliacao_id',
                    'avaliacoes.nota as avaliacao_nota',
                    'avaliacoes.comentario as avaliacao_comentario'
                ])
                ->first();

            if (!$pedido) return null;

            return [
                'id' => (int) $pedido->id,
                'numero' => (string) $pedido->numero,
                'status' => (string) $pedido->status,
                'descricao' => $pedido->descricao,
                'foto' => $pedido->foto ? asset('storage/' . $pedido->foto) : null,
                'data' => $pedido->data,
                'endereco' => (string) $pedido->endereco,
                'observacoes' => $pedido->observacoes,
                'valor' => (float) ($pedido->valor ?? 0),
                'created_at' => $pedido->created_at,
                'prestador' => $pedido->prestador_id ? [
                    'id' => (int) $pedido->prestador_id,
                    'nome' => (string) $pedido->prestador_nome,
                    'foto' => $pedido->prestador_foto ? asset('storage/' . $pedido->prestador_foto) : null,
                    'telefone' => (string) $pedido->prestador_telefone,
                    'media_avaliacao' => (float) ($pedido->prestador_media ?? 0),
                ] : null,
                'servico' => $pedido->servico_id ? [
                    'id' => (int) $pedido->servico_id,
                    'nome' => (string) $pedido->servico_nome,
                    'preco' => (float) ($pedido->servico_preco ?? 0),
                    'duracao' => (int) ($pedido->servico_duracao ?? 0),
                ] : null,
                'avaliacao' => $pedido->avaliacao_id ? [
                    'id' => (int) $pedido->avaliacao_id,
                    'nota' => (int) $pedido->avaliacao_nota,
                    'comentario' => $pedido->avaliacao_comentario,
                ] : null,
            ];
        });

        if (!$pedido) {
            return response()->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $pedido]);
    }

    /**
     * Criar novo pedido
     * POST /api/cliente/pedidos
     */
    public function createPedido(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'prestador_id' => 'required|exists:users,id',
            'servico_id' => 'required|exists:servicos,id',
            'data' => 'required|date|after:now',
            'endereco' => 'required|string',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        DB::beginTransaction();
        try {
            $pedido = Pedido::create([
                'cliente_id' => $user->id,
                'prestador_id' => $request->prestador_id,
                'servico_id' => $request->servico_id,
                'data' => $request->data,
                'endereco' => $request->endereco,
                'observacoes' => $request->observacoes,
                'status' => 'pendente',
                'numero' => 'PED-' . strtoupper(uniqid()),
            ]);

            $prestador = User::find($request->prestador_id);
            if ($prestador) {
                try {
                    $prestador->notify(new DynamicNotification('novo_pedido_cliente', [
                        'cliente_nome' => $user->nome,
                        'pedido_numero' => $pedido->numero,
                        'data' => $request->data,
                        'pedido_id' => $pedido->id,
                    ]));
                } catch (\Exception $e) {
                    Log::warning('Erro ao enviar notificação: ' . $e->getMessage());
                }
            }

            DB::commit();
            $this->clearClienteCache($user->id);

            return response()->json(['success' => true, 'message' => 'Pedido criado com sucesso', 'data' => $pedido], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar pedido: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao criar pedido'], 500);
        }
    }

    /**
     * Cancelar pedido
     * PUT /api/cliente/pedidos/{id}/cancelar
     */
    public function cancelarPedido(Request $request, $id)
    {
        $user = $request->user();

        DB::beginTransaction();
        try {
            $pedido = Pedido::where('cliente_id', $user->id)->lockForUpdate()->find($id);

            if (!$pedido) {
                return response()->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
            }

            if ($pedido->status !== 'pendente') {
                return response()->json(['success' => false, 'error' => 'Apenas pedidos pendentes podem ser cancelados'], 422);
            }

            $pedido->status = 'cancelado';
            $pedido->save();

            $prestador = $pedido->prestador;
            if ($prestador) {
                try {
                    $prestador->notify(new DynamicNotification('pedido_cancelado_cliente', [
                        'cliente_nome' => $user->nome,
                        'pedido_numero' => $pedido->numero ?? $pedido->id,
                        'pedido_id' => $pedido->id,
                    ]));
                } catch (\Exception $e) {
                    Log::warning('Erro ao enviar notificação: ' . $e->getMessage());
                }
            }

            DB::commit();
            $this->clearClienteCache($user->id);
            Cache::forget("cliente_pedido_{$user->id}_{$id}");

            return response()->json(['success' => true, 'message' => 'Pedido cancelado com sucesso', 'data' => $pedido]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao cancelar pedido: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao cancelar pedido'], 500);
        }
    }

    // ==========================================
    // 3. AVALIAÇÕES DO CLIENTE - OTIMIZADO
    // ==========================================

    /**
     * Listar avaliações do cliente - OTIMIZADO
     * GET /api/cliente/avaliacoes
     */
    public function avaliacoes(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 10);

        $cacheKey = "cliente_avaliacoes_{$user->id}_{$perPage}";

        $avaliacoes = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($user, $perPage) {
            return DB::table('avaliacoes')
                ->where('cliente_id', $user->id)
                ->leftJoin('users as prestadores', 'avaliacoes.prestador_id', '=', 'prestadores.id')
                ->select([
                    'avaliacoes.id',
                    'avaliacoes.nota',
                    'avaliacoes.comentario',
                    'avaliacoes.categorias',
                    'avaliacoes.created_at',
                    'prestadores.id as prestador_id',
                    'prestadores.nome as prestador_nome',
                    'prestadores.foto as prestador_foto',
                    'prestadores.telefone as prestador_telefone',
                ])
                ->orderBy('avaliacoes.created_at', 'desc')
                ->paginate($perPage);
        });

        return response()->json(['success' => true, 'data' => $avaliacoes]);
    }

    /**
     * Criar avaliação - OTIMIZADO
     * POST /api/cliente/avaliacoes
     */
    public function createAvaliacao(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'prestador_id' => 'required|exists:users,id',
            'pedido_id' => 'required|exists:pedidos,id',
            'nota' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:500',
            'categorias' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        DB::beginTransaction();
        try {
            $pedido = Pedido::where('id', $request->pedido_id)
                ->where('cliente_id', $user->id)
                ->first();

            if (!$pedido) {
                return response()->json(['success' => false, 'error' => 'Pedido não encontrado'], 404);
            }

            $existe = Avaliacao::where('pedido_id', $request->pedido_id)
                ->where('cliente_id', $user->id)
                ->exists();

            if ($existe) {
                return response()->json(['success' => false, 'error' => 'Este pedido já foi avaliado'], 422);
            }

            $avaliacao = Avaliacao::create([
                'cliente_id' => $user->id,
                'prestador_id' => $request->prestador_id,
                'pedido_id' => $request->pedido_id,
                'nota' => $request->nota,
                'comentario' => $request->comentario,
                'categorias' => $request->categorias,
            ]);

            $prestador = User::find($request->prestador_id);
            if ($prestador) {
                try {
                    $comentarioResumo = $request->comentario ? substr($request->comentario, 0, 100) : 'Sem comentário';
                    $prestador->notify(new DynamicNotification('nova_avaliacao', [
                        'cliente_nome' => $user->nome,
                        'nota' => $request->nota,
                        'comentario_resumo' => $comentarioResumo,
                        'pedido_numero' => $pedido->numero ?? $pedido->id,
                        'avaliacao_id' => $avaliacao->id,
                    ]));
                } catch (\Exception $e) {
                    Log::warning('Erro ao enviar notificação: ' . $e->getMessage());
                }
            }

            $this->atualizarMediaPrestador($request->prestador_id);
            DB::commit();

            $this->clearClienteCache($user->id);
            $this->clearPrestadorCache($request->prestador_id);

            return response()->json(['success' => true, 'message' => 'Avaliação enviada com sucesso!', 'data' => $avaliacao], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar avaliação: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao enviar avaliação'], 500);
        }
    }

    /**
     * Atualizar avaliação - OTIMIZADO
     * PUT /api/cliente/avaliacoes/{id}
     */
    public function updateAvaliacao(Request $request, $id)
    {
        $user = $request->user();

        DB::beginTransaction();
        try {
            $avaliacao = Avaliacao::where('cliente_id', $user->id)->lockForUpdate()->find($id);

            if (!$avaliacao) {
                return response()->json(['success' => false, 'error' => 'Avaliação não encontrada'], 404);
            }

            $validator = Validator::make($request->all(), [
                'nota' => 'sometimes|integer|min:1|max:5',
                'comentario' => 'nullable|string|max:500',
                'categorias' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
            }

            $notaAnterior = $avaliacao->nota;
            $prestadorId = $avaliacao->prestador_id;

            if ($request->has('nota')) $avaliacao->nota = $request->nota;
            if ($request->has('comentario')) $avaliacao->comentario = $request->comentario;
            if ($request->has('categorias')) $avaliacao->categorias = $request->categorias;

            $avaliacao->save();

            if ($request->has('nota') && $notaAnterior != $request->nota) {
                $prestador = User::find($prestadorId);
                if ($prestador) {
                    try {
                        $prestador->notify(new DynamicNotification('avaliacao_atualizada', [
                            'cliente_nome' => $user->nome,
                            'nota_anterior' => $notaAnterior,
                            'nova_nota' => $request->nota,
                            'avaliacao_id' => $avaliacao->id,
                        ]));
                    } catch (\Exception $e) {
                        Log::warning('Erro ao enviar notificação: ' . $e->getMessage());
                    }
                }
            }

            $this->atualizarMediaPrestador($prestadorId);
            DB::commit();

            $this->clearClienteCache($user->id);
            $this->clearPrestadorCache($prestadorId);

            return response()->json(['success' => true, 'message' => 'Avaliação atualizada com sucesso', 'data' => $avaliacao]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar avaliação: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao atualizar avaliação'], 500);
        }
    }

    /**
     * Deletar avaliação - OTIMIZADO
     * DELETE /api/cliente/avaliacoes/{id}
     */
    public function deleteAvaliacao(Request $request, $id)
    {
        $user = $request->user();

        DB::beginTransaction();
        try {
            $avaliacao = Avaliacao::where('cliente_id', $user->id)->lockForUpdate()->find($id);

            if (!$avaliacao) {
                return response()->json(['success' => false, 'error' => 'Avaliação não encontrada'], 404);
            }

            $prestadorId = $avaliacao->prestador_id;
            $avaliacao->delete();

            $prestador = User::find($prestadorId);
            if ($prestador) {
                try {
                    $prestador->notify(new DynamicNotification('avaliacao_removida', [
                        'cliente_nome' => $user->nome,
                        'avaliacao_id' => $id,
                    ]));
                } catch (\Exception $e) {
                    Log::warning('Erro ao enviar notificação: ' . $e->getMessage());
                }
            }

            $this->atualizarMediaPrestador($prestadorId);
            DB::commit();

            $this->clearClienteCache($user->id);
            $this->clearPrestadorCache($prestadorId);

            return response()->json(['success' => true, 'message' => 'Avaliação removida com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao deletar avaliação: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao remover avaliação'], 500);
        }
    }

    /**
     * Verificar se pedido já foi avaliado
     * GET /api/cliente/pedidos/{id}/avaliacao
     */
    public function checkAvaliacao(Request $request, $pedidoId)
    {
        $user = $request->user();
        $cacheKey = "cliente_pedido_avaliacao_{$user->id}_{$pedidoId}";

        $existe = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user, $pedidoId) {
            return Avaliacao::where('pedido_id', $pedidoId)
                ->where('cliente_id', $user->id)
                ->exists();
        });

        return response()->json(['success' => true, 'data' => ['avaliado' => $existe]]);
    }

    /**
     * Atualizar média do prestador - OTIMIZADO
     */
    private function atualizarMediaPrestador($prestadorId)
    {
        // ✅ OTIMIZADO: query única
        $stats = DB::table('avaliacoes')
            ->where('prestador_id', $prestadorId)
            ->selectRaw('ROUND(AVG(nota), 1) as media, COUNT(*) as total')
            ->first();

        User::where('id', $prestadorId)->update([
            'media_avaliacao' => $stats->media ?? 0,
            'total_avaliacoes' => $stats->total ?? 0
        ]);

        Cache::forget("prestador_stats_{$prestadorId}");
        Cache::forget("prestador_detalhes_{$prestadorId}");
    }

    // ==========================================
    // 4. FAVORITOS - OTIMIZADO
    // ==========================================

    /**
     * Listar favoritos do cliente - OTIMIZADO
     * GET /api/cliente/favoritos
     */
    public function favoritos(Request $request)
    {
        try {
            $user = $request->user();
            $cacheKey = "cliente_favoritos_{$user->id}";

            $favoritos = Cache::remember($cacheKey, 300, function () use ($user) {
                return Favorito::where('cliente_id', $user->id)
                    ->with('prestador:id,nome,foto,telefone,media_avaliacao,profissao')
                    ->orderBy('created_at', 'desc')
                    ->limit(20)  // ← **ADICIONAR ESTA LINHA**
                    ->get()
                    ->map(fn($fav) => [
                        'id' => $fav->id,
                        'created_at' => $fav->created_at,
                        'prestador' => $fav->prestador ? [
                            'id' => $fav->prestador->id,
                            'nome' => $fav->prestador->nome,
                            'foto' => $fav->prestador->foto ? asset('storage/' . $fav->prestador->foto) : null,
                            'telefone' => $fav->prestador->telefone,
                            'media_avaliacao' => (float) ($fav->prestador->media_avaliacao ?? 0),
                            'profissao' => $fav->prestador->profissao ?? '',
                        ] : null,
                    ])
                    ->toArray();
            });

            return response()->json(['success' => true, 'data' => $favoritos]);
        } catch (\Exception $e) {
            Log::error('Erro em favoritos: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao carregar favoritos'], 500);
        }
    }

    /**
     * Adicionar prestador aos favoritos
     * POST /api/cliente/favoritos/{prestadorId}
     */
    public function addFavorito(Request $request, $prestadorId)
    {
        $user = $request->user();

        $prestador = User::where('tipo', 'prestador')->find($prestadorId);
        if (!$prestador) {
            return response()->json(['success' => false, 'error' => 'Prestador não encontrado'], 404);
        }

        $existe = Favorito::where('cliente_id', $user->id)
            ->where('prestador_id', $prestadorId)
            ->exists();

        if ($existe) {
            return response()->json(['success' => false, 'error' => 'Prestador já está nos favoritos'], 422);
        }

        DB::beginTransaction();
        try {
            $favorito = Favorito::create([
                'cliente_id' => $user->id,
                'prestador_id' => $prestadorId,
            ]);

            DB::commit();
            Cache::forget("cliente_favoritos_{$user->id}");

            return response()->json(['success' => true, 'message' => 'Prestador adicionado aos favoritos', 'data' => $favorito], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao adicionar favorito: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao adicionar favorito'], 500);
        }
    }

    /**
     * Remover prestador dos favoritos
     * DELETE /api/cliente/favoritos/{prestadorId}
     */
    public function removeFavorito(Request $request, $prestadorId)
    {
        $user = $request->user();

        DB::beginTransaction();
        try {
            $deleted = Favorito::where('cliente_id', $user->id)
                ->where('prestador_id', $prestadorId)
                ->delete();

            if (!$deleted) {
                return response()->json(['success' => false, 'error' => 'Prestador não está nos favoritos'], 404);
            }

            DB::commit();
            Cache::forget("cliente_favoritos_{$user->id}");

            return response()->json(['success' => true, 'message' => 'Prestador removido dos favoritos']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao remover favorito: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao remover favorito'], 500);
        }
    }

    /**
     * Verificar se prestador é favorito
     * GET /api/cliente/favoritos/{prestadorId}/check
     */
    public function checkFavorito(Request $request, $prestadorId)
    {
        $user = $request->user();
        $cacheKey = "cliente_favorito_check_{$user->id}_{$prestadorId}";

        $isFavorito = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user, $prestadorId) {
            return Favorito::where('cliente_id', $user->id)
                ->where('prestador_id', $prestadorId)
                ->exists();
        });

        return response()->json(['success' => true, 'data' => ['is_favorito' => $isFavorito]]);
    }

    // ==========================================
    // 5. MÉTODOS AUXILIARES PARA LIMPAR CACHE
    // ==========================================

    private function clearClienteCache($userId)
    {
        Cache::forget("cliente_favoritos_{$userId}");
        Cache::forget("cliente_avaliacoes_{$userId}_10");
        Cache::forget("cliente_meus_pedidos_{$userId}_20");

        for ($page = 1; $page <= 3; $page++) {
            Cache::forget("cliente_pedidos_{$userId}_all_{$page}");
            Cache::forget("cliente_pedidos_{$userId}_pendente_{$page}");
            Cache::forget("cliente_pedidos_{$userId}_concluido_{$page}");
        }
    }

    private function clearPrestadorCache($prestadorId)
    {
        Cache::forget("prestador_stats_{$prestadorId}");
        Cache::forget("prestador_detalhes_{$prestadorId}");
    }
}
