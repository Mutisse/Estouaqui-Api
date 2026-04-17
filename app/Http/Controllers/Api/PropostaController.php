<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposta;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropostaController extends Controller
{
    /**
     * Prestador envia proposta para um pedido
     * POST /api/prestador/propostas
     */
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

        // ✅ CORRIGIDO: Verificar se o pedido está pendente (não 'aberto')
        if ($pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido já não está disponível'
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
            $proposta = Proposta::create([
                'pedido_id' => $pedido->id,
                'prestador_id' => $prestador->id,
                'valor' => $request->valor,
                'mensagem' => $request->mensagem,
                'status' => 'pendente',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proposta enviada com sucesso!',
                'data' => $proposta
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar proposta'
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

        // Verificar se o pedido ainda está aberto
        if ($proposta->pedido->status !== 'aberto') {
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
                'message' => 'Erro ao aceitar proposta'
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
            $proposta->status = 'recusada';
            $proposta->save();

            return response()->json([
                'success' => true,
                'message' => 'Proposta recusada'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao recusar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao recusar proposta'
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
                $q->select('id', 'nome', 'foto', 'telefone', 'media_avaliacao');
            }, 'pedido'])
            ->orderBy('created_at', 'desc')
            ->get();

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
                $q->select('id', 'numero', 'descricao', 'endereco', 'status', 'created_at');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

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

        // Buscar pedidos que o prestador AINDA NÃO fez proposta
        $pedidos = Pedido::where('status', 'pendente')
            ->whereDoesntHave('propostas', function ($query) use ($prestador) {
                $query->where('prestador_id', $prestador->id);
            })
            // Opcional: filtrar por categorias que o prestador atende
            ->whereIn('categoria_id', $prestador->categorias()->pluck('categorias.id'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }
}
