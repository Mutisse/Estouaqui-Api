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
 * Controller exclusivo para autenticação - VERSÃO OTIMIZADA
 * Login, Logout, Recuperação de senha
 */
class AuthController extends Controller
{
    /**
     * Login do usuário - OTIMIZADO (SEM CACHE PARA EVITAR TIMEOUT)
     * POST /api/login
     */
    public function login(Request $request)
    {
        // Validação rápida
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

        // Determinar campo de busca
        $field = $request->has('email') ? 'email' : 'telefone';
        $value = $field === 'email' ? $request->email : preg_replace('/\D/', '', $request->telefone);

        // ✅ Query ÚNICA - SEM CACHE (cache causa timeout)
        $user = User::where($field, $value)
            ->select('id', 'nome', 'email', 'telefone', 'foto', 'tipo', 'password', 'ativo', 'blocked_at')
            ->first();

        // Verificações rápidas
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Credenciais inválidas'
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'Credenciais inválidas'
            ], 401);
        }

        if ($user->blocked_at) {
            return response()->json([
                'success' => false,
                'error' => 'Conta bloqueada. Contacte o suporte.'
            ], 403);
        }

        // ✅ REMOVER TOKENS ANTIGOS (operação rápida)
        $user->tokens()->where('created_at', '<', now()->subDays(30))->delete();

        // ✅ CRIAR TOKEN (operação rápida)
        $token = $user->createToken('auth_token', ['*'], now()->addDays(7))->plainTextToken;

        // ✅ DADOS DO USUÁRIO (APENAS ARRAY)
        $userData = [
            'id' => $user->id,
            'nome' => $user->nome,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
            'tipo' => $user->tipo,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Login efetuado com sucesso',
            'data' => [
                'user' => $userData,
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
        $user = $request->user();

        if ($user) {
            // Revogar o token atual
            $user->currentAccessToken()->delete();
        }

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

        // Revogar todos os tokens do usuário
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso. Faça login com sua nova senha.'
        ]);
    }

    /**
     * Verificar token - OTIMIZADO
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

        // ✅ Dados simples (sem cache para evitar timeout)
        $userData = [
            'id' => $user->id,
            'nome' => $user->nome,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
            'tipo' => $user->tipo,
        ];

        return response()->json([
            'success' => true,
            'data' => ['user' => $userData]
        ]);
    }
}
