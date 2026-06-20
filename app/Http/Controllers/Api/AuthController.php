<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends BaseController
{
    /**
     * 🔥 HELPER PARA GERAR URL DE IMAGENS
     */
    private function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $path = ltrim($path, '/');
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = str_replace('app/public/', '', $path);

        return url('/imagem/' . $path);
    }

    /**
     * 🔥 LOGIN - COM VERIFICAÇÃO DE STATUS E VERIFICADO
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required_without:telefone|string',
            'telefone' => 'required_without:email|string',
            'password' => 'required|string|min:6',
        ]);

        $login = $request->email ?? $request->telefone;
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
        $field = $isEmail ? 'email' : 'telefone';

        $user = User::where($field, $login)->first();

        // 🔥 VERIFICAR CREDENCIAIS
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/Telefone ou palavra-passe incorretos'
            ], 401);
        }

        // 🔥 VERIFICAR STATUS DO UTILIZADOR
        if ($user->status === 'bloqueado') {
            return response()->json([
                'success' => false,
                'message' => 'A sua conta foi bloqueada. Contacte o suporte.'
            ], 403);
        }

        if ($user->status === 'desativado') {
            return response()->json([
                'success' => false,
                'message' => 'A sua conta foi desativada. Contacte o suporte.'
            ], 403);
        }

        if ($user->status === 'reprovado') {
            return response()->json([
                'success' => false,
                'message' => 'O seu cadastro foi reprovado. Contacte o suporte.'
            ], 403);
        }

        // 🔥 SE FOR PRESTADOR E NÃO VERIFICADO, AVISAR
        if ($user->tipo === 'prestador' && !$user->verificado) {
            $aviso = 'A sua conta está pendente de verificação. Algumas funcionalidades podem estar limitadas.';
        } else {
            $aviso = null;
        }

        // Remove todos tokens antigos
        $user->tokens()->delete();

        // Cria novo token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'foto' => $this->getImageUrl($user->foto),
                'tipo' => $user->tipo,
                'status' => $user->status,
                'verificado' => (bool) $user->verificado, // 🔥 FORÇA BOOLEAN
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'aviso' => $aviso,
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
     * 🔥 VERIFY - COM VERIFICAÇÃO
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
                    'foto' => $this->getImageUrl($user->foto),
                    'tipo' => $user->tipo,
                    'status' => $user->status,
                    'verificado' => (bool) $user->verificado, // 🔥 FORÇA BOOLEAN
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
     * 🔥 REGISTRO - COM STATUS E VERIFICADO
     */
    public function register(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telefone' => 'required|string|unique:users,telefone',
            'password' => 'required|string|min:6',
            'endereco' => 'nullable|string',
            'foto' => 'nullable|image|max:5120',
            'sobre' => 'nullable|string',
            'profissao' => 'nullable|string',
            'raio_atendimento' => 'nullable|integer|min:1|max:100',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'categorias' => 'nullable|json',
            'disponibilidade' => 'nullable|json',
            'portfolio.*' => 'nullable|image|max:5120',
            'documento' => 'nullable|file|max:10240',
        ]);

        // ========== DETECTAR TIPO DE PERFIL ==========
        $temDadosPrestador = $request->has('categorias') ||
            $request->has('disponibilidade') ||
            $request->hasFile('portfolio') ||
            $request->hasFile('documento') ||
            $request->has('sobre') ||
            $request->has('profissao');

        $tipo = $temDadosPrestador ? 'prestador' : 'cliente';

        // ========== CRIAR USUÁRIO ==========
        $userData = [
            'nome' => $request->nome,
            'email' => $request->email,
            'telefone' => $request->telefone,
            'password' => Hash::make($request->password),
            'tipo' => $tipo,
            'status' => 'ativo', // 🔥 STATUS PADRÃO
            'verificado' => $tipo === 'cliente', // 🔥 CLIENTE JÁ VERIFICADO
        ];

        $user = User::create($userData);

        // ========== PROCESSAR FOTO ==========
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('usuarios', 'public');
            $user->foto = $path;
            $user->save();
        }

        // ========== PROCESSAR DADOS DO PRESTADOR ==========
        if ($tipo === 'prestador') {
            // 🔥 PRESTADOR FICA PENDENTE
            $user->verificado = false;
            $user->status = 'pendente';
            $user->save();

            $profile = $user->prestadorProfile()->create();

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

            if ($request->has('disponibilidade')) {
                $disponibilidade = json_decode($request->disponibilidade, true);
                if (is_array($disponibilidade)) {
                    $profileData['disponibilidade'] = $disponibilidade;
                }
            }

            if (!empty($profileData)) {
                $profile->update($profileData);
            }

            // Processar categorias
            if ($request->has('categorias')) {
                $categorias = json_decode($request->categorias, true);
                if (is_array($categorias) && count($categorias) > 0) {
                    $user->categorias()->sync($categorias);
                }
            }

            // Processar portfolio
            if ($request->hasFile('portfolio')) {
                $portfolioPaths = [];
                foreach ($request->file('portfolio') as $file) {
                    $path = $file->store('portfolio/' . $user->id, 'public');
                    $portfolioPaths[] = $path;
                }
                $profile->portfolio = $portfolioPaths;
                $profile->save();
            }

            // Processar documento
            if ($request->hasFile('documento')) {
                $documentoPath = $request->file('documento')->store('documentos/' . $user->id, 'public');
                $profile->documento = $documentoPath;
                $profile->status_documento = 'pendente';
                $profile->save();
            }
        }

        // ========== PROCESSAR ENDEREÇO (CLIENTE) ==========
        if ($tipo === 'cliente' && $request->has('endereco')) {
            $user->enderecos()->create([
                'endereco' => $request->endereco,
                'principal' => true,
            ]);
        }

        // ========== NOTIFICAÇÃO ==========
        NotificationService::send('sistema.bem_vindo', $user->id, [
            'nome' => $user->nome
        ]);

        // ========== RESPOSTA ==========
        $token = $user->createToken('auth_token')->plainTextToken;

        $userDataResponse = [
            'id' => $user->id,
            'nome' => $user->nome,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'foto' => $this->getImageUrl($user->foto),
            'tipo' => $user->tipo,
            'status' => $user->status,
            'verificado' => (bool) $user->verificado, // 🔥 FORÇA BOOLEAN
        ];

        if ($tipo === 'prestador' && $user->prestadorProfile) {
            $userDataResponse['sobre'] = $user->prestadorProfile->sobre;
            $userDataResponse['profissao'] = $user->prestadorProfile->profissao;
            $userDataResponse['raio_atendimento'] = $user->prestadorProfile->raio_atendimento;
            $userDataResponse['disponivel'] = $user->prestadorProfile->disponivel;
            $userDataResponse['media_avaliacao'] = $user->prestadorProfile->media_avaliacao;
            $userDataResponse['total_avaliacoes'] = $user->prestadorProfile->total_avaliacoes;
        }

        return response()->json([
            'success' => true,
            'message' => $tipo === 'prestador'
                ? 'Registo efetuado! Aguarde a verificação da sua conta.'
                : 'Registo efetuado com sucesso!',
            'token' => $token,
            'user' => $userDataResponse
        ], 201);
    }

    /**
     * Atualizar perfil do usuário
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
                'foto' => $this->getImageUrl($user->foto),
                'tipo' => $user->tipo,
                'status' => $user->status,
                'verificado' => (bool) $user->verificado, // 🔥 FORÇA BOOLEAN
            ]
        ]);
    }

    /**
     * Upload de foto do usuário
     */
    public function uploadFoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|max:5120',
        ]);

        $user = $request->user();

        if ($request->hasFile('foto')) {
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
                'foto' => $this->getImageUrl($path)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Nenhuma foto enviada'
        ], 400);
    }

    /**
     * 🔥 USER - COM VERIFICAÇÃO
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
                'foto' => $this->getImageUrl($user->foto),
                'tipo' => $user->tipo,
                'status' => $user->status,
                'verificado' => (bool) $user->verificado, // 🔥 FORÇA BOOLEAN
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }
}
