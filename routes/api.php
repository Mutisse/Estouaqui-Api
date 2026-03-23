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

    // Perfil
    Route::get('/me', [UsuarioController::class, 'me'])->name('me');
    Route::put('/me', [UsuarioController::class, 'update'])->name('update');
    Route::delete('/me', [UsuarioController::class, 'destroy'])->name('destroy');

    // Avatar
    Route::post('/avatar', [UsuarioController::class, 'updateAvatar'])->name('avatar.update');
    Route::delete('/avatar', [UsuarioController::class, 'removeAvatar'])->name('avatar.remove');

    // Senha
    Route::put('/password', [UsuarioController::class, 'changePassword'])->name('password.change');

    // Dashboard
    Route::get('/dashboard', [UsuarioController::class, 'dashboard'])->name('dashboard');

    // Notificações
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [UsuarioController::class, 'notifications'])->name('index');
        Route::put('/read-all', [UsuarioController::class, 'markAllNotificationsRead'])->name('read-all');
        Route::put('/{id}/read', [UsuarioController::class, 'markNotificationRead'])->name('read');
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

    // ==========================================
    // 2.3 FUNCIONALIDADES DO CLIENTE
    // ==========================================
    Route::prefix('cliente')->name('cliente.')->group(function () {

        // Pedidos
        Route::prefix('pedidos')->name('pedidos.')->group(function () {
            Route::get('/', [ClienteController::class, 'pedidos'])->name('index');
            Route::get('/{id}', [ClienteController::class, 'showPedido'])->name('show');
            Route::post('/', [ClienteController::class, 'createPedido'])->name('create');
            Route::put('/{id}/cancelar', [ClienteController::class, 'cancelarPedido'])->name('cancelar');
        });

        // Avaliações
        Route::prefix('avaliacoes')->name('avaliacoes.')->group(function () {
            Route::get('/', [ClienteController::class, 'avaliacoes'])->name('index');
            Route::post('/', [ClienteController::class, 'createAvaliacao'])->name('create');
            Route::put('/{id}', [ClienteController::class, 'updateAvaliacao'])->name('update');
            Route::delete('/{id}', [ClienteController::class, 'deleteAvaliacao'])->name('delete');
        });

        // Verificar avaliação de pedido
        Route::get('/pedidos/{pedidoId}/avaliacao', [ClienteController::class, 'checkAvaliacao'])->name('check-avaliacao');

        // Favoritos
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
    Route::prefix('prestador')->name('prestador.')->group(function () {

        // Serviços
        Route::prefix('servicos')->name('servicos.')->group(function () {
            Route::get('/', [PrestadorController::class, 'servicos'])->name('index');
            Route::post('/', [PrestadorController::class, 'createServico'])->name('create');
            Route::put('/{id}', [PrestadorController::class, 'updateServico'])->name('update');
            Route::delete('/{id}', [PrestadorController::class, 'deleteServico'])->name('delete');
            Route::put('/{id}/toggle', [PrestadorController::class, 'toggleServico'])->name('toggle');
        });

        // Agenda
        Route::prefix('agenda')->name('agenda.')->group(function () {
            Route::get('/', [PrestadorController::class, 'agenda'])->name('index');
            Route::post('/bloquear', [PrestadorController::class, 'bloquearHorario'])->name('bloquear');
            Route::delete('/{id}', [PrestadorController::class, 'desbloquearHorario'])->name('desbloquear');
        });

        // Solicitações de serviço
        Route::prefix('solicitacoes')->name('solicitacoes.')->group(function () {
            Route::get('/', [PrestadorController::class, 'solicitacoes'])->name('index');
            Route::put('/{id}/aceitar', [PrestadorController::class, 'aceitarSolicitacao'])->name('aceitar');
            Route::put('/{id}/recusar', [PrestadorController::class, 'recusarSolicitacao'])->name('recusar');
        });

        // Categorias do prestador
        Route::prefix('categorias')->name('categorias.')->group(function () {
            Route::get('/', [PrestadorController::class, 'minhasCategorias'])->name('index');
            Route::post('/{categoriaId}', [PrestadorController::class, 'addCategoria'])->name('add');
            Route::delete('/{categoriaId}', [PrestadorController::class, 'removeCategoria'])->name('remove');
        });

        // Estatísticas do prestador
        Route::get('/stats', [PrestadorController::class, 'stats'])->name('stats');
    });

    // ==========================================
    // 2.5 ADMINISTRAÇÃO (apenas para administradores)
    // ==========================================
    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/atividade', [AdminController::class, 'atividade'])->name('atividade');

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
        });

        // Configurações
        Route::get('/configuracoes', [AdminController::class, 'configuracoes'])->name('configuracoes');
        Route::put('/configuracoes', [AdminController::class, 'updateConfiguracoes'])->name('configuracoes.update');

        // Logs
        Route::get('/logs', [AdminController::class, 'logs'])->name('logs');
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
