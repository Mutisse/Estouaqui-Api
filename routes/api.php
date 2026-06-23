<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificacaoController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\FavoritoController;
use App\Http\Controllers\Api\PrestadorController;
use App\Http\Controllers\Api\PromocaoController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AvaliacaoController;
use App\Http\Controllers\Api\PrestadorDashboardController;
use App\Http\Controllers\Api\PrestadorPedidoController;
use App\Http\Controllers\Api\PrestadorServicoController;
use App\Http\Controllers\Api\PrestadorAgendaController;
use App\Http\Controllers\Api\PrestadorGanhoController;
use App\Http\Controllers\Api\PrestadorPerfilController;
use App\Http\Controllers\Api\PrestadorNotificacaoController;
use App\Http\Controllers\Api\PrestadorChatController;
use App\Http\Controllers\Api\ConfiguracaoController;
use App\Http\Controllers\Api\PrestadorPreferenciaController;
use App\Http\Controllers\Api\PrestadorRelatorioController;
use App\Http\Controllers\Api\ClienteSuporteController;
use App\Http\Controllers\Api\PrestadorSuporteController;
use App\Http\Controllers\Api\ClientePropostaController;

// ==================== CONTROLLERS ADMIN ====================
use App\Http\Controllers\Admin\AdminAvaliacaoController;
use App\Http\Controllers\Admin\AdminBackupController;
use App\Http\Controllers\Admin\AdminCategoriaController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEstatisticaController;
use App\Http\Controllers\Admin\AdminFinanceiroController;
use App\Http\Controllers\Admin\AdminLogController;
use App\Http\Controllers\Admin\AdminMonitoramentoController;
use App\Http\Controllers\Admin\AdminNotificacaoController;
use App\Http\Controllers\Admin\AdminPedidoController;
use App\Http\Controllers\Admin\AdminPerformanceController;
use App\Http\Controllers\Admin\AdminPrestadorController;
use App\Http\Controllers\Admin\AdminPromocaoController;
use App\Http\Controllers\Admin\AdminRelatorioController;
use App\Http\Controllers\Admin\AdminServicoController;
use App\Http\Controllers\Admin\AdminSuporteController;
use App\Http\Controllers\Admin\AdminUtilizadorController;
use App\Http\Controllers\Admin\AdminPerfilController;
use App\Http\Controllers\Admin\AdminConfiguracoesController;
use App\Http\Controllers\Admin\AdminAgendaController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// ROTAS PÚBLICAS (não requerem autenticação)
// ==========================================

// Auth
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password/{token}', [AuthController::class, 'resetPassword']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Categorias (públicas)
Route::get('/categorias', [CategoriaController::class, 'index']);
Route::get('/categorias/{id}', [CategoriaController::class, 'show']);

// ==========================================
// ROTAS DE CONFIGURAÇÕES PÚBLICAS
// ==========================================
Route::prefix('/configuracoes')->group(function () {
    Route::get('/prestador', [ConfiguracaoController::class, 'getPrestadorConfig']);
    Route::get('/cliente', [ConfiguracaoController::class, 'getClienteConfig']);
    Route::get('/sistema', [ConfiguracaoController::class, 'getSistemaConfig']);
    Route::get('/grupo/{grupo}', [ConfiguracaoController::class, 'getByGroup']);
    Route::get('/raio-options', [ConfiguracaoController::class, 'getRaioOptions']);
    Route::get('/ordenacao-options', [ConfiguracaoController::class, 'getOrdenacaoOptions']);
    Route::get('/{chave}', [ConfiguracaoController::class, 'show']);
});

// ==========================================
// PRESTADORES PÚBLICOS
// ==========================================
Route::prefix('/prestadores')->group(function () {
    Route::get('/proximos', [PrestadorController::class, 'proximos']);
    Route::get('/destaque', [PrestadorController::class, 'destaque']);
    Route::get('/top', [PrestadorController::class, 'top']);
    Route::get('/disponiveis', [PrestadorController::class, 'disponiveis']);
    Route::get('/categoria/{categoriaId}', [PrestadorController::class, 'porCategoria']);
    Route::get('/', [PrestadorController::class, 'index']);
    Route::get('/{id}', [PrestadorController::class, 'show']);
});

// Promoções públicas
Route::get('/promocoes', [PromocaoController::class, 'index']);

// ==========================================
// ROTAS PROTEGIDAS (requerem token)
// ==========================================
Route::middleware(['auth:sanctum'])->group(function () {

    // ========== AUTH ==========
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/verify', [AuthController::class, 'verify']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/user', [AuthController::class, 'updateProfile']);

    // ========== CONFIGURAÇÕES PROTEGIDAS ==========
    Route::prefix('/configuracoes')->group(function () {
        Route::put('/{chave}', [ConfiguracaoController::class, 'update']);
    });

    // ==========================================
    // ========== ROTAS DO CLIENTE ==========
    // ==========================================
    Route::prefix('/cliente')->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // ===== PEDIDOS =====
        Route::get('/pedidos', [PedidoController::class, 'index']);
        Route::get('/pedidos/{id}', [PedidoController::class, 'show']);
        Route::post('/pedidos', [PedidoController::class, 'store']);
        Route::patch('/pedidos/{id}/status', [PedidoController::class, 'updateStatus']);
        Route::delete('/pedidos/{id}', [PedidoController::class, 'destroy']);

        // 🔥 NOVA ROTA: Verificar disponibilidade antes de criar pedido
        Route::get('/pedidos/verificar-disponibilidade', [PedidoController::class, 'verificarDisponibilidade']);

        // ===== PROPOSTAS =====
        Route::get('/propostas', [ClientePropostaController::class, 'index']);
        Route::get('/propostas/estatisticas', [ClientePropostaController::class, 'estatisticas']);
        Route::get('/propostas/pendentes/count', [ClientePropostaController::class, 'pendentesCount']);
        Route::get('/propostas/check/{pedidoId}', [ClientePropostaController::class, 'checkPropostaAceita']);
        Route::get('/propostas/{id}', [ClientePropostaController::class, 'show']);
        Route::post('/propostas/{id}/aceitar', [ClientePropostaController::class, 'aceitar']);
        Route::post('/propostas/{id}/recusar', [ClientePropostaController::class, 'recusar']);

        // ===== NOTIFICAÇÕES =====
        Route::get('/notificacoes', [NotificacaoController::class, 'index']);
        Route::get('/notificacoes/nao-lidas', [NotificacaoController::class, 'naoLidas']);
        Route::patch('/notificacoes/{id}/ler', [NotificacaoController::class, 'marcarComoLida']);
        Route::post('/notificacoes/marcar-todas-lidas', [NotificacaoController::class, 'marcarTodasComoLidas']);
        Route::delete('/notificacoes/{id}', [NotificacaoController::class, 'destroy']);

        // ===== PERFIL =====
        Route::get('/perfil', [PerfilController::class, 'show']);
        Route::put('/perfil', [PerfilController::class, 'update']);
        Route::post('/perfil/foto', [PerfilController::class, 'uploadFoto']);
        Route::delete('/perfil/foto', [PerfilController::class, 'removerFoto']);
        Route::get('/perfil/dashboard', [PerfilController::class, 'dashboard']);

        // ===== ENDEREÇOS =====
        Route::get('/enderecos', [PerfilController::class, 'getEnderecos']);
        Route::post('/enderecos', [PerfilController::class, 'storeEndereco']);
        Route::put('/enderecos/{id}', [PerfilController::class, 'updateEndereco']);
        Route::put('/enderecos/{id}/principal', [PerfilController::class, 'setEnderecoPrincipal']);
        Route::delete('/enderecos/{id}', [PerfilController::class, 'deleteEndereco']);

        // ===== CONFIGURAÇÕES PERFIL =====
        Route::get('/configuracoes', [PerfilController::class, 'getConfiguracoes']);
        Route::put('/configuracoes', [PerfilController::class, 'updateConfiguracoes']);

        // ===== CHAT =====
        Route::get('/chat/chats', [ChatController::class, 'chats']);
        Route::get('/chat/mensagens/{prestadorId}', [ChatController::class, 'mensagens']);
        Route::get('/chat/mensagens/{prestadorId}/novas', [ChatController::class, 'novasMensagens']);
        Route::post('/chat/enviar/{prestadorId}', [ChatController::class, 'enviar']);
        Route::post('/chat/marcar-lidas/{prestadorId}', [ChatController::class, 'marcarLidas']);
        Route::get('/chat/nao-lidas', [ChatController::class, 'naoLidas']);

        // ===== SUPORTE =====
        Route::prefix('/suporte')->group(function () {
            Route::get('/tickets', [ClienteSuporteController::class, 'index']);
            Route::get('/tickets/{id}', [ClienteSuporteController::class, 'show']);
            Route::get('/tickets/{id}/mensagens', [ClienteSuporteController::class, 'mensagens']);
            Route::post('/tickets', [ClienteSuporteController::class, 'store']);
            Route::post('/tickets/{id}/mensagens', [ClienteSuporteController::class, 'enviarMensagem']);
            Route::put('/tickets/{id}/fechar', [ClienteSuporteController::class, 'fechar']);
        });
    });

    // ========== PEDIDOS (rotas avulsas) ==========
    Route::get('/pedidos/{id}/historico', [PedidoController::class, 'historico']);
    Route::get('/pedidos/{id}/cliente', [PedidoController::class, 'getClienteDoPedido']);

    // ========== FAVORITOS ==========
    Route::get('/favoritos', [FavoritoController::class, 'index']);
    Route::post('/favoritos', [FavoritoController::class, 'store']);
    Route::delete('/favoritos/{prestadorId}', [FavoritoController::class, 'destroy']);
    Route::get('/favoritos/check/{prestadorId}', [FavoritoController::class, 'check']);
    Route::delete('/favoritos/limpar-todos', [FavoritoController::class, 'limparTodos']);
    Route::get('/favoritos/prestadores', [FavoritoController::class, 'prestadoresFavoritos']);
    Route::get('/favoritos/count', [FavoritoController::class, 'count']);

    // ========== PROMOÇÕES ==========
    Route::get('/promocoes/{id}', [PromocaoController::class, 'show']);
    Route::post('/promocoes/validar', [PromocaoController::class, 'validar']);
    Route::post('/promocoes/aplicar', [PromocaoController::class, 'aplicarCupom']);

    // ========== UPLOAD ==========
    Route::post('/upload/foto', [AuthController::class, 'uploadFoto']);

    // ========== AVALIAÇÕES ==========
    Route::get('/pedidos/{pedidoId}/avaliacao', [AvaliacaoController::class, 'getAvaliacaoByPedido']);
    Route::post('/pedidos/{pedidoId}/avaliar', [AvaliacaoController::class, 'store']);
    Route::get('/prestadores/{prestadorId}/avaliacoes', [AvaliacaoController::class, 'getAvaliacoesByPrestador']);
    Route::get('/clientes/{clienteId}/avaliacoes', [AvaliacaoController::class, 'getAvaliacoesByCliente']);
    Route::put('/avaliacoes/{id}', [AvaliacaoController::class, 'update']);
    Route::delete('/avaliacoes/{id}', [AvaliacaoController::class, 'destroy']);

    // ==========================================
    // ========== ROTAS DO PRESTADOR ==========
    // ==========================================
    Route::prefix('/prestador')->group(function () {

        // ===== DASHBOARD =====
        Route::get('/dashboard/stats', [PrestadorDashboardController::class, 'stats']);
        Route::get('/dashboard/ganhos', [PrestadorDashboardController::class, 'ganhos']);
        Route::get('/dashboard/proximos-servicos', [PrestadorDashboardController::class, 'proximosServicos']);
        Route::get('/dashboard/avaliacoes-recentes', [PrestadorDashboardController::class, 'avaliacoesRecentes']);

        // ===== PEDIDOS =====
        Route::get('/solicitacoes', [PrestadorPedidoController::class, 'index']);
        Route::get('/solicitacoes/{id}', [PrestadorPedidoController::class, 'show']);
        Route::put('/solicitacoes/{id}/aceitar', [PrestadorPedidoController::class, 'aceitar']);
        Route::put('/solicitacoes/{id}/recusar', [PrestadorPedidoController::class, 'recusar']);
        Route::put('/solicitacoes/{id}/iniciar', [PrestadorPedidoController::class, 'iniciarServico']);
        Route::put('/solicitacoes/{id}/concluir', [PrestadorPedidoController::class, 'concluirServico']);
        Route::put('/solicitacoes/{id}/cancelar', [PrestadorPedidoController::class, 'cancelar']);

        // ===== PEDIDOS DISPONÍVEIS E PROPOSTAS =====
        Route::get('/pedidos-disponiveis', [PrestadorPedidoController::class, 'pedidosDisponiveis']);
        Route::post('/propostas', [PrestadorPedidoController::class, 'enviarProposta']);
        Route::get('/propostas', [PrestadorPedidoController::class, 'minhasPropostas']);
        Route::get('/propostas/check/{pedidoId}', [PrestadorPedidoController::class, 'verificarProposta']);
        Route::get('/pedidos', [PrestadorPedidoController::class, 'historico']);
        Route::get('/avaliacoes', [PrestadorPedidoController::class, 'avaliacoes']);

        // ===== SERVIÇOS =====
        Route::get('/servicos', [PrestadorServicoController::class, 'index']);
        Route::get('/servicos/{id}', [PrestadorServicoController::class, 'show']);
        Route::post('/servicos', [PrestadorServicoController::class, 'store']);
        Route::put('/servicos/{id}', [PrestadorServicoController::class, 'update']);
        Route::delete('/servicos/{id}', [PrestadorServicoController::class, 'destroy']);

        // ===== AGENDA - COMPLETO =====
        Route::get('/agenda', [PrestadorAgendaController::class, 'index']);
        Route::get('/agenda/{data}', [PrestadorAgendaController::class, 'show']);
        Route::put('/agenda', [PrestadorAgendaController::class, 'update']);
        Route::post('/agenda/bloquear', [PrestadorAgendaController::class, 'bloquearHorario']);
        Route::delete('/agenda/bloquear/{id}', [PrestadorAgendaController::class, 'desbloquearHorario']);

        // 🔥 NOVA ROTA: Verificar disponibilidade em lote
        Route::post('/agenda/verificar-disponibilidade', [PrestadorAgendaController::class, 'verificarDisponibilidadeEmLote']);

        // ===== INTERVALOS RECORRENTES =====
        Route::get('/agenda/intervalos', [PrestadorAgendaController::class, 'intervalos']);
        Route::post('/agenda/intervalos', [PrestadorAgendaController::class, 'storeIntervalo']);
        Route::put('/agenda/intervalos/{id}', [PrestadorAgendaController::class, 'updateIntervalo']);
        Route::delete('/agenda/intervalos/{id}', [PrestadorAgendaController::class, 'destroyIntervalo']);

        // ===== GANHOS =====
        Route::get('/ganhos', [PrestadorGanhoController::class, 'index']);
        Route::get('/ganhos/extrato', [PrestadorGanhoController::class, 'extrato']);
        Route::post('/saques', [PrestadorGanhoController::class, 'solicitarSaque']);
        Route::get('/saques/historico', [PrestadorGanhoController::class, 'historicoSaques']);

        // ===== PERFIL PRESTADOR =====
        Route::get('/perfil', [PrestadorPerfilController::class, 'show']);
        Route::put('/perfil', [PrestadorPerfilController::class, 'update']);
        Route::post('/perfil/foto', [PrestadorPerfilController::class, 'uploadFoto']);
        Route::delete('/perfil/foto', [PrestadorPerfilController::class, 'removerFoto']);
        Route::get('/perfil/stats', [PrestadorPerfilController::class, 'stats']);
        Route::get('/perfil/categorias', [PrestadorPerfilController::class, 'getCategorias']);
        Route::post('/perfil/categorias', [PrestadorPerfilController::class, 'addCategoria']);
        Route::delete('/perfil/categorias/{id}', [PrestadorPerfilController::class, 'removeCategoria']);
        Route::get('/perfil/disponibilidade', [PrestadorPerfilController::class, 'getDisponibilidade']);
        Route::put('/perfil/disponibilidade', [PrestadorPerfilController::class, 'updateDisponibilidade']);
        Route::put('/perfil/disponibilidade', [PrestadorController::class, 'updateDisponibilidade']);

        // ===== PORTFOLIO =====
        Route::get('/portfolio', [PrestadorPerfilController::class, 'getPortfolio']);
        Route::post('/portfolio', [PrestadorPerfilController::class, 'addPortfolio']);
        Route::put('/portfolio/{id}', [PrestadorPerfilController::class, 'updatePortfolio']);
        Route::delete('/portfolio/{id}', [PrestadorPerfilController::class, 'removePortfolio']);

        // ===== NOTIFICAÇÕES PRESTADOR =====
        Route::get('/notificacoes', [PrestadorNotificacaoController::class, 'index']);
        Route::get('/notificacoes/nao-lidas', [PrestadorNotificacaoController::class, 'naoLidas']);
        Route::put('/notificacoes/{id}/ler', [PrestadorNotificacaoController::class, 'marcarComoLida']);
        Route::put('/notificacoes/marcar-todas-lidas', [PrestadorNotificacaoController::class, 'marcarTodasComoLidas']);

        // ===== CHAT PRESTADOR =====
        Route::get('/chat/dados', [PrestadorChatController::class, 'dadosPrestador']);
        Route::get('/chat/conversas', [PrestadorChatController::class, 'conversas']);
        Route::get('/chat/mensagens/{chatId}', [PrestadorChatController::class, 'mensagens']);
        Route::post('/chat/enviar', [PrestadorChatController::class, 'enviarMensagem']);
        Route::put('/chat/marcar-lidas/{chatId}', [PrestadorChatController::class, 'marcarComoLidas']);

        // ===== PREFERÊNCIAS =====
        Route::get('/preferencias', [PrestadorPreferenciaController::class, 'index']);
        Route::put('/preferencias', [PrestadorPreferenciaController::class, 'update']);
        Route::delete('/preferencias/mpesa', [PrestadorPreferenciaController::class, 'removerMpesa']);
        Route::delete('/preferencias/conta', [PrestadorPreferenciaController::class, 'removerConta']);

        // ===== RELATÓRIO FINANCEIRO =====
        Route::get('/relatorio-financeiro', [PrestadorRelatorioController::class, 'index']);

        // ===== EXCLUIR CONTA =====
        Route::delete('/perfil/conta', [PrestadorPerfilController::class, 'deleteAccount']);

        // ===== SUPORTE =====
        Route::prefix('/suporte')->group(function () {
            Route::get('/tickets', [PrestadorSuporteController::class, 'index']);
            Route::get('/tickets/{id}', [PrestadorSuporteController::class, 'show']);
            Route::get('/tickets/{id}/mensagens', [PrestadorSuporteController::class, 'mensagens']);
            Route::post('/tickets', [PrestadorSuporteController::class, 'store']);
            Route::post('/tickets/{id}/mensagens', [PrestadorSuporteController::class, 'enviarMensagem']);
            Route::put('/tickets/{id}/fechar', [PrestadorSuporteController::class, 'fechar']);
        });
    });

    // ========== 🔥 ROTA DE VERIFICAÇÃO DE DISPONIBILIDADE DO PRESTADOR ==========
    // (Fora dos grupos para ser acessível a todos autenticados)
    Route::get('/prestadores/{id}/disponibilidade', [PrestadorController::class, 'verificarDisponibilidade']);
    Route::get('/agenda/horarios', [PrestadorAgendaController::class, 'horarios']);
});

// ==========================================
// ROTAS ADMIN (requerem token e role admin/root)
// ==========================================
Route::middleware(['auth:sanctum', 'admin'])->prefix('/admin')->group(function () {

    // ==================== ADMIN - DASHBOARD ====================
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard/estatisticas', [AdminDashboardController::class, 'estatisticas']);
    Route::get('/dashboard/atividade', [AdminDashboardController::class, 'atividade']);
    Route::get('/dashboard/ultimos-utilizadores', [AdminDashboardController::class, 'ultimosUtilizadores']);
    Route::get('/dashboard/servicos-recentes', [AdminDashboardController::class, 'servicosRecentes']);
    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);

    // ==================== ADMIN - UTILIZADORES ====================
    Route::get('/utilizadores', [AdminUtilizadorController::class, 'index']);
    Route::get('/utilizadores/{id}', [AdminUtilizadorController::class, 'show']);
    Route::post('/utilizadores', [AdminUtilizadorController::class, 'store']);
    Route::put('/utilizadores/{id}', [AdminUtilizadorController::class, 'update']);
    Route::delete('/utilizadores/{id}', [AdminUtilizadorController::class, 'destroy']);
    Route::put('/utilizadores/{id}/aprovar', [AdminUtilizadorController::class, 'aprovar']);
    Route::put('/utilizadores/{id}/reprovar', [AdminUtilizadorController::class, 'reprovar']);
    Route::put('/utilizadores/{id}/ativar', [AdminUtilizadorController::class, 'ativar']);
    Route::put('/utilizadores/{id}/desativar', [AdminUtilizadorController::class, 'desativar']);
    Route::put('/utilizadores/{id}/bloquear', [AdminUtilizadorController::class, 'bloquear']);
    Route::put('/utilizadores/{id}/desbloquear', [AdminUtilizadorController::class, 'desbloquear']);
    Route::put('/utilizadores/{id}/verificar', [AdminUtilizadorController::class, 'verificar']);

    // ==================== ADMIN - PRESTADORES ====================
    Route::get('/prestadores/profissoes', [AdminPrestadorController::class, 'profissoes']);
    Route::get('/prestadores', [AdminPrestadorController::class, 'index']);
    Route::get('/prestadores/{id}', [AdminPrestadorController::class, 'show']);
    Route::post('/prestadores', [AdminPrestadorController::class, 'store']);
    Route::put('/prestadores/{id}', [AdminPrestadorController::class, 'update']);
    Route::delete('/prestadores/{id}', [AdminPrestadorController::class, 'destroy']);
    Route::put('/prestadores/{id}/verificar', [AdminPrestadorController::class, 'verificar']);
    Route::put('/prestadores/{id}/ativar', [AdminPrestadorController::class, 'ativar']);
    Route::put('/prestadores/{id}/desativar', [AdminPrestadorController::class, 'desativar']);

    // ==================== ADMIN - CATEGORIAS ====================
    Route::get('/categorias', [AdminCategoriaController::class, 'index']);
    Route::get('/categorias/{id}', [AdminCategoriaController::class, 'show']);
    Route::post('/categorias', [AdminCategoriaController::class, 'store']);
    Route::put('/categorias/{id}', [AdminCategoriaController::class, 'update']);
    Route::delete('/categorias/{id}', [AdminCategoriaController::class, 'destroy']);
    Route::put('/categorias/{id}/status', [AdminCategoriaController::class, 'alternarStatus']);
    Route::post('/categorias/reordenar', [AdminCategoriaController::class, 'reordenar']);

    // ==================== ADMIN - PEDIDOS ====================
    Route::get('/pedidos', [AdminPedidoController::class, 'index']);
    Route::get('/pedidos/{id}', [AdminPedidoController::class, 'show']);
    Route::put('/pedidos/{id}/status', [AdminPedidoController::class, 'atualizarStatus']);
    Route::post('/pedidos/{id}/cancelar', [AdminPedidoController::class, 'cancelar']);
    Route::delete('/pedidos/{id}', [AdminPedidoController::class, 'destroy']);
    Route::get('/pedidos/{id}/propostas', [AdminPedidoController::class, 'propostas']);
    Route::post('/propostas/{id}/aceitar', [AdminPedidoController::class, 'aceitarProposta']);
    Route::post('/propostas/{id}/recusar', [AdminPedidoController::class, 'recusarProposta']);
    Route::get('/pedidos/estatisticas', [AdminPedidoController::class, 'estatisticas']);

    // ==================== ADMIN - SERVIÇOS ====================
    Route::get('/servicos/estatisticas', [AdminServicoController::class, 'estatisticas']);
    Route::get('/servicos', [AdminServicoController::class, 'index']);
    Route::post('/servicos', [AdminServicoController::class, 'store']);
    Route::get('/servicos/{id}', [AdminServicoController::class, 'show']);
    Route::put('/servicos/{id}', [AdminServicoController::class, 'update']);
    Route::delete('/servicos/{id}', [AdminServicoController::class, 'destroy']);
    Route::put('/servicos/{id}/status', [AdminServicoController::class, 'alternarStatus']);

    // ==================== ADMIN - AVALIAÇÕES ====================
    Route::get('/avaliacoes/estatisticas', [AdminAvaliacaoController::class, 'estatisticas']);
    Route::get('/avaliacoes', [AdminAvaliacaoController::class, 'index']);
    Route::get('/avaliacoes/{id}', [AdminAvaliacaoController::class, 'show']);
    Route::put('/avaliacoes/{id}/aprovar', [AdminAvaliacaoController::class, 'aprovar']);
    Route::put('/avaliacoes/{id}/rejeitar', [AdminAvaliacaoController::class, 'rejeitar']);
    Route::delete('/avaliacoes/{id}', [AdminAvaliacaoController::class, 'destroy']);

    // ==================== ADMIN - FINANCEIRO ====================
    Route::get('/financeiro', [AdminFinanceiroController::class, 'index']);
    Route::get('/financeiro/resumo', [AdminFinanceiroController::class, 'resumo']);
    Route::get('/financeiro/transacoes', [AdminFinanceiroController::class, 'transacoes']);
    Route::post('/financeiro/transacoes', [AdminFinanceiroController::class, 'registrarTransacao']);
    Route::put('/financeiro/transacoes/{id}/status', [AdminFinanceiroController::class, 'atualizarStatusTransacao']);
    Route::get('/financeiro/ganhos-por-mes', [AdminFinanceiroController::class, 'ganhosPorMes']);
    Route::get('/financeiro/saques/pendentes', [AdminFinanceiroController::class, 'saquesPendentes']);
    Route::get('/financeiro/saques/ultimos', [AdminFinanceiroController::class, 'ultimosSaques']);
    Route::post('/financeiro/saques/{id}/aprovar', [AdminFinanceiroController::class, 'aprovarSaque']);
    Route::post('/financeiro/saques/{id}/concluir', [AdminFinanceiroController::class, 'concluirSaque']);
    Route::post('/financeiro/saques/{id}/recusar', [AdminFinanceiroController::class, 'recusarSaque']);
    Route::get('/financeiro/exportar', [AdminFinanceiroController::class, 'exportar']);

    // ==================== ADMIN - PROMOÇÕES ====================
    Route::get('/promocoes/estatisticas', [AdminPromocaoController::class, 'estatisticas']);
    Route::get('/promocoes', [AdminPromocaoController::class, 'index']);
    Route::get('/promocoes/{id}', [AdminPromocaoController::class, 'show']);
    Route::post('/promocoes', [AdminPromocaoController::class, 'store']);
    Route::put('/promocoes/{id}', [AdminPromocaoController::class, 'update']);
    Route::delete('/promocoes/{id}', [AdminPromocaoController::class, 'destroy']);
    Route::put('/promocoes/{id}/status', [AdminPromocaoController::class, 'alternarStatus']);

    // ==================== ADMIN - NOTIFICAÇÕES ====================
    Route::get('/notificacoes/templates', [AdminNotificacaoController::class, 'templates']);
    Route::get('/notificacoes/estatisticas', [AdminNotificacaoController::class, 'estatisticas']);
    Route::get('/notificacoes', [AdminNotificacaoController::class, 'index']);
    Route::get('/notificacoes/{id}', [AdminNotificacaoController::class, 'show']);
    Route::post('/notificacoes/enviar', [AdminNotificacaoController::class, 'enviar']);
    Route::delete('/notificacoes/{id}', [AdminNotificacaoController::class, 'destroy']);
    Route::put('/notificacoes/{id}/marcar-lida', [AdminNotificacaoController::class, 'marcarComoLida']);
    Route::put('/notificacoes/marcar-todas-lidas', [AdminNotificacaoController::class, 'marcarTodasComoLidas']);

    // ==================== ADMIN - BACKUPS ====================
    Route::get('/backups/estatisticas', [AdminBackupController::class, 'estatisticas']);
    Route::get('/backups', [AdminBackupController::class, 'index']);
    Route::post('/backups', [AdminBackupController::class, 'store']);
    Route::delete('/backups/{id}', [AdminBackupController::class, 'destroy']);
    Route::get('/backups/{id}/download', [AdminBackupController::class, 'download']);
    Route::post('/backups/{id}/restaurar', [AdminBackupController::class, 'restaurar']);
    Route::get('/backups/configuracoes', [AdminBackupController::class, 'configuracoes']);
    Route::put('/backups/configuracoes', [AdminBackupController::class, 'atualizarConfiguracoes']);
    Route::delete('/backups/limpar', [AdminBackupController::class, 'limpar']);

    // ==================== ADMIN - LOGS ====================
    Route::get('/logs', [AdminLogController::class, 'index']);
    Route::get('/logs/estatisticas', [AdminLogController::class, 'estatisticas']);
    Route::delete('/logs/limpar', [AdminLogController::class, 'limpar']);
    Route::get('/logs/exportar', [AdminLogController::class, 'exportar']);

    // ==================== ADMIN - PERFORMANCE ====================
    Route::get('/performance', [AdminPerformanceController::class, 'index']);
    Route::get('/performance/realtime', [AdminPerformanceController::class, 'realtime']);
    Route::get('/performance/historico', [AdminPerformanceController::class, 'historico']);

    // ==================== ADMIN - MONITORAMENTO ====================
    Route::get('/monitoramento/estatisticas', [AdminMonitoramentoController::class, 'estatisticas']);
    Route::get('/monitoramento/status', [AdminMonitoramentoController::class, 'status']);
    Route::get('/monitoramento/alertas', [AdminMonitoramentoController::class, 'alertas']);
    Route::put('/monitoramento/alertas/{id}/ler', [AdminMonitoramentoController::class, 'marcarAlertaLido']);
    Route::put('/monitoramento/alertas/marcar-todos-lidos', [AdminMonitoramentoController::class, 'marcarTodosAlertasLidos']);
    Route::get('/monitoramento/logs', [AdminMonitoramentoController::class, 'logs']);
    Route::delete('/monitoramento/logs/limpar', [AdminMonitoramentoController::class, 'limparLogs']);
    Route::get('/monitoramento/metricas', [AdminMonitoramentoController::class, 'metricas']);
    Route::get('/monitoramento/testar/{servico}', [AdminMonitoramentoController::class, 'testarServico']);

    // ==================== ADMIN - SUPORTE ====================
    Route::get('/suporte/estatisticas', [AdminSuporteController::class, 'estatisticas']);
    Route::get('/suporte/tickets', [AdminSuporteController::class, 'index']);
    Route::get('/suporte/tickets/{id}', [AdminSuporteController::class, 'show']);
    Route::put('/suporte/tickets/{id}/status', [AdminSuporteController::class, 'atualizarStatus']);
    Route::put('/suporte/tickets/{id}/prioridade', [AdminSuporteController::class, 'atualizarPrioridade']);
    Route::delete('/suporte/tickets/{id}', [AdminSuporteController::class, 'destroy']);
    Route::get('/suporte/tickets/{id}/mensagens', [AdminSuporteController::class, 'mensagens']);
    Route::post('/suporte/tickets/{id}/mensagens', [AdminSuporteController::class, 'enviarMensagem']);
    Route::put('/suporte/tickets/{id}/atribuir', [AdminSuporteController::class, 'atribuirAdmin']);

    // ==================== ADMIN - CHAT ====================
    Route::get('/suporte/chat/tickets', [AdminSuporteController::class, 'chatTickets']);
    Route::get('/suporte/chat/tickets/{id}/mensagens', [AdminSuporteController::class, 'chatMensagens']);
    Route::post('/suporte/chat/tickets/{id}/enviar', [AdminSuporteController::class, 'enviarChatMensagem']);
    Route::get('/suporte/chat/tickets/{id}/novas', [AdminSuporteController::class, 'novasMensagensChat']);
    Route::put('/suporte/chat/tickets/{id}/marcar-lidas', [AdminSuporteController::class, 'marcarChatLidas']);

    // ==================== ADMIN - RELATÓRIOS ====================
    Route::get('/relatorios/pedidos', [AdminRelatorioController::class, 'pedidos']);
    Route::get('/relatorios/financeiro', [AdminRelatorioController::class, 'financeiro']);
    Route::get('/relatorios/prestadores', [AdminRelatorioController::class, 'prestadores']);
    Route::get('/relatorios/clientes', [AdminRelatorioController::class, 'clientes']);
    Route::get('/relatorios/{tipo}/exportar', [AdminRelatorioController::class, 'exportar']);

    // ==================== ADMIN - ESTATÍSTICAS ====================
    Route::get('/estatisticas', [AdminEstatisticaController::class, 'index']);
    Route::get('/estatisticas/{periodo}', [AdminEstatisticaController::class, 'porPeriodo']);
    Route::get('/estatisticas/graficos', [AdminEstatisticaController::class, 'graficos']);

    // ==================== ADMIN - PERFIL ====================
    Route::get('/perfil', [AdminPerfilController::class, 'getPerfil']);
    Route::put('/perfil', [AdminPerfilController::class, 'atualizarPerfil']);
    Route::put('/perfil/senha', [AdminPerfilController::class, 'alterarSenha']);
    Route::post('/perfil/foto', [AdminPerfilController::class, 'atualizarFoto']);
    Route::get('/atividades', [AdminPerfilController::class, 'getAtividades']);

    // ==================== ADMIN - CONFIGURAÇÕES ====================
    Route::get('/configuracoes/todas', [AdminConfiguracoesController::class, 'getTodasConfiguracoes']);
    Route::get('/configuracoes/gerais', [AdminConfiguracoesController::class, 'getConfiguracoesGerais']);
    Route::put('/configuracoes/gerais', [AdminConfiguracoesController::class, 'atualizarConfiguracoesGerais']);
    Route::get('/configuracoes/prestador', [AdminConfiguracoesController::class, 'getConfiguracoesPrestador']);
    Route::put('/configuracoes/prestador', [AdminConfiguracoesController::class, 'atualizarConfiguracoesPrestador']);
    Route::get('/configuracoes/pagamento', [AdminConfiguracoesController::class, 'getConfiguracoesPagamento']);
    Route::put('/configuracoes/pagamento', [AdminConfiguracoesController::class, 'atualizarConfiguracoesPagamento']);
    Route::get('/configuracoes/opcoes/raios', [AdminConfiguracoesController::class, 'getOpcoesRaios']);
    Route::get('/configuracoes/opcoes/dias-semana', [AdminConfiguracoesController::class, 'getOpcoesDiasSemana']);
    Route::get('/configuracoes/opcoes/documentos', [AdminConfiguracoesController::class, 'getOpcoesDocumentos']);
    Route::get('/configuracoes/opcoes/fuso-horario', [AdminConfiguracoesController::class, 'getOpcoesFusoHorario']);
    Route::get('/configuracoes/opcoes/moeda', [AdminConfiguracoesController::class, 'getOpcoesMoeda']);
    Route::get('/configuracoes/opcoes/criptografia', [AdminConfiguracoesController::class, 'getOpcoesCriptografia']);
    Route::get('/configuracoes/opcoes/modulos', [AdminConfiguracoesController::class, 'getOpcoesModulos']);
    Route::put('/configuracoes/horarios-agenda', [ConfiguracaoController::class, 'atualizarHorariosAgenda']);
    // ==================== ADMIN - PERMISSÕES ====================
    Route::get('/permissoes', [AdminConfiguracoesController::class, 'getPermissoes']);
    Route::get('/roles', [AdminConfiguracoesController::class, 'getRoles']);
    Route::put('/permissoes/{id}', [AdminConfiguracoesController::class, 'atualizarPermissao']);
    Route::put('/roles/{id}', [AdminConfiguracoesController::class, 'atualizarRole']);

    // ==================== ADMIN - AGENDA ====================
    Route::get('/agenda/prestador/{prestadorId}', [AdminAgendaController::class, 'showPrestadorAgenda']);
    Route::get('/agenda/prestador/{prestadorId}/estatisticas', [AdminAgendaController::class, 'estatisticas']);
    Route::post('/agenda/prestador/{prestadorId}/bloquear', [AdminAgendaController::class, 'bloquearHorarioAdmin']);
    Route::delete('/agenda/{id}', [AdminAgendaController::class, 'desbloquearHorarioAdmin']);
});

// ==========================================
// ROTAS DE TESTE
// ==========================================
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'message' => 'API EstouAqui está funcionando!'
    ]);
});
