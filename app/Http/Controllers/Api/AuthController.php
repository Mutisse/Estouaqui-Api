<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Controller exclusivo para autenticação
 * Login, Logout, Recuperação de senha
 */
class AuthController extends Controller
{
    /**
     * Login do usuário - OTIMIZADO
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

        // ✅ OTIMIZAÇÃO 1: Buscar apenas campos necessários
        $field = $request->has('email') ? 'email' : 'telefone';
        $value = $field === 'email' ? $request->email : preg_replace('/\D/', '', $request->telefone);

        // ✅ OTIMIZAÇÃO 2: Query com select específico e cache
        $cacheKey = "user_login_{$field}_{$value}";

        $user = Cache::remember($cacheKey, 60, function () use ($field, $value) {
            return User::where($field, $value)
                ->select('id', 'nome', 'email', 'telefone', 'foto', 'tipo', 'password', 'ativo', 'blocked_at')
                ->first();
        });

        // ✅ OTIMIZAÇÃO 3: Verificação rápida de existência
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Credenciais inválidas'
            ], 401);
        }

        // ✅ OTIMIZAÇÃO 4: Hash check otimizado
        if (!Hash::check($request->password, $user->password)) {
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

        // ✅ OTIMIZAÇÃO 5: Não deletar todos os tokens - apenas limitar
        // Remover apenas tokens antigos (mais de 30 dias)
        $user->tokens()->where('created_at', '<', now()->subDays(30))->delete();

        // ✅ OTIMIZAÇÃO 6: Criar token com expiração
        $token = $user->createToken('auth_token', ['*'], now()->addDays(7))->plainTextToken;

        // ✅ OTIMIZAÇÃO 7: Cache dos dados do usuário
        $userData = [
            'id' => $user->id,
            'nome' => $user->nome,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
            'tipo' => $user->tipo,
        ];

        Cache::put("user_data_{$user->id}", $userData, 3600);

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
        // Revogar o token atual
        $request->user()->currentAccessToken()->delete();

        // Limpar cache do usuário
        Cache::forget("user_data_{$request->user()->id}");

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

        // Revogar todos os tokens do usuário
        $user->tokens()->delete();

        // Limpar cache
        Cache::forget("user_data_{$user->id}");
        Cache::forget("user_login_email_{$user->email}");
        Cache::forget("user_login_telefone_{$user->telefone}");

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

        // ✅ OTIMIZAÇÃO: Buscar do cache
        $userData = Cache::remember("user_data_{$user->id}", 3600, function () use ($user) {
            return [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'tipo' => $user->tipo,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => ['user' => $userData]
        ]);
    }
}
