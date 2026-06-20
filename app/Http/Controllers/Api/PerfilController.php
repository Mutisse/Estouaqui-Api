<?php
// app/Http/Controllers/Api/PerfilController.php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Pedido;
use App\Models\Favorito;
use App\Models\Avaliacao;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PerfilController extends BaseController
{
    // ==========================================
    // 🔥 HELPER PARA GERAR URL DE IMAGENS
    // ==========================================

    /**
     * Gera URL correta para imagens usando a rota /imagem
     */
    private function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Limpa o path
        $path = ltrim($path, '/');
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = str_replace('app/public/', '', $path);
        $path = str_replace('usuarios/', '', $path);

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
        $path = str_replace('usuarios/', '', $path);
        $path = ltrim($path, '/');
        return $path;
    }

    // ==========================================
    // 🔥 PERFIL - SHOW (CORRIGIDO)
    // ==========================================

    /**
     * GET /cliente/perfil
     * Obter dados do perfil do usuário logado (INCLUINDO ENDEREÇO)
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Buscar o endereço principal do usuário (ou o primeiro endereço)
        $enderecoPrincipal = $user->enderecos()
            ->where('principal', true)
            ->first();

        if (!$enderecoPrincipal) {
            $enderecoPrincipal = $user->enderecos()->first();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'tipo' => $user->tipo,
                'foto' => $this->getImageUrl($user->foto), // 🔥 CORRIGIDO
                'created_at' => $user->created_at,
                'profissao' => $user->profissao,
                'sobre' => $user->sobre,
                'media_avaliacao' => $user->media_avaliacao,
                'total_avaliacoes' => $user->total_avaliacoes,
                'endereco' => $enderecoPrincipal ? [
                    'id' => $enderecoPrincipal->id,
                    'rua' => $enderecoPrincipal->rua,
                    'numero' => $enderecoPrincipal->numero,
                    'bairro' => $enderecoPrincipal->bairro,
                    'cidade' => $enderecoPrincipal->cidade,
                    'provincia' => $enderecoPrincipal->provincia,
                    'ponto_referencia' => $enderecoPrincipal->ponto_referencia,
                    'complemento' => $enderecoPrincipal->complemento,
                    'principal' => $enderecoPrincipal->principal,
                ] : null,
            ]
        ]);
    }

    // ==========================================
    // 🔥 ATUALIZAR PERFIL
    // ==========================================

    /**
     * PUT /cliente/perfil
     * Atualizar dados do perfil (INCLUINDO ENDEREÇO)
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nome' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'telefone' => 'sometimes|string|unique:users,telefone,' . $user->id,
            'profissao' => 'sometimes|string|max:255',
            'sobre' => 'sometimes|string|max:1000',
            'endereco' => 'sometimes|array',
            'endereco.rua' => 'nullable|string|max:255',
            'endereco.numero' => 'nullable|string|max:20',
            'endereco.bairro' => 'nullable|string|max:255',
            'endereco.cidade' => 'nullable|string|max:255',
            'endereco.provincia' => 'nullable|string|max:255',
            'endereco.ponto_referencia' => 'nullable|string|max:255',
            'endereco.complemento' => 'nullable|string|max:255',
        ]);

        // Atualizar dados básicos do perfil
        if ($request->has('nome')) {
            $user->nome = $request->nome;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('telefone')) {
            $user->telefone = $request->telefone;
        }

        if ($request->has('profissao')) {
            $user->profissao = $request->profissao;
        }

        if ($request->has('sobre')) {
            $user->sobre = $request->sobre;
        }

        $user->save();

        // Atualizar ENDEREÇO
        if ($request->has('endereco') && $request->endereco) {
            $enderecoData = $request->endereco;

            $endereco = $user->enderecos()->where('principal', true)->first();

            if (!$endereco) {
                $endereco = $user->enderecos()->create(array_merge($enderecoData, ['principal' => true]));
            } else {
                $endereco->update($enderecoData);
            }
        }

        NotificationService::send('sistema.perfil_atualizado', $user->id, [
            'nome' => $user->nome
        ]);

        $enderecoPrincipal = $user->enderecos()->where('principal', true)->first();

        return response()->json([
            'success' => true,
            'message' => 'Perfil atualizado com sucesso!',
            'data' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'tipo' => $user->tipo,
                'foto' => $this->getImageUrl($user->foto), // 🔥 CORRIGIDO
                'profissao' => $user->profissao,
                'sobre' => $user->sobre,
                'endereco' => $enderecoPrincipal ? [
                    'id' => $enderecoPrincipal->id,
                    'rua' => $enderecoPrincipal->rua,
                    'numero' => $enderecoPrincipal->numero,
                    'bairro' => $enderecoPrincipal->bairro,
                    'cidade' => $enderecoPrincipal->cidade,
                    'provincia' => $enderecoPrincipal->provincia,
                    'ponto_referencia' => $enderecoPrincipal->ponto_referencia,
                    'complemento' => $enderecoPrincipal->complemento,
                    'principal' => $enderecoPrincipal->principal,
                ] : null,
            ]
        ]);
    }

    // ==========================================
    // 🔥 UPLOAD FOTO - CORRIGIDO
    // ==========================================

    /**
     * POST /cliente/perfil/foto
     * Atualizar foto do perfil
     */
    public function uploadFoto(Request $request)
    {
        $user = $request->user();

        Log::info('📸 Upload de foto iniciado para usuário: ' . $user->id);

        try {
            $validated = $request->validate([
                'foto' => 'required|image|max:5120|mimes:jpeg,png,jpg,gif'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Erro de validação da foto:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao validar a foto',
                'errors' => $e->errors()
            ], 422);
        }

        // 🔥 REMOVER FOTO ANTIGA
        if ($user->foto) {
            $oldPath = $this->cleanStoragePath($user->foto);

            // Tenta remover da nova pasta (perfis/cliente/)
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
                Log::info('🗑️ Foto antiga removida: ' . $oldPath);
            }

            // Tenta remover da pasta antiga (usuarios/) - fallback
            $oldPathFallback = 'usuarios/' . basename($user->foto);
            if (Storage::disk('public')->exists($oldPathFallback)) {
                Storage::disk('public')->delete($oldPathFallback);
                Log::info('🗑️ Foto antiga removida (fallback): ' . $oldPathFallback);
            }
        }

        // 🔥 SALVAR NA PASTA CORRETA: perfis/cliente/
        $file = $request->file('foto');
        $extension = $file->getClientOriginalExtension();
        $filename = 'perfil_' . $user->id . '_' . time() . '.' . $extension;
        $path = $file->storeAs('perfis/cliente', $filename, 'public');

        Log::info('📁 Foto salva em: ' . $path);

        // 🔥 SALVAR APENAS O CAMINHO RELATIVO
        $user->foto = $path;
        $user->save();

        NotificationService::send('sistema.perfil_atualizado', $user->id, [
            'nome' => $user->nome
        ]);

        // 🔥 RETORNAR URL COMPLETA USANDO /imagem
        return response()->json([
            'success' => true,
            'message' => 'Foto atualizada com sucesso!',
            'data' => [
                'foto' => $this->getImageUrl($path) // 🔥 CORRIGIDO
            ]
        ]);
    }

    // ==========================================
    // 🔥 REMOVER FOTO
    // ==========================================

    /**
     * DELETE /cliente/perfil/foto
     * Remover foto do perfil
     */
    public function removerFoto(Request $request)
    {
        $user = $request->user();

        if ($user->foto) {
            $path = $this->cleanStoragePath($user->foto);

            // Tenta remover da nova pasta
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info('🗑️ Foto removida: ' . $path);
            }

            // Tenta remover da pasta antiga - fallback
            $pathFallback = 'usuarios/' . basename($user->foto);
            if (Storage::disk('public')->exists($pathFallback)) {
                Storage::disk('public')->delete($pathFallback);
                Log::info('🗑️ Foto removida (fallback): ' . $pathFallback);
            }

            $user->foto = null;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto removida com sucesso!'
        ]);
    }

    // ==========================================
    // 🔥 DASHBOARD
    // ==========================================

    /**
     * GET /cliente/perfil/dashboard
     * Obter estatísticas do usuário
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $data = [
            'total_pedidos' => Pedido::where('cliente_id', $user->id)->count(),
            'avaliacoes_feitas' => Avaliacao::where('cliente_id', $user->id)->count(),
            'favoritos_count' => Favorito::where('cliente_id', $user->id)->count(),
            'anos_registro' => $user->created_at ? $user->created_at->diffInYears(now()) : 0,
        ];

        if ($user->isPrestador()) {
            $data['servicos_concluidos'] = Pedido::where('prestador_id', $user->id)
                ->where('status', 'concluido')
                ->count();
            $data['avaliacoes_recebidas'] = Avaliacao::where('prestador_id', $user->id)->count();
            $data['media_avaliacao'] = $user->media_avaliacao;
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // ==========================================
    // 🔥 ENDEREÇOS
    // ==========================================

    /**
     * GET /cliente/enderecos
     * Listar endereços do usuário
     */
    public function getEnderecos(Request $request)
    {
        $user = $request->user();
        $enderecos = $user->enderecos()->orderBy('principal', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $enderecos
        ]);
    }

    /**
     * POST /cliente/enderecos
     * Adicionar novo endereço
     */
    public function storeEndereco(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'rua' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'ponto_referencia' => 'nullable|string|max:255',
            'complemento' => 'nullable|string|max:255',
            'principal' => 'boolean',
        ]);

        if ($request->principal) {
            $user->enderecos()->update(['principal' => false]);
        }

        $endereco = $user->enderecos()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Endereço adicionado com sucesso!',
            'data' => $endereco
        ], 201);
    }

    /**
     * PUT /cliente/enderecos/{id}
     * Atualizar endereço
     */
    public function updateEndereco(Request $request, $id)
    {
        $user = $request->user();
        $endereco = $user->enderecos()->find($id);

        if (!$endereco) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        $request->validate([
            'rua' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'ponto_referencia' => 'nullable|string|max:255',
            'complemento' => 'nullable|string|max:255',
            'principal' => 'boolean',
        ]);

        if ($request->principal) {
            $user->enderecos()->update(['principal' => false]);
        }

        $dados = array_filter($request->all(), function($value) {
            return $value !== null && $value !== '';
        });

        $endereco->update($dados);

        return response()->json([
            'success' => true,
            'message' => 'Endereço atualizado com sucesso!',
            'data' => $endereco
        ]);
    }

    /**
     * PUT /cliente/enderecos/{id}/principal
     * Definir endereço como principal
     */
    public function setEnderecoPrincipal(Request $request, $id)
    {
        $user = $request->user();
        $endereco = $user->enderecos()->find($id);

        if (!$endereco) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        $user->enderecos()->update(['principal' => false]);
        $endereco->principal = true;
        $endereco->save();

        return response()->json([
            'success' => true,
            'message' => 'Endereço definido como principal!'
        ]);
    }

    /**
     * DELETE /cliente/enderecos/{id}
     * Remover endereço
     */
    public function deleteEndereco(Request $request, $id)
    {
        $user = $request->user();
        $endereco = $user->enderecos()->find($id);

        if (!$endereco) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        $endereco->delete();

        return response()->json([
            'success' => true,
            'message' => 'Endereço removido com sucesso!'
        ]);
    }

    // ==========================================
    // 🔥 CONFIGURAÇÕES
    // ==========================================

    /**
     * GET /cliente/configuracoes
     * Obter configurações do usuário
     */
    public function getConfiguracoes(Request $request)
    {
        $user = $request->user();

        $configuracoes = $user->configuracoes ?? [
            'notificacoes_email' => true,
            'notificacoes_push' => true,
            'idioma' => 'pt',
            'tema' => 'system',
        ];

        return response()->json([
            'success' => true,
            'data' => $configuracoes
        ]);
    }

    /**
     * PUT /cliente/configuracoes
     * Atualizar configurações do usuário
     */
    public function updateConfiguracoes(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'notificacoes_email' => 'sometimes|boolean',
            'notificacoes_push' => 'sometimes|boolean',
            'idioma' => 'sometimes|in:pt,en',
            'tema' => 'sometimes|in:light,dark,system',
        ]);

        $configuracoes = $user->configuracoes ?? [];
        $configuracoes = array_merge($configuracoes, $request->all());

        $user->configuracoes = $configuracoes;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Configurações atualizadas!',
            'data' => $configuracoes
        ]);
    }
}
