<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Controller exclusivo para autenticação
 * Login, Logout, Recuperação de senha
 */
class AuthController extends Controller
{
    /**
     * Login do usuário
     * POST /api/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:telefone|email',
            'telefone' => 'required_without:email|string|max:20',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        // Buscar usuário por email ou telefone
        $user = User::where('email', $request->email)
            ->orWhere('telefone', $request->telefone)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'Credenciais inválidas'
            ], 401);
        }

        // Verificar se conta está bloqueada
        if ($user->blocked_at) {
            return response()->json([
                'success' => false,
                'error' => 'Conta bloqueada. Contacte o suporte.'
            ], 403);
        }

        // Revogar tokens antigos (opcional - manter apenas o atual)
        $user->tokens()->delete();

        // Criar novo token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login efetuado com sucesso',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                    'tipo' => $user->tipo,
                ],
                'token' => $token
            ]
        ]);
    }

    /**
     * Logout do usuário
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        // Revogar o token atual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout efetuado com sucesso'
        ]);
    }

    /**
     * Solicitar recuperação de senha
     * POST /api/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        $token = Str::random(64);

        // Salvar token na tabela de reset
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => now()]
        );

        // TODO: Enviar email com link de reset
        // Link: /reset-password/{token}?email={$user->email}

        return response()->json([
            'success' => true,
            'message' => 'Link de recuperação enviado para o email'
        ]);
    }

    /**
     * Resetar senha
     * POST /api/reset-password/{token}
     */
    public function resetPassword(Request $request, $token)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:password'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        // Verificar token
        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $token)
            ->first();

        if (!$reset) {
            return response()->json([
                'success' => false,
                'error' => 'Token inválido ou expirado'
            ], 422);
        }

        // Verificar se token não expirou (24 horas)
        $expiration = now()->subHours(24);
        if ($reset->created_at < $expiration) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'error' => 'Token expirado. Solicite um novo link de recuperação.'
            ], 422);
        }

        // Atualizar senha
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Remover token usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Revogar todos os tokens do usuário (opcional)
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso. Faça login com sua nova senha.'
        ]);
    }

    /**
     * Verificar token (opcional - para validação no frontend)
     * GET /api/verify-token
     */
    public function verifyToken(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Token inválido'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'tipo' => $user->tipo,
                ]
            ]
        ]);
    }
}
