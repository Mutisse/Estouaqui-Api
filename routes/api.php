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

/*
|--------------------------------------------------------------------------
| API ROTAS - ESTOUAQUI
|--------------------------------------------------------------------------
| Estrutura organizada por responsabilidades:
| - AuthController: autenticação
| - UsuarioController: funcionalidades comuns
| - ClienteController: específicas do cliente
| - PrestadorController: específicas do prestador
| - AdminController: administração
| - CategoriaController: gestão de categorias
| - ServicoController: gestão de serviços
| - PedidoController: gestão de pedidos
| - AvaliacaoController: gestão de avaliações
| - FavoritoController: gestão de favoritos
| - TransacaoController: gestão financeira
*/

// ==========================================
// ✅ ROTA DE LOGIN GLOBAL (OBRIGATÓRIA)
// ==========================================
Route::post('/login', [AuthController::class, 'login'])->name('login');

// ==========================================
// 1. ROTAS PÚBLICAS (sem autenticação)
// ==========================================

// 1.1 Verificações de disponibilidade
Route::get('/check-email', [UsuarioController::class, 'checkEmail'])->name('check.email');
Route::get('/check-phone', [UsuarioController::class, 'checkPhone'])->name('check.phone');

// 1.2 Upload temporário de foto
Route::post('/upload-temp', [UsuarioController::class, 'uploadTemp'])->name('upload-temp');

// 1.3 Autenticação
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('reset-password');
});

// 1.4 Registro por perfil
Route::prefix('register')->name('register.')->group(function () {
    Route::post('/cliente', [ClienteController::class, 'register'])->name('cliente');
    Route::post('/prestador', [PrestadorController::class, 'register'])->name('prestador');
});

// 1.5 Listagem pública de prestadores
Route::prefix('prestadores')->name('prestadores.')->group(function () {
    Route::get('/', [PrestadorController::class, 'index'])->name('index');
    Route::get('/destaque', [PrestadorController::class, 'destaque'])->name('destaque');
    Route::get('/top', [PrestadorController::class, 'topAvaliados'])->name('top');
    Route::get('/proximos', [PrestadorController::class, 'proximos'])->name('proximos');
    Route::get('/categorias', [CategoriaController::class, 'publicas'])->name('categorias');
    Route::get('/{id}', [PrestadorController::class, 'show'])->name('show');
    Route::get('/{id}/avaliacoes', [PrestadorController::class, 'avaliacoes'])->name('avaliacoes');
});

// 1.6 Estatísticas públicas
Route::get('/stats', [UsuarioController::class, 'publicStats'])->name('public-stats');

// ==========================================
// ROTAS AUXILIARES (dados de configuração)
// ==========================================
Route::prefix('auxiliar')->name('auxiliar.')->group(function () {
    Route::get('/dias-semana', [AuxiliarController::class, 'diasSemana']);
    Route::get('/meses', [AuxiliarController::class, 'meses']);
    Route::get('/dias-options', [AuxiliarController::class, 'diasOptions']);
    Route::get('/horarios-padrao', [AuxiliarController::class, 'horariosPadrao']);
    Route::get('/horarios-options', [AuxiliarController::class, 'horariosOptions']);
});

// ==========================================
// ⭐⭐⭐ ROTAS PÚBLICAS DE PROMOÇÕES ⭐⭐⭐
// ==========================================
Route::prefix('promocoes')->name('promocoes.')->group(function () {
    Route::get('/', [PromocaoController::class, 'index']);
    Route::get('/ativas', [PromocaoController::class, 'ativas']);
    Route::get('/{id}', [PromocaoController::class, 'show']);
    Route::get('/codigo/{codigo}', [PromocaoController::class, 'showByCodigo']);
    Route::post('/validar', [PromocaoController::class, 'validarCupom']);
});

// ==========================================
// 2. ROTAS PROTEGIDAS (requerem autenticação)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // ==========================================
    // 2.1 AUTENTICAÇÃO (protegidas)
    // ==========================================
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/verify-token', [AuthController::class, 'verifyToken'])->name('verify-token');
    });

    // ==========================================
    // 2.2 FUNCIONALIDADES COMUNS (UsuarioController)
    // ==========================================
    Route::get('/me', [UsuarioController::class, 'me'])->name('me');
    Route::put('/me', [UsuarioController::class, 'update'])->name('update');
    Route::delete('/me', [UsuarioController::class, 'destroy'])->name('destroy');
    Route::post('/avatar', [UsuarioController::class, 'updateAvatar'])->name('avatar.update');
    Route::delete('/avatar', [UsuarioController::class, 'removeAvatar'])->name('avatar.remove');
    Route::put('/password', [UsuarioController::class, 'changePassword'])->name('password.change');
    Route::get('/dashboard', [UsuarioController::class, 'dashboard'])->name('dashboard');
    Route::get('/activities/recent', [UsuarioController::class, 'recentActivities'])->name('activities.recent');
    Route::get('/activities', [UsuarioController::class, 'activitiesHistory'])->name('activities.history');

    // Notificações
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [UsuarioController::class, 'notifications'])->name('index');
        Route::put('/read-all', [UsuarioController::class, 'markAllNotificationsRead'])->name('read-all');
        Route::put('/{id}/read', [UsuarioController::class, 'markNotificationRead'])->name('read');
    });

    // Localização
    Route::prefix('localizacao')->name('localizacao.')->group(function () {
        Route::get('/', [LocalizacaoController::class, 'show']);
        Route::post('/', [LocalizacaoController::class, 'update']);
        Route::get('/prestadores-proximos', [LocalizacaoController::class, 'prestadoresProximos']);
    });

    // Preferências
    Route::prefix('preferences')->name('preferences.')->group(function () {
        Route::get('/', [UsuarioController::class, 'preferences'])->name('show');
        Route::put('/', [UsuarioController::class, 'updatePreferences'])->name('update');
    });

    // Endereços
    Route::prefix('addresses')->name('addresses.')->group(function () {
        Route::get('/', [UsuarioController::class, 'addresses'])->name('index');
        Route::post('/', [UsuarioController::class, 'createAddress'])->name('create');
        Route::get('/{id}', [UsuarioController::class, 'getAddress'])->name('show');
        Route::put('/{id}', [UsuarioController::class, 'updateAddress'])->name('update');
        Route::delete('/{id}', [UsuarioController::class, 'deleteAddress'])->name('delete');
        Route::put('/{id}/primary', [UsuarioController::class, 'setPrimaryAddress'])->name('primary');
    });

    // Tipos de serviço
    Route::prefix('servico-tipos')->name('servico-tipos.')->group(function () {
        Route::get('/', [ServicoTipoController::class, 'index']);
        Route::get('/options', [ServicoTipoController::class, 'options']);
    });

    // Opções de raio
    Route::prefix('raio-opcoes')->name('raio-opcoes.')->group(function () {
        Route::get('/', [RaioOpcaoController::class, 'index']);
        Route::get('/options', [RaioOpcaoController::class, 'options']);
    });

    // ==========================================
    // 2.3 FUNCIONALIDADES DO CLIENTE
    // ==========================================
    Route::middleware('role:cliente')->prefix('cliente')->name('cliente.')->group(function () {
        Route::prefix('pedidos')->name('pedidos.')->group(function () {
            Route::get('/', [ClienteController::class, 'pedidos'])->name('index');
            Route::get('/{id}', [ClienteController::class, 'showPedido'])->name('show');
            Route::post('/', [ClienteController::class, 'createPedido'])->name('create');
            Route::put('/{id}/cancelar', [ClienteController::class, 'cancelarPedido'])->name('cancelar');
        });

        Route::prefix('avaliacoes')->name('avaliacoes.')->group(function () {
            Route::get('/', [ClienteController::class, 'avaliacoes'])->name('index');
            Route::post('/', [ClienteController::class, 'createAvaliacao'])->name('create');
            Route::put('/{id}', [ClienteController::class, 'updateAvaliacao'])->name('update');
            Route::delete('/{id}', [ClienteController::class, 'deleteAvaliacao'])->name('delete');
        });

        Route::get('/pedidos/{pedidoId}/avaliacao', [ClienteController::class, 'checkAvaliacao'])->name('check-avaliacao');

        Route::prefix('favoritos')->name('favoritos.')->group(function () {
            Route::get('/', [FavoritoController::class, 'index'])->name('index');
            Route::post('/{prestadorId}', [FavoritoController::class, 'store'])->name('add');
            Route::delete('/{prestadorId}', [FavoritoController::class, 'destroy'])->name('remove');
            Route::get('/{prestadorId}/check', [FavoritoController::class, 'check'])->name('check');
        });
    });

    // ==========================================
    // 2.4 FUNCIONALIDADES DO PRESTADOR
    // ==========================================
    Route::middleware('role:prestador')->prefix('prestador')->name('prestador.')->group(function () {
        Route::prefix('servicos')->name('servicos.')->group(function () {
            Route::get('/', [PrestadorController::class, 'servicos'])->name('index');
            Route::post('/', [PrestadorController::class, 'createServico'])->name('create');
            Route::put('/{id}', [PrestadorController::class, 'updateServico'])->name('update');
            Route::delete('/{id}', [PrestadorController::class, 'deleteServico'])->name('delete');
            Route::put('/{id}/toggle', [PrestadorController::class, 'toggleServico'])->name('toggle');
        });

        Route::prefix('agenda')->name('agenda.')->group(function () {
            Route::get('/', [PrestadorController::class, 'agenda'])->name('index');
            Route::post('/bloquear', [PrestadorController::class, 'bloquearHorario'])->name('bloquear');
            Route::delete('/{id}', [PrestadorController::class, 'desbloquearHorario'])->name('desbloquear');
        });

        Route::prefix('solicitacoes')->name('solicitacoes.')->group(function () {
            Route::get('/', [PrestadorController::class, 'solicitacoes'])->name('index');
            Route::put('/{id}/aceitar', [PrestadorController::class, 'aceitarSolicitacao'])->name('aceitar');
            Route::put('/{id}/recusar', [PrestadorController::class, 'recusarSolicitacao'])->name('recusar');
        });

        Route::prefix('categorias')->name('categorias.')->group(function () {
            Route::get('/', [PrestadorController::class, 'minhasCategorias'])->name('index');
            Route::post('/{categoriaId}', [PrestadorController::class, 'addCategoria'])->name('add');
            Route::delete('/{categoriaId}', [PrestadorController::class, 'removeCategoria'])->name('remove');
        });

        Route::prefix('saques')->name('saques.')->group(function () {
            Route::get('/', [PrestadorController::class, 'saques'])->name('index');
            Route::post('/', [PrestadorController::class, 'solicitarSaque'])->name('solicitar');
            Route::get('/historico', [PrestadorController::class, 'historicoSaques'])->name('historico');
        });

        Route::prefix('intervalos')->name('intervalos.')->group(function () {
            Route::get('/', [PrestadorController::class, 'intervalos'])->name('index');
            Route::post('/', [PrestadorController::class, 'criarIntervalo'])->name('create');
            Route::put('/{id}', [PrestadorController::class, 'atualizarIntervalo'])->name('update');
            Route::delete('/{id}', [PrestadorController::class, 'deletarIntervalo'])->name('delete');
        });

        Route::get('/ganhos', [PrestadorController::class, 'ganhos'])->name('ganhos');
        Route::get('/disponibilidade', [PrestadorController::class, 'getDisponibilidade']);
        Route::put('/disponibilidade', [PrestadorController::class, 'updateDisponibilidade']);
        Route::get('/proximos-servicos', [PrestadorController::class, 'proximosServicos']);
        Route::get('/avaliacoes/recentes', [PrestadorController::class, 'avaliacoesRecentes']);
        Route::get('/stats', [PrestadorController::class, 'stats'])->name('stats');
        Route::post('/clear-cache', [PrestadorController::class, 'clearCache']);
    });

    // ==========================================
    // 2.5 ADMINISTRAÇÃO (apenas para administradores)
    // ==========================================
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/atividade', [AdminController::class, 'atividade'])->name('atividade');
        Route::get('/stats', [AdminController::class, 'stats'])->name('stats');
        Route::get('/logs', [AdminController::class, 'logs'])->name('logs');
        Route::get('/configuracoes', [AdminController::class, 'configuracoes'])->name('configuracoes');
        Route::put('/configuracoes', [AdminController::class, 'updateConfiguracoes'])->name('configuracoes.update');

        // Gestão de usuários
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [AdminController::class, 'index'])->name('index');
            Route::get('/export', [AdminController::class, 'export'])->name('export');
            Route::get('/stats', [AdminController::class, 'stats'])->name('stats');
            Route::get('/email/{email}', [AdminController::class, 'getByEmail'])->name('by-email');

            Route::prefix('{id}')->name('user.')->group(function () {
                Route::get('/', [AdminController::class, 'show'])->name('show');
                Route::put('/', [AdminController::class, 'update'])->name('update');
                Route::delete('/', [AdminController::class, 'destroy'])->name('destroy');
                Route::delete('/force', [AdminController::class, 'forceDelete'])->name('force-delete');

                Route::prefix('status')->name('status.')->group(function () {
                    Route::post('/block', [AdminController::class, 'block'])->name('block');
                    Route::post('/unblock', [AdminController::class, 'unblock'])->name('unblock');
                });
            });
        });

        // Gestão de prestadores
        Route::prefix('prestadores')->name('prestadores.')->group(function () {
            Route::get('/', [AdminController::class, 'prestadores'])->name('index');
            Route::get('/pendentes', [AdminController::class, 'prestadoresPendentes'])->name('pendentes');
            Route::put('/{id}/aprovar', [AdminController::class, 'aprovarPrestador'])->name('aprovar');
            Route::put('/{id}/reprovar', [AdminController::class, 'reprovarPrestador'])->name('reprovar');
        });

        // Gestão de categorias
        Route::prefix('categorias')->name('categorias.')->group(function () {
            Route::get('/', [CategoriaController::class, 'index'])->name('index');
            Route::post('/', [CategoriaController::class, 'store'])->name('store');
            Route::get('/{id}', [CategoriaController::class, 'show'])->name('show');
            Route::put('/{id}', [CategoriaController::class, 'update'])->name('update');
            Route::delete('/{id}', [CategoriaController::class, 'destroy'])->name('destroy');
        });

        // Gestão de serviços
        Route::prefix('servicos')->name('servicos.')->group(function () {
            Route::get('/', [ServicoController::class, 'index'])->name('index');
            Route::post('/', [ServicoController::class, 'store'])->name('store');
            Route::get('/{id}', [ServicoController::class, 'show'])->name('show');
            Route::put('/{id}', [ServicoController::class, 'update'])->name('update');
            Route::delete('/{id}', [ServicoController::class, 'destroy'])->name('destroy');
        });

        // Gestão de pedidos
        Route::prefix('pedidos')->name('pedidos.')->group(function () {
            Route::get('/', [PedidoController::class, 'index'])->name('index');
            Route::get('/{id}', [PedidoController::class, 'show'])->name('show');
            Route::put('/{id}/status', [PedidoController::class, 'updateStatus'])->name('status');
            Route::delete('/{id}/cancel', [PedidoController::class, 'cancel'])->name('cancel');
        });

        // Gestão de avaliações
        Route::prefix('avaliacoes')->name('avaliacoes.')->group(function () {
            Route::get('/', [AvaliacaoController::class, 'index'])->name('index');
            Route::get('/{id}', [AvaliacaoController::class, 'show'])->name('show');
            Route::delete('/{id}', [AvaliacaoController::class, 'destroy'])->name('destroy');
        });

        // Financeiro
        Route::prefix('financeiro')->name('financeiro.')->group(function () {
            Route::get('/resumo', [TransacaoController::class, 'resumo'])->name('resumo');
            Route::get('/transacoes', [TransacaoController::class, 'index'])->name('transacoes');
            Route::get('/transacoes/{id}', [TransacaoController::class, 'show'])->name('transacao');
            Route::post('/transacoes', [TransacaoController::class, 'store'])->name('transacao.store');
            Route::put('/transacoes/{id}/status', [TransacaoController::class, 'updateStatus'])->name('transacao.status');
        });

        // Relatórios
        Route::prefix('relatorios')->name('relatorios.')->group(function () {
            Route::get('/usuarios', [AdminController::class, 'relatorioUsuarios'])->name('usuarios');
            Route::get('/servicos', [AdminController::class, 'relatorioServicos'])->name('servicos');
            Route::get('/financeiro', [AdminController::class, 'relatorioFinanceiro'])->name('financeiro');
            Route::get('/prestadores', [AdminController::class, 'relatorioPrestadores'])->name('prestadores');
        });
    });

    // ==========================================
    // 2.6 CHAT (protegido)
    // ==========================================
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/messages/{prestadorId}', [ChatController::class, 'getMessages'])->name('messages');
        Route::post('/messages', [ChatController::class, 'sendMessage'])->name('send');
        Route::get('/messages/{prestadorId}/latest', [ChatController::class, 'getLatestMessages'])->name('latest');
        Route::put('/messages/{prestadorId}/read', [ChatController::class, 'markAsRead'])->name('read');
        Route::get('/conversations', [ChatController::class, 'getConversations'])->name('conversations');
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount'])->name('unread-count');
    });

    // ==========================================
    // 2.7 ADMIN DE PROMOÇÕES (protegido - apenas admin)
    // ==========================================
    Route::middleware('role:admin')->prefix('admin/promocoes')->name('admin.promocoes.')->group(function () {
        Route::post('/', [PromocaoController::class, 'store'])->name('store');
        Route::put('/{id}', [PromocaoController::class, 'update'])->name('update');
        Route::delete('/{id}', [PromocaoController::class, 'destroy'])->name('destroy');
    });
});

// ==========================================
// 3. ROTA DE TESTE (apenas desenvolvimento)
// ==========================================
if (app()->environment('local')) {
    Route::get('/test', function () {
        return response()->json([
            'message' => 'API EstouAqui funcionando!',
            'version' => '1.0.0',
            'timestamp' => now()->toDateTimeString()
        ]);
    });
}
