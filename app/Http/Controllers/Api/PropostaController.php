<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposta;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\DynamicNotification;

class PropostaController extends Controller
{
    /**
     * Prestador envia proposta para um pedido
     * POST /api/prestador/propostas
     */
    public function store(Request $request)
    {
        $prestador = $request->user();

        // Verificar se é prestador
        if (!$prestador->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas prestadores podem enviar propostas'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'pedido_id' => 'required|exists:pedidos,id',
            'valor' => 'required|numeric|min:0',
            'mensagem' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $pedido = Pedido::find($request->pedido_id);

        // Verificar se o pedido está pendente
        if ($pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido já não está disponível'
            ], 422);
        }

        // Verificar se o prestador atende a categoria do pedido
        $categoriasDoPrestador = $prestador->categorias()->pluck('categorias.id')->toArray();
        if (!in_array($pedido->categoria_id, $categoriasDoPrestador)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não atende a categoria deste serviço'
            ], 422);
        }

        // Verificar se já enviou proposta para este pedido
        $existe = Proposta::where('pedido_id', $pedido->id)
            ->where('prestador_id', $prestador->id)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Você já enviou uma proposta para este pedido'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $proposta = Proposta::create([
                'pedido_id' => $pedido->id,
                'prestador_id' => $prestador->id,
                'valor' => $request->valor,
                'mensagem' => $request->mensagem,
                'status' => 'pendente',
            ]);

            // ✅ NOTIFICAÇÃO: Nova proposta para o CLIENTE
            $cliente = $pedido->cliente;
            if ($cliente) {
                $cliente->notify(new DynamicNotification('nova_proposta', [
                    'prestador_nome' => $prestador->nome,
                    'valor' => number_format($request->valor, 2, ',', '.'),
                    'pedido_numero' => $pedido->numero ?? $pedido->id,
                    'pedido_id' => $pedido->id,
                ]));
                Log::info("Notificação 'nova_proposta' enviada para o cliente ID: {$cliente->id}");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proposta enviada com sucesso!',
                'data' => $proposta
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cliente aceita uma proposta
     * PUT /api/cliente/propostas/{id}/aceitar
     */
    public function aceitar(Request $request, $id)
    {
        $cliente = $request->user();

        $proposta = Proposta::with('pedido')->find($id);

        if (!$proposta) {
            return response()->json([
                'success' => false,
                'message' => 'Proposta não encontrada'
            ], 404);
        }

        // Verificar se o pedido pertence ao cliente
        if ($proposta->pedido->cliente_id !== $cliente->id) {
            return response()->json([
                'success' => false,
                'message' => 'Esta proposta não pertence aos seus pedidos'
            ], 403);
        }

        // Verificar se a proposta está pendente
        if ($proposta->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Esta proposta já foi ' . $proposta->status
            ], 422);
        }

        // Verificar se o pedido está pendente
        if ($proposta->pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido já não está disponível'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Aceitar a proposta
            $proposta->status = 'aceita';
            $proposta->save();

            // Atualizar o pedido
            $pedido = $proposta->pedido;
            $pedido->status = 'aceito';
            $pedido->prestador_id = $proposta->prestador_id;
            $pedido->valor = $proposta->valor;
            $pedido->save();

            // Recusar todas as outras propostas para este pedido
            Proposta::where('pedido_id', $pedido->id)
                ->where('id', '!=', $id)
                ->where('status', 'pendente')
                ->update(['status' => 'recusada']);

            // ✅ NOTIFICAÇÃO: Proposta aceita para o PRESTADOR
            $prestador = $proposta->prestador;
            if ($prestador) {
                $prestador->notify(new DynamicNotification('solicitacao_aceita', [
                    'cliente_nome' => $cliente->nome,
                    'pedido_numero' => $pedido->numero ?? $pedido->id,
                    'valor' => number_format($proposta->valor, 2, ',', '.'),
                    'pedido_id' => $pedido->id,
                ]));
                Log::info("Notificação 'solicitacao_aceita' enviada para o prestador ID: {$prestador->id}");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proposta aceita! O prestador será contactado.',
                'data' => [
                    'pedido' => $pedido,
                    'proposta' => $proposta
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao aceitar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aceitar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cliente recusa uma proposta
     * PUT /api/cliente/propostas/{id}/recusar
     */
    public function recusar(Request $request, $id)
    {
        $cliente = $request->user();

        $proposta = Proposta::with('pedido')->find($id);

        if (!$proposta) {
            return response()->json([
                'success' => false,
                'message' => 'Proposta não encontrada'
            ], 404);
        }

        // Verificar se o pedido pertence ao cliente
        if ($proposta->pedido->cliente_id !== $cliente->id) {
            return response()->json([
                'success' => false,
                'message' => 'Esta proposta não pertence aos seus pedidos'
            ], 403);
        }

        // Verificar se a proposta está pendente
        if ($proposta->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Esta proposta já foi ' . $proposta->status
            ], 422);
        }

        try {
            DB::beginTransaction();

            $proposta->status = 'recusada';
            $proposta->save();

            // ✅ NOTIFICAÇÃO: Proposta recusada para o PRESTADOR
            $prestador = $proposta->prestador;
            if ($prestador) {
                $prestador->notify(new DynamicNotification('solicitacao_recusada', [
                    'cliente_nome' => $cliente->nome,
                    'pedido_numero' => $proposta->pedido->numero ?? $proposta->pedido->id,
                    'pedido_id' => $proposta->pedido->id,
                ]));
                Log::info("Notificação 'solicitacao_recusada' enviada para o prestador ID: {$prestador->id}");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proposta recusada'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao recusar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao recusar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cliente vê todas as propostas dos seus pedidos
     * GET /api/cliente/propostas
     */
    public function minhasPropostasCliente(Request $request)
    {
        $cliente = $request->user();

        $propostas = Proposta::whereHas('pedido', function ($query) use ($cliente) {
            $query->where('cliente_id', $cliente->id);
        })
            ->with(['prestador' => function ($q) {
                $q->select('id', 'nome', 'foto', 'telefone', 'media_avaliacao', 'latitude', 'longitude');
            }, 'pedido'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($proposta) {
                return [
                    'id' => $proposta->id,
                    'valor' => (float) $proposta->valor,
                    'mensagem' => $proposta->mensagem,
                    'status' => $proposta->status,
                    'created_at' => $proposta->created_at->toISOString(),
                    'prestador' => $proposta->prestador ? [
                        'id' => $proposta->prestador->id,
                        'nome' => $proposta->prestador->nome,
                        'foto' => $proposta->prestador->foto ? asset('storage/' . $proposta->prestador->foto) : null,
                        'telefone' => $proposta->prestador->telefone,
                        'media_avaliacao' => (float) ($proposta->prestador->media_avaliacao ?? 0),
                    ] : null,
                    'pedido' => $proposta->pedido ? [
                        'id' => $proposta->pedido->id,
                        'descricao' => $proposta->pedido->descricao,
                        'endereco' => $proposta->pedido->endereco,
                        'status' => $proposta->pedido->status,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $propostas
        ]);
    }

    /**
     * Prestador vê todas as suas propostas
     * GET /api/prestador/propostas
     */
    public function minhasPropostasPrestador(Request $request)
    {
        $prestador = $request->user();

        $propostas = Proposta::where('prestador_id', $prestador->id)
            ->with(['pedido' => function ($q) {
                $q->select('id', 'numero', 'descricao', 'endereco', 'status', 'created_at', 'categoria_id')
                    ->with('categoria:id,nome,icone,cor');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($proposta) {
                return [
                    'id' => $proposta->id,
                    'valor' => (float) $proposta->valor,
                    'mensagem' => $proposta->mensagem,
                    'status' => $proposta->status,
                    'created_at' => $proposta->created_at->toISOString(),
                    'pedido' => $proposta->pedido ? [
                        'id' => $proposta->pedido->id,
                        'numero' => $proposta->pedido->numero,
                        'descricao' => $proposta->pedido->descricao,
                        'endereco' => $proposta->pedido->endereco,
                        'status' => $proposta->pedido->status,
                        'created_at' => $proposta->pedido->created_at->toISOString(),
                        'categoria' => $proposta->pedido->categoria ? [
                            'id' => $proposta->pedido->categoria->id,
                            'nome' => $proposta->pedido->categoria->nome,
                            'icone' => $proposta->pedido->categoria->icone,
                            'cor' => $proposta->pedido->categoria->cor,
                        ] : null,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $propostas
        ]);
    }

    /**
     * Prestador vê pedidos disponíveis para proposta
     * GET /api/prestador/pedidos-disponiveis
     */
    public function pedidosDisponiveis(Request $request)
    {
        $prestador = $request->user();

        // Parâmetros de filtro
        $categoriaId = $request->query('categoria_id');
        $raioKm = (float) $request->query('raio_km', 10);
        $ordenarPor = $request->query('ordenar_por', 'distancia');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        // 1. Buscar categorias que o prestador atende
        $categoriasDoPrestador = $prestador->categorias()
            ->pluck('categorias.id')
            ->toArray();

        // Se o prestador não tem categorias, retorna vazio
        if (empty($categoriasDoPrestador)) {
            return response()->json([
                'success' => true,
                'data' => [],  // ✅ RETORNAR ARRAY VAZIO
                'filtros' => [
                    'categorias_disponiveis' => [],
                    'categoria_selecionada' => $categoriaId,
                    'raio_km' => $raioKm,
                    'ordenar_por' => $ordenarPor,
                    'tem_localizacao' => false,
                ],
                'paginacao' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 0,
                ],
                'message' => 'Você ainda não definiu suas categorias de atuação'
            ]);
        }

        // Aplicar filtro de categoria específica se enviado
        $categoriasFiltro = $categoriaId
            ? (in_array($categoriaId, $categoriasDoPrestador) ? [$categoriaId] : [])
            : $categoriasDoPrestador;

        if (empty($categoriasFiltro)) {
            return response()->json([
                'success' => true,
                'data' => [],  // ✅ RETORNAR ARRAY VAZIO
                'filtros' => [
                    'categorias_disponiveis' => $categoriasDoPrestador,
                    'categoria_selecionada' => $categoriaId,
                    'raio_km' => $raioKm,
                    'ordenar_por' => $ordenarPor,
                    'tem_localizacao' => !empty($prestador->latitude),
                ],
                'paginacao' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 0,
                ],
                'message' => 'Categoria selecionada não está nas suas áreas de atuação'
            ]);
        }

        // 2. Buscar pedidos pendentes
        $query = Pedido::where('status', 'pendente')
            ->whereDoesntHave('propostas', function ($q) use ($prestador) {
                $q->where('prestador_id', $prestador->id);
            })
            ->whereIn('categoria_id', $categoriasFiltro)
            ->with(['cliente' => function ($q) {
                $q->select('id', 'nome', 'foto', 'telefone', 'latitude', 'longitude');
            }, 'categoria']);

        // 3. Buscar pedidos
        $pedidos = $query->get();

        // 4. Calcular distância para cada pedido
        $prestadorLat = $prestador->latitude;
        $prestadorLng = $prestador->longitude;
        $temLocalizacao = !empty($prestadorLat) && !empty($prestadorLng);

        foreach ($pedidos as $pedido) {
            $pedido->distancia_km = null;

            if ($temLocalizacao && $pedido->cliente && $pedido->cliente->latitude && $pedido->cliente->longitude) {
                $pedido->distancia_km = $this->calcularDistancia(
                    (float) $prestadorLat,
                    (float) $prestadorLng,
                    (float) $pedido->cliente->latitude,
                    (float) $pedido->cliente->longitude
                );
            }
        }

        // 5. Filtrar por raio de distância
        if ($temLocalizacao && $raioKm > 0) {
            $pedidos = $pedidos->filter(function ($pedido) use ($raioKm) {
                if ($pedido->distancia_km === null) {
                    return true;
                }
                return $pedido->distancia_km <= $raioKm;
            });
        }

        // 6. Ordenar resultados
        $collection = collect($pedidos);

        if ($ordenarPor === 'distancia' && $temLocalizacao) {
            $collection = $collection->sortBy(function ($pedido) {
                return $pedido->distancia_km ?? PHP_FLOAT_MAX;
            });
        } elseif ($ordenarPor === 'valor') {
            $collection = $collection->sortBy('valor');
        } else {
            $collection = $collection->sortByDesc('created_at');
        }

        // 7. ✅ CONVERTER PARA ARRAY (NÃO USAR PAGINATOR DO ELOQUENT)
        $total = $collection->count();
        $offset = ($page - 1) * $perPage;
        $pedidosPaginated = $collection->slice($offset, $perPage)->values();

        // 8. Formatar resposta
        $result = $pedidosPaginated->map(function ($pedido) {
            $distancia = $pedido->distancia_km;
            $distanciaTexto = $distancia !== null
                ? ($distancia < 1
                    ? round($distancia * 1000) . 'm'
                    : round($distancia, 1) . 'km')
                : null;

            return [
                'id' => (int) $pedido->id,
                'descricao' => (string) $pedido->descricao,
                'foto' => $pedido->foto ? asset('storage/' . $pedido->foto) : null,
                'endereco' => (string) $pedido->endereco,
                'created_at' => $pedido->created_at->toISOString(),
                'distancia_km' => $distancia,
                'distancia_texto' => $distanciaTexto,
                'categoria' => $pedido->categoria ? [
                    'id' => (int) $pedido->categoria->id,
                    'nome' => (string) $pedido->categoria->nome,
                    'icone' => (string) ($pedido->categoria->icone ?? 'category'),
                    'cor' => (string) ($pedido->categoria->cor ?? 'primary'),
                ] : null,
                'cliente' => $pedido->cliente ? [
                    'id' => (int) $pedido->cliente->id,
                    'nome' => (string) $pedido->cliente->nome,
                    'foto' => $pedido->cliente->foto ? asset('storage/' . $pedido->cliente->foto) : null,
                ] : null,
            ];
        })->toArray();  // ✅ GARANTIR QUE É ARRAY

        // ✅ RETORNAR COMO ARRAY SIMPLES
        return response()->json([
            'success' => true,
            'data' => $result,  // ✅ ARRAY, não LengthAwarePaginator
            'filtros' => [
                'categorias_disponiveis' => $categoriasDoPrestador,
                'categoria_selecionada' => $categoriaId,
                'raio_km' => $raioKm,
                'ordenar_por' => $ordenarPor,
                'tem_localizacao' => $temLocalizacao,
            ],
            'paginacao' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
            ]
        ]);
    }

    /**
     * Calcular distância entre dois pontos (Haversine formula)
     *
     * @param float $lat1 Latitude do ponto 1
     * @param float $lon1 Longitude do ponto 1
     * @param float $lat2 Latitude do ponto 2
     * @param float $lon2 Longitude do ponto 2
     * @return float Distância em quilômetros
     */
    private function calcularDistancia($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Raio da Terra em km

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
