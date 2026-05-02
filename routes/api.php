<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\PrestadorController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ServicoController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\AvaliacaoController;
use App\Http\Controllers\Api\FavoritoController;
use App\Http\Controllers\Api\TransacaoController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\PromocaoController;
use App\Http\Controllers\Api\AuxiliarController;
use App\Http\Controllers\Api\LocalizacaoController;
use App\Http\Controllers\Api\ServicoTipoController;
use App\Http\Controllers\Api\RaioOpcaoController;
use App\Http\Controllers\Api\SystemMonitorController;
use App\Http\Controllers\Api\PropostaController;

/*
|--------------------------------------------------------------------------
| API ROTAS - ESTOUAQUI (COMPLETO)
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| API ROTAS - ESTOUAQUI (COMPLETO)
|--------------------------------------------------------------------------
*/




// ==========================================
// ROTA DE TESTE
// ==========================================
Route::get('/test', function () {
    return response()->json(['message' => 'API OK', 'version' => '1.0.0']);
});

// ==========================================
// 1. ROTAS PÚBLICAS - AUTENTICAÇÃO
// ==========================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password/{token}', [AuthController::class, 'resetPassword']);

// ==========================================
// 2. ROTAS PÚBLICAS - VERIFICAÇÕES
// ==========================================
Route::get('/check-email', [UsuarioController::class, 'checkEmail']);
Route::get('/check-phone', [UsuarioController::class, 'checkPhone']);
Route::post('/upload-temp', [UsuarioController::class, 'uploadTemp']);

// ==========================================
// 3. ROTAS PÚBLICAS - REGISTRO
// ==========================================
Route::post('/register/cliente', [ClienteController::class, 'register']);
Route::post('/register/prestador', [PrestadorController::class, 'register']);

// ==========================================
// 4. ROTAS PÚBLICAS - ESTATÍSTICAS
// ==========================================
Route::get('/stats', [UsuarioController::class, 'publicStats']);

// ==========================================
// 5. ROTAS PÚBLICAS - PRESTADORES
// ==========================================
Route::prefix('prestadores')->group(function () {
    Route::get('/', [PrestadorController::class, 'index']);
    Route::get('/destaque', [PrestadorController::class, 'destaque']);
    Route::get('/top', [PrestadorController::class, 'topAvaliados']);
    Route::get('/proximos', [PrestadorController::class, 'proximos']);
    Route::get('/categorias', [PrestadorController::class, 'categorias']);
    Route::get('/{id}', [PrestadorController::class, 'show']);
    Route::get('/{id}/avaliacoes', [PrestadorController::class, 'avaliacoes']);
});

// ==========================================
// 6. ROTAS PÚBLICAS - PROMOÇÕES
// ==========================================
Route::prefix('promocoes')->group(function () {
    Route::get('/', [PromocaoController::class, 'index']);
    Route::get('/ativas', [PromocaoController::class, 'ativas']);
    Route::get('/{id}', [PromocaoController::class, 'show']);
    Route::get('/codigo/{codigo}', [PromocaoController::class, 'showByCodigo']);
    Route::post('/validar', [PromocaoController::class, 'validarCupom']);
});

// ==========================================
// 7. ROTAS PÚBLICAS - AUXILIAR
// ==========================================
Route::prefix('auxiliar')->group(function () {
    Route::get('/dias-semana', [AuxiliarController::class, 'diasSemana']);
    Route::get('/meses', [AuxiliarController::class, 'meses']);
    Route::get('/dias-options', [AuxiliarController::class, 'diasOptions']);
    Route::get('/horarios-padrao', [AuxiliarController::class, 'horariosPadrao']);
    Route::get('/horarios-options', [AuxiliarController::class, 'horariosOptions']);
});

// ==========================================
// 8. ROTAS PÚBLICAS - DADOS AUXILIARES
// ==========================================
Route::prefix('public')->group(function () {
    Route::get('/servico-tipos', [ServicoTipoController::class, 'index']);
    Route::get('/servico-tipos/options', [ServicoTipoController::class, 'options']);
    Route::get('/raio-opcoes', [RaioOpcaoController::class, 'index']);
    Route::get('/raio-opcoes/options', [RaioOpcaoController::class, 'options']);
    Route::get('/categorias', [CategoriaController::class, 'publicas']);
    Route::get('/promocoes/ativas', [PromocaoController::class, 'publicAtivas']);
});

// ==========================================
// ROTAS PÚBLICAS - MONITORAMENTO
// ==========================================
Route::prefix('system')->group(function () {
    Route::get('/health', [SystemMonitorController::class, 'health']);
});

// ==========================================
// ==========================================
// ROTAS PROTEGIDAS (auth:sanctum)
// ==========================================
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // ==========================================
    // 10. AUTH (PROTEGIDO)
    // ==========================================
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/verify-token', [AuthController::class, 'verifyToken']);

    // ==========================================
    // 11. USUÁRIO (comum a todos)
    // ==========================================
    Route::get('/me', [UsuarioController::class, 'me']);
    Route::put('/me', [UsuarioController::class, 'update']);
    Route::delete('/me', [UsuarioController::class, 'destroy']);
    Route::post('/avatar', [UsuarioController::class, 'updateAvatar']);
    Route::delete('/avatar', [UsuarioController::class, 'removeAvatar']);
    Route::put('/password', [UsuarioController::class, 'changePassword']);
    Route::get('/dashboard', [UsuarioController::class, 'dashboard']);
    Route::get('/activities/recent', [UsuarioController::class, 'recentActivities']);
    Route::get('/activities', [UsuarioController::class, 'activitiesHistory']);

    // ==========================================
    // 12. NOTIFICAÇÕES
    // ==========================================
    // ==========================================
    // 12. NOTIFICAÇÕES - CORRIGIDO
    // ==========================================
    Route::prefix('notifications')->group(function () {
        // ✅ CORRETO: sem duplicar o prefixo
        Route::get('/', [UsuarioController::class, 'notifications']);
        Route::get('/recent', [UsuarioController::class, 'recentNotifications']);
        Route::get('/unread-count', [UsuarioController::class, 'unreadCount']);
        Route::put('/{id}/read', [UsuarioController::class, 'markNotificationRead']);
        Route::put('/read-all', [UsuarioController::class, 'markAllNotificationsRead']);
        Route::post('/create-indexes', [UsuarioController::class, 'createIndexes']);
    });
    // ==========================================
    // 13. PREFERÊNCIAS
    // ==========================================
    Route::prefix('preferences')->group(function () {
        Route::get('/', [UsuarioController::class, 'preferences']);
        Route::put('/', [UsuarioController::class, 'updatePreferences']);
    });

    // ==========================================
    // 14. LOCALIZAÇÃO
    // ==========================================
    Route::prefix('localizacao')->group(function () {
        Route::get('/', [LocalizacaoController::class, 'show']);
        Route::post('/', [LocalizacaoController::class, 'update']);
        Route::get('/prestadores-proximos', [LocalizacaoController::class, 'prestadoresProximos']);
    });

    // ==========================================
    // 15. ENDEREÇOS
    // ==========================================
    Route::prefix('addresses')->group(function () {
        Route::get('/', [UsuarioController::class, 'addresses']);
        Route::post('/', [UsuarioController::class, 'createAddress']);
        Route::get('/{id}', [UsuarioController::class, 'getAddress']);
        Route::put('/{id}', [UsuarioController::class, 'updateAddress']);
        Route::delete('/{id}', [UsuarioController::class, 'deleteAddress']);
        Route::put('/{id}/primary', [UsuarioController::class, 'setPrimaryAddress']);
    });

    // ==========================================
    // 16. SERVIÇO TIPOS (PROTEGIDO - ADMIN)
    // ==========================================
    Route::prefix('servico-tipos')->middleware('role:admin')->group(function () {
        Route::get('/', [ServicoTipoController::class, 'index']);
        Route::get('/options', [ServicoTipoController::class, 'options']);
    });

    // ==========================================
    // 17. RAIO OPÇÕES (PROTEGIDO - ADMIN)
    // ==========================================
    Route::prefix('raio-opcoes')->middleware('role:admin')->group(function () {
        Route::get('/', [RaioOpcaoController::class, 'index']);
        Route::get('/options', [RaioOpcaoController::class, 'options']);
    });

    // ==========================================
    // 18. CHAT
    // ==========================================
    Route::prefix('chat')->group(function () {
        Route::get('/messages/{prestadorId}', [ChatController::class, 'getMessages']);
        Route::post('/messages', [ChatController::class, 'sendMessage']);
        Route::get('/messages/{prestadorId}/latest', [ChatController::class, 'getLatestMessages']);
        Route::put('/messages/{prestadorId}/read', [ChatController::class, 'markAsRead']);
        Route::get('/conversations', [ChatController::class, 'getConversations']);
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);
    });

    // ==========================================
    // 19. ROTAS DO CLIENTE (role:cliente)
    // ==========================================
    Route::middleware('role:cliente')->prefix('cliente')->group(function () {

        // Pedidos
        Route::prefix('pedidos')->group(function () {
            Route::post('/', [PedidoController::class, 'createPedido']);
            Route::get('/meus-pedidos', [ClienteController::class, 'meusPedidos']);
            Route::get('/{id}', [PedidoController::class, 'show']);
            Route::put('/{id}/cancelar', [PedidoController::class, 'cancelarPedido']);
        });

        // Avaliações
        Route::prefix('avaliacoes')->group(function () {
            Route::get('/', [ClienteController::class, 'avaliacoes']);
            Route::post('/', [ClienteController::class, 'createAvaliacao']);
            Route::put('/{id}', [ClienteController::class, 'updateAvaliacao']);
            Route::delete('/{id}', [ClienteController::class, 'deleteAvaliacao']);
        });

        Route::get('/pedidos/{pedidoId}/avaliacao', [ClienteController::class, 'checkAvaliacao']);

        // Favoritos
        Route::prefix('favoritos')->group(function () {
            Route::get('/', [FavoritoController::class, 'index']);
            Route::post('/{prestadorId}', [FavoritoController::class, 'store']);
            Route::delete('/{prestadorId}', [FavoritoController::class, 'destroy']);
            Route::get('/{prestadorId}/check', [FavoritoController::class, 'check']);
        });

        // Propostas do cliente
        Route::get('/propostas', [PropostaController::class, 'minhasPropostasCliente']);
        Route::put('/propostas/{id}/aceitar', [PropostaController::class, 'aceitar']);
        Route::put('/propostas/{id}/recusar', [PropostaController::class, 'recusar']);
    });

    // ==========================================
    // 20. ROTAS DO PRESTADOR (role:prestador)
    // ==========================================
    Route::middleware('role:prestador')->prefix('prestador')->group(function () {

        // Serviços
        Route::prefix('servicos')->group(function () {
            Route::get('/', [PrestadorController::class, 'servicos']);
            Route::post('/', [PrestadorController::class, 'createServico']);
            Route::put('/{id}', [PrestadorController::class, 'updateServico']);
            Route::delete('/{id}', [PrestadorController::class, 'deleteServico']);
            Route::put('/{id}/toggle', [PrestadorController::class, 'toggleServico']);
        });

        // Agenda
        Route::prefix('agenda')->group(function () {
            Route::get('/', [PrestadorController::class, 'agenda']);
            Route::post('/bloquear', [PrestadorController::class, 'bloquearHorario']);
            Route::delete('/{id}', [PrestadorController::class, 'desbloquearHorario']);
        });

        // Solicitações
        Route::prefix('solicitacoes')->group(function () {
            Route::get('/', [PrestadorController::class, 'solicitacoes']);
            Route::put('/{id}/aceitar', [PrestadorController::class, 'aceitarSolicitacao']);
            Route::put('/{id}/recusar', [PrestadorController::class, 'recusarSolicitacao']);
        });

        // Categorias do prestador
        Route::prefix('categorias')->group(function () {
            Route::get('/', [PrestadorController::class, 'minhasCategorias']);
            Route::post('/{categoriaId}', [PrestadorController::class, 'addCategoria']);
            Route::delete('/{categoriaId}', [PrestadorController::class, 'removeCategoria']);
        });

        // Saques
        Route::prefix('saques')->group(function () {
            Route::get('/', [PrestadorController::class, 'saques']);
            Route::post('/', [PrestadorController::class, 'solicitarSaque']);
            Route::get('/historico', [PrestadorController::class, 'historicoSaques']);
        });

        // Intervalos
        Route::prefix('intervalos')->group(function () {
            Route::get('/', [PrestadorController::class, 'intervalos']);
            Route::post('/', [PrestadorController::class, 'criarIntervalo']);
            Route::put('/{id}', [PrestadorController::class, 'atualizarIntervalo']);
            Route::delete('/{id}', [PrestadorController::class, 'deletarIntervalo']);
        });

        // Outros
        Route::get('/ganhos', [PrestadorController::class, 'ganhos']);
        Route::get('/disponibilidade', [PrestadorController::class, 'getDisponibilidade']);
        Route::put('/disponibilidade', [PrestadorController::class, 'updateDisponibilidade']);
        Route::get('/proximos-servicos', [PrestadorController::class, 'proximosServicos']);
        Route::get('/avaliacoes/recentes', [PrestadorController::class, 'avaliacoesRecentes']);
        Route::get('/stats', [PrestadorController::class, 'stats']);
        Route::post('/clear-cache', [PrestadorController::class, 'clearCache']);

        // Propostas do prestador
        Route::post('/propostas', [PropostaController::class, 'store']);
        Route::get('/propostas', [PropostaController::class, 'minhasPropostasPrestador']);
        Route::get('/pedidos-disponiveis', [PropostaController::class, 'pedidosDisponiveis']);
    });

    // ==========================================
    // 21. ROTAS DO ADMIN (role:admin) - COMPLETAS E SEM CONFLITOS
    // ==========================================
    Route::middleware('role:admin')->prefix('admin')->group(function () {

        // ==========================================
        // DASHBOARD E ESTATÍSTICAS
        // ==========================================
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/atividade', [AdminController::class, 'atividade']);
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/logs', [AdminController::class, 'logs']);
        Route::get('/configuracoes', [AdminController::class, 'configuracoes']);
        Route::put('/configuracoes', [AdminController::class, 'updateConfiguracoes']);

        // ==========================================
        // NOTIFICAÇÕES DO ADMIN
        // ==========================================
        Route::prefix('notifications')->group(function () {
            Route::get('/', [AdminController::class, 'notifications']);
            Route::put('/read-all', [AdminController::class, 'markAllNotificationsRead']);
            Route::put('/{id}/read', [AdminController::class, 'markNotificationRead']);
        });

        // ==========================================
        // GESTÃO DE UTILIZADORES
        // ==========================================
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'index']);
            Route::get('/export', [AdminController::class, 'export']);
            Route::get('/email/{email}', [AdminController::class, 'getByEmail']);

            Route::prefix('{id}')->group(function () {
                Route::get('/', [AdminController::class, 'show']);
                Route::put('/', [AdminController::class, 'update']);
                Route::delete('/', [AdminController::class, 'destroy']);
                Route::delete('/force', [AdminController::class, 'forceDelete']);

                Route::prefix('status')->group(function () {
                    Route::post('/block', [AdminController::class, 'block']);
                    Route::post('/unblock', [AdminController::class, 'unblock']);
                });
            });
        });

        // ==========================================
        // GESTÃO DE PRESTADORES
        // ==========================================
        Route::prefix('prestadores')->group(function () {
            Route::get('/', [AdminController::class, 'prestadores']);
            Route::get('/pendentes', [AdminController::class, 'prestadoresPendentes']);
            Route::put('/{id}/aprovar', [AdminController::class, 'aprovarPrestador']);
            Route::put('/{id}/reprovar', [AdminController::class, 'reprovarPrestador']);
        });

        // ==========================================
        // GESTÃO DE CATEGORIAS
        // ==========================================
        Route::prefix('categorias')->group(function () {
            Route::get('/', [CategoriaController::class, 'index']);
            Route::post('/', [CategoriaController::class, 'store']);
            Route::get('/{id}', [CategoriaController::class, 'show']);
            Route::put('/{id}', [CategoriaController::class, 'update']);
            Route::delete('/{id}', [CategoriaController::class, 'destroy']);
            Route::post('/upload-imagem', [CategoriaController::class, 'uploadImagem']);
            Route::delete('/{id}/imagem', [CategoriaController::class, 'removerImagem']);
        });

        // ==========================================
        // GESTÃO DE SERVIÇOS
        // ==========================================
        Route::prefix('servicos')->group(function () {
            Route::get('/', [ServicoController::class, 'index']);
            Route::post('/', [ServicoController::class, 'store']);
            Route::get('/{id}', [ServicoController::class, 'show']);
            Route::put('/{id}', [ServicoController::class, 'update']);
            Route::delete('/{id}', [ServicoController::class, 'destroy']);
        });

        // ==========================================
        // GESTÃO DE PEDIDOS - USANDO AdminController
        // ==========================================
        Route::prefix('pedidos')->group(function () {
            Route::get('/', [AdminController::class, 'pedidos']);
            Route::get('/{id}', [AdminController::class, 'showPedido']);
            Route::put('/{id}/status', [AdminController::class, 'updatePedidoStatus']);
            Route::delete('/{id}/cancel', [AdminController::class, 'cancelPedido']);
        });

        // ==========================================
        // GESTÃO DE AVALIAÇÕES
        // ==========================================
        Route::prefix('avaliacoes')->group(function () {
            Route::get('/', [AvaliacaoController::class, 'index']);
            Route::get('/{id}', [AvaliacaoController::class, 'show']);
            Route::delete('/{id}', [AvaliacaoController::class, 'destroy']);
        });

        // ==========================================
        // FINANCEIRO - USANDO AdminController
        // ==========================================
        Route::prefix('financeiro')->group(function () {
            Route::get('/resumo', [AdminController::class, 'resumoFinanceiro']);
            Route::get('/transacoes', [AdminController::class, 'transacoes']);
            Route::get('/transacoes/{id}', [AdminController::class, 'showTransacao']);
            Route::post('/transacoes', [AdminController::class, 'storeTransacao']);
            Route::put('/transacoes/{id}/status', [AdminController::class, 'updateTransacaoStatus']);
        });

        // ==========================================
        // RELATÓRIOS - USANDO AdminController
        // ==========================================
        Route::prefix('relatorios')->group(function () {
            Route::get('/usuarios', [AdminController::class, 'relatorioUsuarios']);
            Route::get('/servicos', [AdminController::class, 'relatorioServicos']);
            Route::get('/financeiro', [AdminController::class, 'relatorioFinanceiro']);
            Route::get('/prestadores', [AdminController::class, 'relatorioPrestadores']);
        });

        // ==========================================
        // GESTÃO DE PROMOÇÕES
        // ==========================================
        Route::prefix('promocoes')->group(function () {
            Route::post('/', [PromocaoController::class, 'store']);
            Route::put('/{id}', [PromocaoController::class, 'update']);
            Route::delete('/{id}', [PromocaoController::class, 'destroy']);
        });
    });

    // ==========================================
    // 22. MONITORAMENTO (PROTEGIDO - ADMIN)
    // ==========================================
    Route::middleware('role:admin')->prefix('system')->group(function () {
        Route::get('/health', [SystemMonitorController::class, 'health']);
        Route::get('/metrics', [SystemMonitorController::class, 'metrics']);
        Route::get('/performance', [SystemMonitorController::class, 'performance']);
        Route::get('/cache-stats', [SystemMonitorController::class, 'cacheStats']);
        Route::get('/database-stats', [SystemMonitorController::class, 'databaseStats']);
        Route::get('/queue-stats', [SystemMonitorController::class, 'queueStats']);
        Route::get('/logs/recent', [SystemMonitorController::class, 'recentLogs']);
        Route::get('/alerts', [SystemMonitorController::class, 'alerts']);
        Route::put('/alerts/{id}/resolve', [SystemMonitorController::class, 'resolveAlert']);
        Route::get('/history', [SystemMonitorController::class, 'history']);
        Route::get('/business-metrics', [SystemMonitorController::class, 'businessMetrics']);
        Route::get('/export', [SystemMonitorController::class, 'export']);
        Route::post('/save-daily-metrics', [SystemMonitorController::class, 'saveDailyMetrics']);
        // NOVAS ROTAS
        Route::get('/security/realtime', [SystemMonitorController::class, 'securityRealtime']);
        Route::post('/security/block-ip', [SystemMonitorController::class, 'blockIp']);
        Route::get('/external/check', [SystemMonitorController::class, 'checkExternalServicesRealtime']);
        Route::get('/performance/endpoints', [SystemMonitorController::class, 'slowEndpoints']);
        Route::get('/performance/status-codes', [SystemMonitorController::class, 'statusCodesAnalysis']);
        Route::get('/business/advanced', [SystemMonitorController::class, 'advancedBusinessMetrics']);
        Route::get('/executive-report', [SystemMonitorController::class, 'executiveReport']);
        // ADICIONAR ESTA ROTA FALTANTE
        Route::get('/infrastructure-metrics', [SystemMonitorController::class, 'infrastructureMetrics']);
    });
});
