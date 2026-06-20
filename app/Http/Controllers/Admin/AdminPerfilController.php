<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Atividade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminPerfilController extends Controller
{
    // ==========================================
    // 🔥 HELPER PARA GERAR URL DE IMAGENS (CORRIGIDO)
    // ==========================================

    /**
     * Gera URL correta para imagens usando a rota /imagem
     */
    private function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // 🔥 LIMPAR O PATH COMPLETAMENTE
        $path = ltrim($path, '/');
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = str_replace('app/public/', '', $path);
        $path = str_replace('perfil-fotos/', '', $path);

        // 🔥 REMOVER QUALQUER DUPLICAÇÃO DE "perfis/"
        $path = preg_replace('#perfis/+#', 'perfis/', $path);

        // 🔥 GARANTIR QUE O PATH COMEÇA COM A PASTA CORRETA
        // Se não começar com perfis/, adiciona
        if (!str_starts_with($path, 'perfis/')) {
            $path = 'perfis/' . ltrim($path, '/');
        }

        // 🔥 USA A ROTA /imagem
        return url('/imagem/' . $path);
    }

    /**
     * Limpa o caminho do storage
     */
    private function cleanStoragePath(string $path): string
    {
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = str_replace('app/public/', '', $path);
        $path = str_replace('perfil-fotos/', '', $path);
        $path = ltrim($path, '/');
        return $path;
    }

    // ==========================================
    // 🔥 PERFIL - GET
    // ==========================================

    /**
     * GET /admin/perfil
     * Obter dados do perfil do usuário logado
     */
    public function getPerfil()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $perfil = [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone ?? '',
                'foto' => $this->getImageUrl($user->foto),
                'tipo' => $user->tipo ?? 'admin',
                'verificado' => (bool) ($user->verificado ?? false),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $perfil
            ]);
        } catch (\Exception $e) {
            Log::error('Erro em getPerfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 🔥 ATUALIZAR PERFIL
    // ==========================================

    /**
     * PUT /admin/perfil
     * Atualizar dados do perfil
     */
    public function atualizarPerfil(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id)
                ],
                'telefone' => 'nullable|string|max:20'
            ]);

            $userModel = User::find($user->id);

            if (!$userModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            $userModel->nome = $validated['nome'];
            $userModel->email = $validated['email'];

            if (isset($validated['telefone'])) {
                $userModel->telefone = $validated['telefone'];
            }

            $userModel->save();

            $this->registrarAtividade(
                'atualizacao',
                'Perfil atualizado: nome, email' . (isset($validated['telefone']) ? ' e telefone' : '')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $userModel->id,
                    'nome' => $userModel->nome,
                    'email' => $userModel->email,
                    'telefone' => $userModel->telefone ?? '',
                    'foto' => $this->getImageUrl($userModel->foto),
                    'tipo' => $userModel->tipo,
                    'verificado' => (bool) $userModel->verificado,
                    'created_at' => $userModel->created_at,
                    'updated_at' => $userModel->updated_at,
                ],
                'message' => 'Perfil atualizado com sucesso'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro em atualizarPerfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /admin/perfil/senha
     * Alterar senha do usuário
     */
    public function alterarSenha(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'senha_atual' => 'required|string',
                'nova_senha' => 'required|string|min:6',
                'nova_senha_confirmation' => 'required|string|same:nova_senha'
            ]);

            $userModel = User::find($user->id);

            if (!$userModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            if (!Hash::check($validated['senha_atual'], $userModel->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Senha atual incorreta'
                ], 422);
            }

            $userModel->password = Hash::make($validated['nova_senha']);
            $userModel->save();

            $this->registrarAtividade('atualizacao', 'Senha alterada');

            return response()->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro em alterarSenha: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar senha: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 🔥 ATUALIZAR FOTO - CORRIGIDO
    // ==========================================

    /**
     * POST /admin/perfil/foto
     * Atualizar foto do perfil
     */
    public function atualizarFoto(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'foto' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120'
            ]);

            $userModel = User::find($user->id);

            if (!$userModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            // 🔥 REMOVER FOTO ANTIGA
            if ($userModel->foto) {
                $oldPath = $this->cleanStoragePath($userModel->foto);

                // Tenta remover da nova pasta
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                    Log::info('🗑️ Foto antiga do admin removida: ' . $oldPath);
                }

                // Tenta remover da pasta antiga (fallback)
                $oldPathFallback = 'perfil-fotos/' . basename($userModel->foto);
                if (Storage::disk('public')->exists($oldPathFallback)) {
                    Storage::disk('public')->delete($oldPathFallback);
                    Log::info('🗑️ Foto antiga do admin removida (fallback): ' . $oldPathFallback);
                }
            }

            // 🔥 SALVAR NA PASTA CORRETA: perfis/admin/
            $file = $request->file('foto');
            $extension = $file->getClientOriginalExtension();
            $filename = 'admin_' . $user->id . '_' . time() . '.' . $extension;
            $path = $file->storeAs('perfis/admin', $filename, 'public');

            Log::info('📁 Foto do admin salva em: ' . $path);

            // 🔥 SALVAR APENAS O CAMINHO RELATIVO
            $userModel->foto = $path;
            $userModel->save();

            $this->registrarAtividade('atualizacao', 'Foto de perfil atualizada');

            // 🔥 RETORNAR URL COMPLETA USANDO /imagem
            return response()->json([
                'success' => true,
                'foto' => $this->getImageUrl($path),
                'message' => 'Foto atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro em atualizarFoto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar foto: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 🔥 ATIVIDADES
    // ==========================================

    /**
     * GET /admin/atividades
     * Listar atividades recentes do usuário
     */
    public function getAtividades(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $limit = $request->input('limit', 20);

            if (!Schema::hasTable('atividades')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Nenhuma atividade registrada ainda'
                ]);
            }

            $atividades = Atividade::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'descricao' => $item->descricao,
                        'tipo' => $item->tipo,
                        'ip' => $item->ip ?? '',
                        'user_agent' => $item->user_agent ?? '',
                        'created_at' => $item->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $atividades
            ]);
        } catch (\Exception $e) {
            Log::error('Erro em getAtividades: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar atividades: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 🔥 MÉTODO PRIVADO PARA REGISTRAR ATIVIDADES
    // ==========================================

    /**
     * Método privado para registrar atividades
     */
    private function registrarAtividade(string $tipo, string $descricao)
    {
        try {
            $user = Auth::user();
            if (!$user) return;

            if (!Schema::hasTable('atividades')) {
                return;
            }

            Atividade::create([
                'user_id' => $user->id,
                'descricao' => $descricao,
                'tipo' => $tipo,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar atividade: ' . $e->getMessage());
        }
    }
}
