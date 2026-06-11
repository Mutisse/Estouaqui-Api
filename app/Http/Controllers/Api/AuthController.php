<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;  // ✅ ADICIONAR ESTA LINHA
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends BaseController
{
    /**
     * Login do usuário
     * Aceita email ou telefone
     */
    public function login(Request $request)
    {
        // Validação básica
        $request->validate([
            'email' => 'required_without:telefone|string',
            'telefone' => 'required_without:email|string',
            'password' => 'required|string|min:6',
        ]);

        // Determina o campo de login
        $login = $request->email ?? $request->telefone;
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
        $field = $isEmail ? 'email' : 'telefone';

        // Busca usuário
        $user = User::where($field, $login)->first();

        // Verifica credenciais
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/Telefone ou palavra-passe incorretos'
            ], 401);
        }

        // Remove todos tokens antigos
        $user->tokens()->delete();

        // Cria novo token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Retorna resposta com URL completa da foto
        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'tipo' => $user->tipo,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Logout do usuário
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout realizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer logout'
            ], 500);
        }
    }

    /**
     * Verifica token e retorna usuário
     */
    public function verify(Request $request)
    {
        $user = $request->user();

        if ($user) {
            return response()->json([
                'success' => true,
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                    'tipo' => $user->tipo,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'valid' => false,
            'message' => 'Token inválido ou expirado'
        ], 401);
    }

    /**
     * Solicita recuperação de senha
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required_without:telefone|email',
            'telefone' => 'required_without:email|string',
        ]);

        $user = null;

        if ($request->has('email')) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->has('telefone')) {
            $user = User::where('telefone', $request->telefone)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Se o usuário existir, enviaremos instruções de recuperação.'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Instruções de recuperação enviadas para o seu email/telefone!'
        ]);
    }

    /**
     * Redefine a senha
     */
    public function resetPassword(Request $request, $token)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6|confirmed',
            'confirm_password' => 'required|string|min:6',
        ]);

        if ($request->password !== $request->confirm_password) {
            return response()->json([
                'success' => false,
                'message' => 'As senhas não coincidem'
            ], 422);
        }

        $status = Password::reset(
            [
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->confirm_password,
                'token' => $token
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $user = User::where('email', $request->email)->first();
            if ($user) {
                NotificationService::send('sistema.perfil_atualizado', $user->id, [
                    'nome' => $user->nome
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso!'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Token inválido ou email não encontrado'
        ], 400);
    }

    /**
     * Registro de novo usuário
     */
    /**
     * Registro de novo usuário - DETECTA automaticamente o tipo
     * Baseado nos dados recebidos
     */
    /**
     * Registro de novo usuário - DETECTA automaticamente o tipo
     * Baseado nos dados recebidos
     */
    public function register(Request $request)
    {
        // Validação base (comum para todos)
        $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telefone' => 'required|string|unique:users,telefone',
            'password' => 'required|string|min:6|confirmed',

            // Campos opcionais
            'endereco' => 'nullable|string',
            'foto' => 'nullable|image|max:5120',

            // Campos específicos para prestador
            'sobre' => 'nullable|string',
            'profissao' => 'nullable|string',
            'raio_atendimento' => 'nullable|integer|min:1|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'categorias' => 'nullable|json',
            'disponibilidade' => 'nullable|json',
            'portfolio.*' => 'nullable|image|max:5120', // múltiplas imagens
            'documento' => 'nullable|file|max:10240', // até 10MB
        ]);

        // ========== DETECTAR TIPO DE PERFIL AUTOMATICAMENTE ==========
        $temDadosPrestador = $request->has('categorias') ||
            $request->has('disponibilidade') ||
            $request->hasFile('portfolio') ||
            $request->hasFile('documento') ||
            $request->has('sobre') ||
            $request->has('profissao');

        $tipo = $temDadosPrestador ? 'prestador' : 'cliente';

        // ========== CRIAR USUÁRIO BASE ==========
        $userData = [
            'nome' => $request->nome,
            'email' => $request->email,
            'telefone' => $request->telefone,
            'password' => Hash::make($request->password),
            'tipo' => $tipo,
        ];

        $user = User::create($userData);

        // ========== PROCESSAR FOTO (comum para ambos) ==========
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('usuarios', 'public');
            $user->foto = $path;
            $user->save();
        }

        // ========== PROCESSAR DADOS ESPECÍFICOS DE PRESTADOR ==========
        if ($tipo === 'prestador') {
            // Criar ou obter o perfil do prestador
            $prestadorProfile = $user->prestadorProfile()->create();

            // Salvar campos no perfil do prestador
            $profileData = [];

            if ($request->has('sobre')) {
                $profileData['sobre'] = $request->sobre;
            }

            if ($request->has('profissao')) {
                $profileData['profissao'] = $request->profissao;
            }

            if ($request->has('raio_atendimento')) {
                $profileData['raio_atendimento'] = $request->raio_atendimento;
            }

            if ($request->has('latitude')) {
                $profileData['latitude'] = $request->latitude;
            }

            if ($request->has('longitude')) {
                $profileData['longitude'] = $request->longitude;
            }

            // Salvar disponibilidade
            if ($request->has('disponibilidade')) {
                $disponibilidade = json_decode($request->disponibilidade, true);
                if (is_array($disponibilidade)) {
                    $profileData['disponibilidade'] = $disponibilidade;
                }
            }

            // Atualizar perfil com os dados
            if (!empty($profileData)) {
                $prestadorProfile->update($profileData);
            }

            // Processar categorias
            if ($request->has('categorias')) {
                $categorias = json_decode($request->categorias, true);
                if (is_array($categorias) && count($categorias) > 0) {
                    $user->categorias()->sync($categorias);
                }
            }

            // Processar portfolio (múltiplas imagens)
            if ($request->hasFile('portfolio')) {
                $portfolioPaths = [];
                foreach ($request->file('portfolio') as $file) {
                    $path = $file->store('portfolio/' . $user->id, 'public');
                    $portfolioPaths[] = $path;
                }
                $prestadorProfile->portfolio = $portfolioPaths;
                $prestadorProfile->save();
            }

            // Processar documento
            if ($request->hasFile('documento')) {
                $documentoPath = $request->file('documento')->store('documentos/' . $user->id, 'public');
                $prestadorProfile->documento = $documentoPath;
                $prestadorProfile->status_documento = 'pendente';
                $prestadorProfile->save();
            }
        }

        // ========== PROCESSAR ENDEREÇO (se for cliente) ==========
        if ($tipo === 'cliente' && $request->has('endereco')) {
            // Criar endereço para o cliente
            $user->enderecos()->create([
                'endereco' => $request->endereco,
                'principal' => true,
            ]);
        }

        // ========== NOTIFICAÇÃO DE BOAS-VINDAS ==========
        NotificationService::send('sistema.bem_vindo', $user->id, [
            'nome' => $user->nome
        ]);

        // ========== RESPOSTA ==========
        $token = $user->createToken('auth_token')->plainTextToken;

        // Preparar dados adicionais se for prestador
        $userDataResponse = [
            'id' => $user->id,
            'nome' => $user->nome,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
            'tipo' => $user->tipo,
        ];

        // Adicionar dados do prestador se aplicável
        if ($tipo === 'prestador' && $user->prestadorProfile) {
            $userDataResponse['sobre'] = $user->prestadorProfile->sobre;
            $userDataResponse['profissao'] = $user->prestadorProfile->profissao;
            $userDataResponse['raio_atendimento'] = $user->prestadorProfile->raio_atendimento;
            $userDataResponse['disponivel'] = $user->prestadorProfile->disponivel;
            $userDataResponse['verificado'] = $user->prestadorProfile->verificado;
            $userDataResponse['media_avaliacao'] = $user->prestadorProfile->media_avaliacao;
            $userDataResponse['total_avaliacoes'] = $user->prestadorProfile->total_avaliacoes;
        }

        return response()->json([
            'success' => true,
            'message' => 'Registo efetuado com sucesso!',
            'token' => $token,
            'user' => $userDataResponse
        ], 201);
    }

    /**
     * Atualizar perfil do usuário
     * PUT /api/auth/user
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nome' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'telefone' => 'sometimes|string|unique:users,telefone,' . $user->id,
        ]);

        if ($request->has('nome')) {
            $user->nome = $request->nome;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('telefone')) {
            $user->telefone = $request->telefone;
        }

        $user->save();

        NotificationService::send('sistema.perfil_atualizado', $user->id, [
            'nome' => $user->nome
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil atualizado com sucesso!',
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'tipo' => $user->tipo,
            ]
        ]);
    }

    /**
     * Upload de foto do usuário
     * POST /api/upload/foto
     */
    public function uploadFoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|max:5120',
        ]);

        $user = $request->user();

        if ($request->hasFile('foto')) {
            // Remover foto antiga se existir
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }

            $path = $request->file('foto')->store('usuarios', 'public');
            $user->foto = $path;
            $user->save();

            NotificationService::send('sistema.perfil_atualizado', $user->id, [
                'nome' => $user->nome
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto atualizada com sucesso!',
                'foto' => asset('storage/' . $path)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Nenhuma foto enviada'
        ], 400);
    }

    /**
     * Retorna os dados do usuário autenticado
     * GET /api/auth/user
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'tipo' => $user->tipo,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }
}
