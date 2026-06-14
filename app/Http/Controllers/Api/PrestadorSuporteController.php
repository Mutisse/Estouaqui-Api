<?php
// app/Http/Controllers/Api/PrestadorSuporteController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\MensagemTicket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PrestadorSuporteController extends BaseController
{
    /**
     * GET /prestador/suporte/tickets
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $tickets = Ticket::where('prestador_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $estatisticas = [
            'total' => $tickets->count(),
            'abertos' => $tickets->where('status', 'aberto')->count(),
            'andamento' => $tickets->where('status', 'em_andamento')->count(),
            'resolvidos' => $tickets->where('status', 'resolvido')->count(),
            'fechados' => $tickets->where('status', 'fechado')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $tickets,
            'estatisticas' => $estatisticas
        ]);
    }

    /**
     * GET /prestador/suporte/tickets/{id}
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        $ticket = Ticket::where('prestador_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    /**
     * GET /prestador/suporte/tickets/{id}/mensagens
     */
    public function mensagens($id, Request $request)
    {
        $user = $request->user();

        $ticket = Ticket::where('prestador_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket não encontrado'
            ], 404);
        }

        $mensagens = MensagemTicket::where('ticket_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mensagens
        ]);
    }

    /**
     * POST /prestador/suporte/tickets
     */
    public function store(Request $request)
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'titulo' => 'required|string|max:255',
                'descricao' => 'required|string',
                'categoria' => 'required|string',
                'prioridade' => 'required|in:baixa,media,alta,urgente',
                'codigo_erro' => 'nullable|string',
                'anexos' => 'nullable|array',
                'anexos.*' => 'file|image|max:5120'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        }

        $numero = 'TK' . strtoupper(uniqid());

        $anexosPaths = [];
        if ($request->hasFile('anexos')) {
            foreach ($request->file('anexos') as $file) {
                $path = $file->store('tickets/' . $user->id, 'public');
                $anexosPaths[] = asset('storage/' . $path);
            }
        }

        $ticket = Ticket::create([
            'numero' => $numero,
            'titulo' => $validated['titulo'],
            'descricao' => $validated['descricao'],
            'categoria' => $validated['categoria'],
            'prioridade' => $validated['prioridade'],
            'status' => 'aberto',
            'prestador_id' => $user->id,
            'anexos' => json_encode($anexosPaths),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mensagem = $validated['descricao'];
        if (!empty($validated['codigo_erro'])) {
            $mensagem .= "\n\n---\n**Código do erro:** " . $validated['codigo_erro'];
        }

        MensagemTicket::create([
            'ticket_id' => $ticket->id,
            'remetente_id' => $user->id,
            'remetente_tipo' => 'prestador',
            'remetente_nome' => $user->nome ?? $user->name,
            'mensagem' => $mensagem,
            'anexos' => json_encode($anexosPaths),
            'lida' => false,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $ticket,
            'message' => 'Ticket criado com sucesso'
        ], 201);
    }

    /**
     * POST /prestador/suporte/tickets/{id}/mensagens
     */
    public function enviarMensagem(Request $request, $id)
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'mensagem' => 'required|string'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        }

        $ticket = Ticket::where('prestador_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket não encontrado'
            ], 404);
        }

        $mensagem = MensagemTicket::create([
            'ticket_id' => $id,
            'remetente_id' => $user->id,
            'remetente_tipo' => 'prestador',
            'remetente_nome' => $user->nome ?? $user->name,
            'mensagem' => $validated['mensagem'],
            'lida' => false,
            'created_at' => now(),
        ]);

        $ticket->updated_at = now();
        $ticket->save();

        return response()->json([
            'success' => true,
            'data' => $mensagem,
            'message' => 'Mensagem enviada'
        ], 201);
    }

    /**
     * PUT /prestador/suporte/tickets/{id}/fechar
     */
    public function fechar($id, Request $request)
    {
        $user = $request->user();

        $ticket = Ticket::where('prestador_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket não encontrado'
            ], 404);
        }

        $ticket->status = 'fechado';
        $ticket->resolvido_em = now();
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Ticket fechado com sucesso'
        ]);
    }
}
