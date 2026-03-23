<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Controller genérico para funcionalidades comuns a todos os usuários
 * (cliente, prestador, admin)
 */
class UsuarioController extends Controller
{
    // ==========================================
    // 1. FUNCIONALIDADES COMUNS (todos os perfis)
    // ==========================================

    /**
     * Obter perfil do usuário autenticado
     * GET /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'endereco' => $user->endereco,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'tipo' => $user->tipo,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * Atualizar perfil do usuário autenticado
     * PUT /api/me
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255',
            'telefone' => 'sometimes|string|max:20',
            'endereco' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('nome')) $user->nome = $request->nome;
            if ($request->has('telefone')) $user->telefone = $request->telefone;
            if ($request->has('endereco')) $user->endereco = $request->endereco;

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar perfil'
            ], 500);
        }
    }

    /**
     * Atualizar foto de perfil
     * POST /api/avatar
     */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }

            $path = $request->file('foto')->store('fotos/usuarios', 'public');
            $user->foto = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto atualizada com sucesso',
                'data' => ['foto' => asset('storage/' . $path)]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar foto'
            ], 500);
        }
    }

    /**
     * Remover foto de perfil
     * DELETE /api/avatar
     */
    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        try {
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
                $user->foto = null;
                $user->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Foto removida com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover foto'
            ], 500);
        }
    }

    /**
     * Alterar senha
     * PUT /api/password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'Senha atual incorreta'
            ], 422);
        }

        try {
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao alterar senha'
            ], 500);
        }
    }

    /**
     * Deletar conta (soft delete)
     * DELETE /api/me
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conta removida com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover conta'
            ], 500);
        }
    }

    /**
     * Dashboard do usuário (dados específicos por perfil)
     * GET /api/dashboard
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $data = [
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'tipo' => $user->tipo,
            ],
            'stats' => [
                'membro_desde' => $user->created_at->format('d/m/Y'),
                'anos' => now()->diffInYears($user->created_at),
            ]
        ];

        // Estatísticas específicas por perfil
        if ($user->isCliente()) {
            $data['stats']['pedidos_ativos'] = 0; // TODO
            $data['stats']['pedidos_concluidos'] = 0;
            $data['stats']['favoritos'] = 0;
            $data['stats']['avaliacoes'] = 0;
        } elseif ($user->isPrestador()) {
            $data['stats']['servicos_ativos'] = 0; // TODO
            $data['stats']['servicos_concluidos'] = 0;
            $data['stats']['avaliacoes'] = 0;
            $data['stats']['rating'] = 0;
        } elseif ($user->isAdmin()) {
            $data['stats']['total_usuarios'] = User::count();
            $data['stats']['total_clientes'] = User::where('tipo', 'cliente')->count();
            $data['stats']['total_prestadores'] = User::where('tipo', 'prestador')->count();
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Listar notificações do usuário
     * GET /api/notifications
     */
    public function notifications(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $user->notifications()->paginate(20)
        ]);
    }

    /**
     * Marcar notificação como lida
     * PUT /api/notifications/{id}/read
     */
    public function markNotificationRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Marcar todas notificações como lidas
     * PUT /api/notifications/read-all
     */
    public function markAllNotificationsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }

    /**
     * Obter preferências do usuário
     * GET /api/preferences
     */
    public function preferences(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'theme' => $user->preferences['theme'] ?? 'light',
                'language' => $user->preferences['language'] ?? 'pt',
                'notifications' => $user->preferences['notifications'] ?? true,
            ]
        ]);
    }

    /**
     * Atualizar preferências
     * PUT /api/preferences
     */
    public function updatePreferences(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'theme' => 'sometimes|in:light,dark',
            'language' => 'sometimes|in:pt,en',
            'notifications' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $preferences = $user->preferences ?? [];
        if ($request->has('theme')) $preferences['theme'] = $request->theme;
        if ($request->has('language')) $preferences['language'] = $request->language;
        if ($request->has('notifications')) $preferences['notifications'] = $request->notifications;

        $user->preferences = $preferences;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Preferências atualizadas',
            'data' => $preferences
        ]);
    }

    /**
     * Listar endereços do usuário
     * GET /api/addresses
     */
    public function addresses(Request $request)
    {
        // TODO: Implementar quando criar tabela de endereços
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    /**
     * Criar novo endereço
     * POST /api/addresses
     */
    public function createAddress(Request $request)
    {
        // TODO: Implementar quando criar tabela de endereços
        return response()->json([
            'success' => true,
            'message' => 'Endereço criado com sucesso',
            'data' => []
        ], 201);
    }

    /**
     * Obter endereço específico
     * GET /api/addresses/{id}
     */
    public function getAddress($id)
    {
        // TODO: Implementar quando criar tabela de endereços
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    /**
     * Atualizar endereço
     * PUT /api/addresses/{id}
     */
    public function updateAddress(Request $request, $id)
    {
        // TODO: Implementar quando criar tabela de endereços
        return response()->json([
            'success' => true,
            'message' => 'Endereço atualizado com sucesso'
        ]);
    }

    /**
     * Deletar endereço
     * DELETE /api/addresses/{id}
     */
    public function deleteAddress($id)
    {
        // TODO: Implementar quando criar tabela de endereços
        return response()->json([
            'success' => true,
            'message' => 'Endereço removido com sucesso'
        ]);
    }

    /**
     * Definir endereço principal
     * PUT /api/addresses/{id}/primary
     */
    public function setPrimaryAddress($id)
    {
        // TODO: Implementar quando criar tabela de endereços
        return response()->json([
            'success' => true,
            'message' => 'Endereço principal definido com sucesso'
        ]);
    }

    // ==========================================
    // 2. MÉTODOS PÚBLICOS (sem autenticação)
    // ==========================================

    /**
     * Verificar disponibilidade de email
     * GET /api/check-email?email=
     */
    public function checkEmail(Request $request)
    {
        $email = $request->query('email');
        $exists = User::where('email', $email)->exists();

        return response()->json([
            'available' => !$exists
        ]);
    }

    /**
     * Verificar disponibilidade de telefone
     * GET /api/check-phone?phone=
     */
    public function checkPhone(Request $request)
    {
        $phone = $request->query('phone');
        $exists = User::where('telefone', $phone)->exists();

        return response()->json([
            'available' => !$exists
        ]);
    }

    /**
     * Upload temporário de foto
     * POST /api/upload-temp
     */
    public function uploadTemp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $path = $request->file('foto')->store('fotos/temp', 'public');

            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao fazer upload'
            ], 500);
        }
    }
}
