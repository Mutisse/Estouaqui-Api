<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Servico;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\Agenda;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PrestadorController extends Controller
{
    // ==========================================
    // 1. REGISTRO DO PRESTADOR
    // ==========================================

    /**
     * Registro de novo prestador
     * POST /api/register/prestador
     */
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
            // VALIDAÇÃO PARA AS 3 FOTOS DO PORTFÓLIO
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
            // Processar as 3 fotos do portfólio (acessando como array)
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
    // 2. SERVIÇOS DO PRESTADOR
    // ==========================================

    /**
     * Listar serviços do prestador
     * GET /api/prestador/servicos
     */
    public function servicos(Request $request)
    {
        $user = $request->user();

        $servicos = Servico::where('prestador_id', $user->id)
            ->with('categoria')
            ->orderBy('created_at', 'desc')
            ->get();

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
        $semana = $request->query('semana'); // offset de semanas

        // TODO: Implementar lógica de agenda
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

        // TODO: Implementar bloqueio de horário
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
        // TODO: Implementar desbloqueio
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
        $status = $request->query('status'); // pendente, confirmado, concluido, cancelado

        $query = Pedido::where('prestador_id', $user->id)
            ->with(['cliente', 'servico']);

        if ($status) {
            $query->where('status', $status);
        }

        $pedidos = $query->orderBy('created_at', 'desc')->paginate(20);

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

        // TODO: Implementar relação many-to-many
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    /**
     * Adicionar categoria
     * POST /api/prestador/categorias/{categoriaId}
     */
    public function addCategoria(Request $request, $categoriaId)
    {
        $user = $request->user();

        // TODO: Implementar adição de categoria
        return response()->json([
            'success' => true,
            'message' => 'Categoria adicionada'
        ]);
    }

    /**
     * Remover categoria
     * DELETE /api/prestador/categorias/{categoriaId}
     */
    public function removeCategoria($categoriaId)
    {
        // TODO: Implementar remoção de categoria
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
        $user = $request->user();

        $pedidosPendentes = Pedido::where('prestador_id', $user->id)
            ->where('status', 'pendente')
            ->count();

        $servicosHoje = Pedido::where('prestador_id', $user->id)
            ->whereDate('data', today())
            ->whereIn('status', ['aceito', 'em_andamento'])
            ->count();

        $avaliacaoMedia = Avaliacao::where('prestador_id', $user->id)
            ->avg('nota');

        $ganhosMes = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->whereMonth('created_at', now()->month)
            ->sum('valor');

        return response()->json([
            'success' => true,
            'data' => [
                'pedidos_pendentes' => $pedidosPendentes,
                'servicos_hoje' => $servicosHoje,
                'avaliacao_media' => round($avaliacaoMedia ?? 0, 1),
                'ganhos_mes' => $ganhosMes,
            ]
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
        $query = User::where('tipo', 'prestador')
            ->where('ativo', true)
            ->with('categorias');

        // Filtro por categoria
        if ($request->has('categoria')) {
            $query->whereHas('categorias', function ($q) use ($request) {
                $q->where('categoria_id', $request->categoria);
            });
        }

        // Filtro por busca
        if ($request->has('busca')) {
            $query->where('nome', 'like', '%' . $request->busca . '%');
        }

        $prestadores = $query->paginate(20);

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
        $prestador = User::where('tipo', 'prestador')
            ->with(['servicos', 'categorias', 'avaliacoes'])
            ->find($id);

        if (!$prestador) {
            return response()->json([
                'success' => false,
                'error' => 'Prestador não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $prestador
        ]);
    }

    /**
     * Prestadores em destaque (público)
     * GET /api/prestadores/destaque
     */
    public function destaque()
    {
        $prestadores = User::where('tipo', 'prestador')
            ->where('ativo', true)
            ->orderBy('media_avaliacao', 'desc')
            ->limit(10)
            ->get();

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
        $prestadores = User::where('tipo', 'prestador')
            ->where('ativo', true)
            ->orderBy('media_avaliacao', 'desc')
            ->limit(10)
            ->get();

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

        // TODO: Implementar busca por proximidade
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
        $categorias = Categoria::where('ativo', true)->get();

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
        $avaliacoes = Avaliacao::where('prestador_id', $id)
            ->with('cliente')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }
}
