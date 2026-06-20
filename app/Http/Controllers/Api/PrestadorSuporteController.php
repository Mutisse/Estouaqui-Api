<?php
// app/Http/Controllers/Api/PrestadorSuporteController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\MensagemTicket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PrestadorSuporteController extends BaseController
{
    // ==========================================
    // 🔥 HELPER PARA GERAR URL DE IMAGENS
    // ==========================================

    /**
     * Gera URL correta para imagens usando a rota /imagem
     */
    private function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Limpa o path
        $path = ltrim($path, '/');
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = str_replace('app/public/', '', $path);
        $path = str_replace('tickets/', '', $path);

        // 🔥 USA A ROTA /imagem
        return url('/imagem/' . $path);
    }

    /**
     * Processa anexos para URLs corretas
     */
    private function processAnexos($anexos): array
    {
        if (empty($anexos)) {
            return [];
        }

        $anexosArray = is_string($anexos) ? json_decode($anexos, true) : $anexos;

        if (!is_array($anexosArray)) {
            return [];
        }

        return array_map(function ($anexo) {
            // Se for string e não for URL, gerar URL
            if (is_string($anexo) && !str_starts_with($anexo, 'http')) {
                return $this->getImageUrl($anexo);
            }
            return $anexo;
        }, $anexosArray);
    }

    // ==========================================
    // 🔥 LISTAR TICKETS (CORRIGIDO)
    // ==========================================

    /**
     * GET /prestador/suporte/tickets
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $tickets = Ticket::where('prestador_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // 🔥 PROCESSAR ANEXOS
        $tickets->each(function ($ticket) {
            if ($ticket->anexos) {
                $ticket->anexos = $this->processAnexos($ticket->anexos);
            }
        });

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

    // ==========================================
    // 🔥 MOSTRAR TICKET (CORRIGIDO)
    // ==========================================

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

        // 🔥 PROCESSAR ANEXOS
        if ($ticket->anexos) {
            $ticket->anexos = $this->processAnexos($ticket->anexos);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    // ==========================================
    // 🔥 MENSAGENS DO TICKET (CORRIGIDO)
    // ==========================================

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

        // 🔥 PROCESSAR ANEXOS DAS MENSAGENS
        $mensagens->each(function ($mensagem) {
            if ($mensagem->anexos) {
                $mensagem->anexos = $this->processAnexos($mensagem->anexos);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $mensagens
        ]);
    }

    // ==========================================
    // 🔥 CRIAR TICKET (CORRIGIDO)
    // ==========================================

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
        $anexosUrls = [];

        if ($request->hasFile('anexos')) {
            foreach ($request->file('anexos') as $file) {
                // 🔥 SALVAR NA PASTA CORRETA: tickets/{user_id}/
                $extension = $file->getClientOriginalExtension();
                $filename = 'anexo_' . time() . '_' . uniqid() . '.' . $extension;
                $path = $file->storeAs('tickets/' . $user->id, $filename, 'public');

                $anexosPaths[] = $path;
                $anexosUrls[] = $this->getImageUrl($path);
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
            'anexos' => json_encode($anexosUrls),
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
            'anexos' => json_encode($anexosUrls),
            'lida' => false,
            'created_at' => now(),
        ]);

        // 🔥 RETORNAR COM ANEXOS PROCESSADOS
        $ticket->anexos = $anexosUrls;

        return response()->json([
            'success' => true,
            'data' => $ticket,
            'message' => 'Ticket criado com sucesso'
        ], 201);
    }

    // ==========================================
    // 🔥 ENVIAR MENSAGEM (CORRIGIDO)
    // ==========================================

    /**
     * POST /prestador/suporte/tickets/{id}/mensagens
     */
    public function enviarMensagem(Request $request, $id)
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'mensagem' => 'required|string',
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

        $ticket = Ticket::where('prestador_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket não encontrado'
            ], 404);
        }

        // 🔥 PROCESSAR ANEXOS DA MENSAGEM
        $anexosUrls = [];
        if ($request->hasFile('anexos')) {
            foreach ($request->file('anexos') as $file) {
                $extension = $file->getClientOriginalExtension();
                $filename = 'msg_' . time() . '_' . uniqid() . '.' . $extension;
                $path = $file->storeAs('tickets/' . $user->id . '/mensagens', $filename, 'public');
                $anexosUrls[] = $this->getImageUrl($path);
            }
        }

        $mensagem = MensagemTicket::create([
            'ticket_id' => $id,
            'remetente_id' => $user->id,
            'remetente_tipo' => 'prestador',
            'remetente_nome' => $user->nome ?? $user->name,
            'mensagem' => $validated['mensagem'],
            'anexos' => json_encode($anexosUrls),
            'lida' => false,
            'created_at' => now(),
        ]);

        // 🔥 RETORNAR COM ANEXOS PROCESSADOS
        $mensagem->anexos = $anexosUrls;

        $ticket->updated_at = now();
        $ticket->save();

        return response()->json([
            'success' => true,
            'data' => $mensagem,
            'message' => 'Mensagem enviada'
        ], 201);
    }

    // ==========================================
    // 🔥 FECHAR TICKET
    // ==========================================

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

    // ==========================================
    // 🔥 REABRIR TICKET
    // ==========================================

    /**
     * PUT /prestador/suporte/tickets/{id}/reabrir
     */
    public function reabrir($id, Request $request)
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

        if ($ticket->status !== 'fechado') {
            return response()->json([
                'success' => false,
                'message' => 'Apenas tickets fechados podem ser reabertos'
            ], 422);
        }

        $ticket->status = 'em_andamento';
        $ticket->resolvido_em = null;
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Ticket reaberto com sucesso'
        ]);
    }

    // ==========================================
    // 🔥 ESTATÍSTICAS DO PRESTADOR
    // ==========================================

    /**
     * GET /prestador/suporte/estatisticas
     */
    public function estatisticas(Request $request)
    {
        $user = $request->user();

        $tickets = Ticket::where('prestador_id', $user->id)->get();

        $estatisticas = [
            'total' => $tickets->count(),
            'abertos' => $tickets->where('status', 'aberto')->count(),
            'em_andamento' => $tickets->where('status', 'em_andamento')->count(),
            'resolvidos' => $tickets->where('status', 'resolvido')->count(),
            'fechados' => $tickets->where('status', 'fechado')->count(),
            'por_prioridade' => [
                'baixa' => $tickets->where('prioridade', 'baixa')->count(),
                'media' => $tickets->where('prioridade', 'media')->count(),
                'alta' => $tickets->where('prioridade', 'alta')->count(),
                'urgente' => $tickets->where('prioridade', 'urgente')->count(),
            ],
            'por_categoria' => $tickets->groupBy('categoria')->map->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $estatisticas
        ]);
    }
}
