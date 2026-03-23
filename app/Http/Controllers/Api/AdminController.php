<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Servico;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\Transacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // ==========================================
    // 1. DASHBOARD E ESTATÍSTICAS
    // ==========================================

    /**
     * Dashboard do admin
     * GET /api/admin/dashboard
     */
    public function dashboard()
    {
        $totalUsers = User::count();
        $totalClientes = User::where('tipo', 'cliente')->count();
        $totalPrestadores = User::where('tipo', 'prestador')->count();
        $totalAdmins = User::where('tipo', 'admin')->count();

        $prestadoresAtivos = User::where('tipo', 'prestador')
            ->where('ativo', true)
            ->count();

        $servicosHoje = Pedido::whereDate('created_at', today())->count();
        $servicosPendentes = Pedido::where('status', 'pendente')->count();

        $avaliacaoMedia = Avaliacao::avg('nota') ?? 0;
        $totalAvaliacoes = Avaliacao::count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_clientes' => $totalClientes,
                'total_prestadores' => $totalPrestadores,
                'total_admins' => $totalAdmins,
                'prestadores_ativos' => $prestadoresAtivos,
                'servicos_hoje' => $servicosHoje,
                'servicos_pendentes' => $servicosPendentes,
                'avaliacao_media' => round($avaliacaoMedia, 1),
                'total_avaliacoes' => $totalAvaliacoes,
            ]
        ]);
    }

    /**
     * Atividade dos últimos 7 dias
     * GET /api/admin/atividade
     */
    public function atividade()
    {
        $dias = [];
        for ($i = 6; $i >= 0; $i--) {
            $data = now()->subDays($i);
            $dias[] = [
                'dia' => $data->format('D'),
                'valor' => Pedido::whereDate('created_at', $data)->count(),
                'data' => $data->format('Y-m-d'),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $dias
        ]);
    }


    // ==========================================
    // 2. GESTÃO DE UTILIZADORES
    // ==========================================

    /**
     * Listar todos os utilizadores
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filtro por tipo
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        // Filtro por status (ativo/bloqueado)
        if ($request->has('status')) {
            if ($request->status === 'bloqueado') {
                $query->whereNotNull('blocked_at');
            } else {
                $query->whereNull('blocked_at');
            }
        }

        // Busca por nome ou email
        if ($request->has('busca')) {
            $busca = $request->busca;
            $query->where(function($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                  ->orWhere('email', 'like', "%{$busca}%")
                  ->orWhere('telefone', 'like', "%{$busca}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Detalhes de um utilizador
     * GET /api/admin/users/{id}
     */
    public function show($id)
    {
        $user = User::with(['servicos', 'pedidos', 'avaliacoes'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilizador não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Atualizar utilizador
     * PUT /api/admin/users/{id}
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilizador não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'telefone' => 'sometimes|string|max:20',
            'endereco' => 'nullable|string',
            'tipo' => 'sometimes|in:cliente,prestador,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('nome')) $user->nome = $request->nome;
            if ($request->has('email')) $user->email = $request->email;
            if ($request->has('telefone')) $user->telefone = $request->telefone;
            if ($request->has('endereco')) $user->endereco = $request->endereco;
            if ($request->has('tipo')) $user->tipo = $request->tipo;

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Utilizador atualizado com sucesso',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar utilizador'
            ], 500);
        }
    }

    /**
     * Bloquear utilizador
     * POST /api/admin/users/{id}/block
     */
    public function block($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilizador não encontrado'
            ], 404);
        }

        $user->blocked_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Utilizador bloqueado com sucesso'
        ]);
    }

    /**
     * Desbloquear utilizador
     * POST /api/admin/users/{id}/unblock
     */
    public function unblock($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilizador não encontrado'
            ], 404);
        }

        $user->blocked_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Utilizador desbloqueado com sucesso'
        ]);
    }

    /**
     * Deletar utilizador (soft delete)
     * DELETE /api/admin/users/{id}
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilizador não encontrado'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilizador removido com sucesso'
        ]);
    }

    /**
     * Deletar utilizador permanentemente
     * DELETE /api/admin/users/{id}/force
     */
    public function forceDelete($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilizador não encontrado'
            ], 404);
        }

        $user->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Utilizador removido permanentemente'
        ]);
    }

    /**
     * Buscar utilizador por email
     * GET /api/admin/users/email/{email}
     */
    public function getByEmail($email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilizador não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }


    // ==========================================
    // 3. GESTÃO DE PRESTADORES
    // ==========================================

    /**
     * Listar prestadores (admin)
     * GET /api/admin/prestadores
     */
    public function prestadores(Request $request)
    {
        $query = User::where('tipo', 'prestador');

        // Filtro por verificação
        if ($request->has('verificado')) {
            $query->where('verificado', $request->verificado);
        }

        // Filtro por categoria
        if ($request->has('categoria')) {
            $query->whereHas('categorias', function($q) use ($request) {
                $q->where('categoria_id', $request->categoria);
            });
        }

        // Avaliação mínima
        if ($request->has('avaliacao_min')) {
            $query->where('media_avaliacao', '>=', $request->avaliacao_min);
        }

        $prestadores = $query->with('categorias')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Prestadores pendentes de verificação
     * GET /api/admin/prestadores/pendentes
     */
    public function prestadoresPendentes()
    {
        $prestadores = User::where('tipo', 'prestador')
            ->where('verificado', false)
            ->with('categorias')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $prestadores
        ]);
    }

    /**
     * Aprovar prestador
     * PUT /api/admin/prestadores/{id}/aprovar
     */
    public function aprovarPrestador($id)
    {
        $prestador = User::where('tipo', 'prestador')->find($id);

        if (!$prestador) {
            return response()->json([
                'success' => false,
                'error' => 'Prestador não encontrado'
            ], 404);
        }

        $prestador->verificado = true;
        $prestador->save();

        return response()->json([
            'success' => true,
            'message' => 'Prestador aprovado com sucesso'
        ]);
    }

    /**
     * Reprovar prestador
     * PUT /api/admin/prestadores/{id}/reprovar
     */
    public function reprovarPrestador($id)
    {
        $prestador = User::where('tipo', 'prestador')->find($id);

        if (!$prestador) {
            return response()->json([
                'success' => false,
                'error' => 'Prestador não encontrado'
            ], 404);
        }

        $prestador->verificado = false;
        $prestador->save();

        return response()->json([
            'success' => true,
            'message' => 'Prestador reprovado'
        ]);
    }


    // ==========================================
    // 4. GESTÃO DE CATEGORIAS
    // ==========================================

    /**
     * Listar categorias (admin)
     * GET /api/admin/categorias
     */
    public function categorias()
    {
        $categorias = Categoria::withCount('servicos')->get();

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    /**
     * Criar categoria
     * POST /api/admin/categorias
     */
    public function createCategoria(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255|unique:categorias',
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
            'cor' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $categoria = Categoria::create([
                'nome' => $request->nome,
                'slug' => \Illuminate\Support\Str::slug($request->nome),
                'descricao' => $request->descricao,
                'icone' => $request->icone ?? 'category',
                'cor' => $request->cor ?? 'primary',
                'ativo' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Categoria criada com sucesso',
                'data' => $categoria
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar categoria'
            ], 500);
        }
    }

    /**
     * Atualizar categoria
     * PUT /api/admin/categorias/{id}
     */
    public function updateCategoria(Request $request, $id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'error' => 'Categoria não encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255|unique:categorias,nome,' . $id,
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
            'cor' => 'nullable|string',
            'ativo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('nome')) {
                $categoria->nome = $request->nome;
                $categoria->slug = \Illuminate\Support\Str::slug($request->nome);
            }
            if ($request->has('descricao')) $categoria->descricao = $request->descricao;
            if ($request->has('icone')) $categoria->icone = $request->icone;
            if ($request->has('cor')) $categoria->cor = $request->cor;
            if ($request->has('ativo')) $categoria->ativo = $request->ativo;

            $categoria->save();

            return response()->json([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso',
                'data' => $categoria
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar categoria'
            ], 500);
        }
    }

    /**
     * Deletar categoria
     * DELETE /api/admin/categorias/{id}
     */
    public function deleteCategoria($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'error' => 'Categoria não encontrada'
            ], 404);
        }

        $categoria->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoria removida com sucesso'
        ]);
    }


    // ==========================================
    // 5. GESTÃO DE SERVIÇOS
    // ==========================================

    /**
     * Listar serviços (admin)
     * GET /api/admin/servicos
     */
    public function servicos(Request $request)
    {
        $query = Servico::with(['prestador', 'categoria']);

        if ($request->has('categoria')) {
            $query->where('categoria_id', $request->categoria);
        }

        if ($request->has('ativo')) {
            $query->where('ativo', $request->ativo);
        }

        $servicos = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $servicos
        ]);
    }

    /**
     * Criar serviço (admin)
     * POST /api/admin/servicos
     */
    public function createServico(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prestador_id' => 'required|exists:users,id',
            'categoria_id' => 'required|exists:categorias,id',
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'preco' => 'required|numeric|min:0',
            'duracao' => 'required|integer|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $servico = Servico::create($request->all());

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


    // ==========================================
    // 6. GESTÃO DE PEDIDOS
    // ==========================================

    /**
     * Listar pedidos (admin)
     * GET /api/admin/pedidos
     */
    public function pedidos(Request $request)
    {
        $query = Pedido::with(['cliente', 'prestador', 'servico']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $pedidos = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * Detalhes do pedido (admin)
     * GET /api/admin/pedidos/{id}
     */
    public function showPedido($id)
    {
        $pedido = Pedido::with(['cliente', 'prestador', 'servico', 'avaliacao'])
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pedido
        ]);
    }


    // ==========================================
    // 7. FINANCEIRO
    // ==========================================

    /**
     * Resumo financeiro
     * GET /api/admin/financeiro/resumo
     */
    public function resumoFinanceiro()
    {
        $saldoAtual = Transacao::sum('valor');
        $pendente = Transacao::where('status', 'pendente')->sum('valor');
        $processadoMes = Transacao::whereMonth('created_at', now()->month)
            ->where('status', 'concluido')
            ->sum('valor');
        $comissoes = Transacao::where('tipo', 'comissao')
            ->whereMonth('created_at', now()->month)
            ->sum('valor');

        return response()->json([
            'success' => true,
            'data' => [
                'saldo_atual' => $saldoAtual,
                'pendente' => $pendente,
                'processado_mes' => $processadoMes,
                'comissoes' => $comissoes,
            ]
        ]);
    }

    /**
     * Listar transações
     * GET /api/admin/financeiro/transacoes
     */
    public function transacoes(Request $request)
    {
        $query = Transacao::with('user');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transacoes = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transacoes
        ]);
    }


    // ==========================================
    // 8. RELATÓRIOS
    // ==========================================

    /**
     * Exportar relatório
     * GET /api/admin/export
     */
    public function export(Request $request)
    {
        $tipo = $request->query('tipo', 'usuarios');

        // TODO: Implementar exportação para Excel/CSV
        return response()->json([
            'success' => true,
            'message' => "Relatório de {$tipo} em processamento"
        ]);
    }

    /**
     * Relatório de serviços
     * GET /api/admin/relatorios/servicos
     */
    public function relatorioServicos(Request $request)
    {
        $periodo = $request->query('periodo', 'mes');

        $query = Pedido::query();

        switch ($periodo) {
            case 'hoje':
                $query->whereDate('created_at', today());
                break;
            case 'semana':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'mes':
                $query->whereMonth('created_at', now()->month);
                break;
            case 'ano':
                $query->whereYear('created_at', now()->year);
                break;
        }

        $total = $query->count();
        $receita = $query->sum('valor');

        return response()->json([
            'success' => true,
            'data' => [
                'periodo' => $periodo,
                'total_servicos' => $total,
                'receita_total' => $receita,
                'servicos_por_status' => [
                    'pendente' => (clone $query)->where('status', 'pendente')->count(),
                    'aceito' => (clone $query)->where('status', 'aceito')->count(),
                    'em_andamento' => (clone $query)->where('status', 'em_andamento')->count(),
                    'concluido' => (clone $query)->where('status', 'concluido')->count(),
                    'cancelado' => (clone $query)->where('status', 'cancelado')->count(),
                ]
            ]
        ]);
    }

    /**
     * Relatório de prestadores
     * GET /api/admin/relatorios/prestadores
     */
    public function relatorioPrestadores()
    {
        $total = User::where('tipo', 'prestador')->count();
        $verificados = User::where('tipo', 'prestador')->where('verificado', true)->count();
        $naoVerificados = $total - $verificados;

        $mediaAvaliacao = Avaliacao::whereHas('prestador')->avg('nota') ?? 0;

        $topPrestadores = User::where('tipo', 'prestador')
            ->orderBy('media_avaliacao', 'desc')
            ->limit(10)
            ->get(['id', 'nome', 'media_avaliacao', 'total_avaliacoes']);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'verificados' => $verificados,
                'nao_verificados' => $naoVerificados,
                'media_avaliacao_geral' => round($mediaAvaliacao, 1),
                'top_prestadores' => $topPrestadores,
            ]
        ]);
    }

    /**
     * Relatório financeiro
     * GET /api/admin/relatorios/financeiro
     */
    public function relatorioFinanceiro(Request $request)
    {
        $periodo = $request->query('periodo', 'mes');

        $query = Transacao::query();

        switch ($periodo) {
            case 'hoje':
                $query->whereDate('created_at', today());
                break;
            case 'semana':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'mes':
                $query->whereMonth('created_at', now()->month);
                break;
            case 'ano':
                $query->whereYear('created_at', now()->year);
                break;
        }

        $entradas = (clone $query)->where('tipo', 'entrada')->sum('valor');
        $saidas = (clone $query)->where('tipo', 'saida')->sum('valor');
        $saldo = $entradas - $saidas;

        return response()->json([
            'success' => true,
            'data' => [
                'periodo' => $periodo,
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => $saldo,
                'comissoes' => (clone $query)->where('tipo', 'comissao')->sum('valor'),
            ]
        ]);
    }


    // ==========================================
    // 9. CONFIGURAÇÕES DO SISTEMA
    // ==========================================

    /**
     * Obter configurações do sistema
     * GET /api/admin/configuracoes
     */
    public function configuracoes()
    {
        // TODO: Implementar configurações do sistema
        return response()->json([
            'success' => true,
            'data' => [
                'nome' => 'EstouAqui',
                'email' => 'geral@estouaqui.co.mz',
                'telefone' => '+258 84 123 4567',
                'endereco' => 'Maputo, Moçambique',
                'comissao_padrao' => 10,
                'tipo_comissao' => 'porcentagem',
            ]
        ]);
    }

    /**
     * Atualizar configurações
     * PUT /api/admin/configuracoes
     */
    public function updateConfiguracoes(Request $request)
    {
        // TODO: Implementar atualização de configurações
        return response()->json([
            'success' => true,
            'message' => 'Configurações atualizadas'
        ]);
    }


    // ==========================================
    // 10. LOGS DO SISTEMA
    // ==========================================

    /**
     * Listar logs do sistema
     * GET /api/admin/logs
     */
    public function logs(Request $request)
    {
        // TODO: Implementar listagem de logs
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }


    // ==========================================
    // 11. ESTATÍSTICAS GERAIS
    // ==========================================

    /**
     * Estatísticas gerais (admin)
     * GET /api/admin/stats
     */
    public function stats()
    {
        $totalUsers = User::count();
        $totalClientes = User::where('tipo', 'cliente')->count();
        $totalPrestadores = User::where('tipo', 'prestador')->count();
        $totalServicos = Servico::count();
        $totalPedidos = Pedido::count();
        $totalAvaliacoes = Avaliacao::count();
        $receitaTotal = Transacao::where('tipo', 'entrada')->sum('valor');

        return response()->json([
            'success' => true,
            'data' => [
                'total_usuarios' => $totalUsers,
                'total_clientes' => $totalClientes,
                'total_prestadores' => $totalPrestadores,
                'total_servicos' => $totalServicos,
                'total_pedidos' => $totalPedidos,
                'total_avaliacoes' => $totalAvaliacoes,
                'receita_total' => $receitaTotal,
            ]
        ]);
    }
}
