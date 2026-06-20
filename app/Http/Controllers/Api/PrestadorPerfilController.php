<?php
// app/Http/Controllers/Api/PrestadorPerfilController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Servico;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrestadorPerfilController extends BaseController
{
    // ==========================================
    // 🔥 HELPER PARA GERAR URL DE IMAGENS
    // ==========================================

    /**
     * Gera URL correta para imagens usando a rota /imagem
     *
     * @param string|null $path Caminho da imagem (ex: "perfis/prestador/foto.jpg")
     * @return string|null URL completa da imagem
     */
    private function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Limpa o path (remove qualquer prefixo indesejado)
        $path = ltrim($path, '/');
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = str_replace('app/public/', '', $path);

        // 🔥 USA A ROTA /imagem (NÃO secure_asset)
        return url('/imagem/' . $path);
    }

    /**
     * Extrai o path de uma URL
     */
    private function extractPathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = str_replace('/storage/', '', $path);
        $path = str_replace('/imagem/', '', $path);
        $path = ltrim($path, '/');
        return $path;
    }

    /**
     * Limpa o caminho do storage
     */
    private function cleanStoragePath(string $path): string
    {
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = str_replace('app/public/', '', $path);
        $path = ltrim($path, '/');
        return $path;
    }

    /**
     * Processa URLs do portfólio (CORRIGIDO)
     */
    private function processPortfolioUrls(array $portfolio): array
    {
        $result = [];
        foreach ($portfolio as $index => $item) {
            if (is_string($item)) {
                $path = $this->cleanStoragePath($item);
                $result[] = [
                    'id' => $index + 1,
                    'url' => $this->getImageUrl($path), // 🔥 CORRIGIDO
                    'path' => $path,
                    'titulo' => '',
                    'descricao' => '',
                    'created_at' => now()->toISOString()
                ];
            } elseif (is_array($item)) {
                if (isset($item['path'])) {
                    $path = $this->cleanStoragePath($item['path']);
                    $item['url'] = $this->getImageUrl($path); // 🔥 CORRIGIDO
                } elseif (isset($item['url'])) {
                    $path = $this->extractPathFromUrl($item['url']);
                    $item['url'] = $this->getImageUrl($path); // 🔥 CORRIGIDO
                }
                $result[] = $item;
            }
        }
        return $result;
    }

    // ==========================================
    // 🔥 PERFIL - SHOW (CORRIGIDO)
    // ==========================================

    public function show(Request $request)
    {
        $user = $request->user();
        $profile = $user->prestadorProfile;

        $portfolio = $profile ? ($profile->portfolio ?? []) : [];
        $portfolioUrls = $this->processPortfolioUrls($portfolio);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'foto' => $this->getImageUrl($user->foto), // 🔥 CORRIGIDO
                'profissao' => $user->profissao,
                'sobre' => $user->sobre,
                'latitude' => $user->latitude ? (float) $user->latitude : null,
                'longitude' => $user->longitude ? (float) $user->longitude : null,
                'raio_atendimento' => $user->raio_atendimento ?? 10,
                'disponivel' => $user->disponivel ?? true,
                'verificado' => $user->verificado ?? false,
                'media_avaliacao' => $user->media_avaliacao ? (float) $user->media_avaliacao : 0,
                'total_avaliacoes' => $user->total_avaliacoes ?? 0,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'endereco' => $profile ? $profile->endereco : null,
                'portfolio' => $portfolioUrls,
                'disponibilidade' => $profile ? ($profile->disponibilidade ?? null) : null,
                'documento_verificado' => $profile ? ($profile->status_documento === 'aprovado') : false,
                'status_documento' => $profile ? ($profile->status_documento ?? 'pendente') : 'pendente',
            ]
        ]);
    }

    /**
     * PUT /prestador/perfil - Atualizar perfil
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'telefone' => 'sometimes|string|max:20',
            'profissao' => 'nullable|string|max:255',
            'sobre' => 'nullable|string',
            'endereco' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'raio_atendimento' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userData = $request->only([
            'nome',
            'email',
            'telefone',
            'profissao',
            'sobre',
            'latitude',
            'longitude',
            'raio_atendimento'
        ]);

        if (!empty($userData)) {
            $user->update($userData);
        }

        $profile = $user->prestadorProfile()->firstOrCreate(['user_id' => $user->id]);
        $profileData = $request->only(['endereco']);

        if (!empty($profileData)) {
            $profile->update($profileData);
        }

        NotificationService::send('sistema.perfil_atualizado', $user->id, [
            'nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil atualizado com sucesso',
            'data' => $this->getPerfilCompleto($user)
        ]);
    }

    // ==========================================
    // 🔥 UPLOAD FOTO DE PERFIL (CORRIGIDO)
    // ==========================================

    public function uploadFoto(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Arquivo inválido. Use uma imagem JPG ou PNG de até 5MB.',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');

            if ($file->getSize() > 5 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'A imagem não pode ter mais que 5MB'
                ], 422);
            }

            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }

            $extension = $file->getClientOriginalExtension();
            $filename = 'perfil_' . $user->id . '_' . time() . '.' . $extension;

            // 🔥 SALVA NA PASTA CORRETA: perfis/prestador/
            $path = $file->storeAs('perfis/prestador', $filename, 'public');

            $user->foto = $path;
            $user->save();

            NotificationService::send('perfil.foto_atualizada', $user->id, [
                'nome' => $user->nome,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto atualizada com sucesso',
                'data' => ['foto' => $this->getImageUrl($path)] // 🔥 CORRIGIDO
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Nenhuma foto enviada'
        ], 400);
    }

    /**
     * DELETE /prestador/perfil/foto - Remover foto de perfil
     */
    public function removerFoto(Request $request)
    {
        $user = $request->user();

        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
            $user->foto = null;
            $user->save();

            NotificationService::send('perfil.foto_removida', $user->id, [
                'nome' => $user->nome,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto removida com sucesso'
        ]);
    }

    /**
     * GET /prestador/perfil/stats - Estatísticas
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $profile = $user->prestadorProfile;

        $servicosCount = Servico::where('prestador_id', $user->id)->where('ativo', true)->count();
        $pedidosPendentes = $user->pedidosComoPrestador()
            ->whereIn('status', ['pendente', 'aceito', 'em_andamento'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'servicos' => $servicosCount,
                'pedidos_pendentes' => $pedidosPendentes,
                'avaliacao_media' => $profile ? (float) $profile->media_avaliacao : 0,
            ]
        ]);
    }

    /**
     * GET /prestador/perfil/categorias - Listar categorias
     */
    public function getCategorias(Request $request)
    {
        $user = $request->user();

        $categorias = $user->categorias()
            ->where('ativo', true)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'icone' => $c->icone ?? 'category',
                'cor' => $c->cor ?? '#5B4BF5',
            ]);

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    /**
     * POST /prestador/perfil/categorias - Adicionar categoria
     */
    public function addCategoria(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'categoria_id' => 'required|exists:categorias,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $categoria = \App\Models\Categoria::find($request->categoria_id);

        if (!$user->categorias()->where('categoria_id', $request->categoria_id)->exists()) {
            $user->categorias()->attach($request->categoria_id);

            NotificationService::send('categoria.adicionada', $user->id, [
                'categoria' => $categoria->nome,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categoria adicionada com sucesso'
        ]);
    }

    /**
     * DELETE /prestador/perfil/categorias/{id} - Remover categoria
     */
    public function removeCategoria(Request $request, $categoriaId)
    {
        $user = $request->user();

        $categoria = \App\Models\Categoria::find($categoriaId);
        $categoriaNome = $categoria ? $categoria->nome : 'Categoria';

        $user->categorias()->detach($categoriaId);

        NotificationService::send('categoria.removida', $user->id, [
            'categoria' => $categoriaNome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Categoria removida com sucesso'
        ]);
    }

    /**
     * GET /prestador/perfil/disponibilidade - Buscar disponibilidade
     */
    public function getDisponibilidade(Request $request)
    {
        $user = $request->user();
        $profile = $user->prestadorProfile;

        return response()->json([
            'success' => true,
            'data' => $profile ? ($profile->disponibilidade ?? null) : null
        ]);
    }

    /**
     * PUT /prestador/perfil/disponibilidade - Atualizar disponibilidade
     */
    public function updateDisponibilidade(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'horarios_padrao' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = $user->prestadorProfile()->firstOrCreate(['user_id' => $user->id]);

        $disponibilidade = $profile->disponibilidade ?? [];
        if ($request->has('horarios_padrao')) {
            $disponibilidade['horarios_padrao'] = $request->horarios_padrao;
        }

        $profile->disponibilidade = $disponibilidade;
        $profile->save();

        NotificationService::send('disponibilidade.atualizada', $user->id, [
            'nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidade atualizada com sucesso',
            'data' => $profile->disponibilidade
        ]);
    }

    // ==========================================
    // ⭐ PORTFOLIO - MÉTODOS COMPLETOS CORRIGIDOS
    // ==========================================

    /**
     * Helper: Retorna perfil completo (CORRIGIDO)
     */
    private function getPerfilCompleto(User $user): array
    {
        $profile = $user->prestadorProfile;
        $portfolio = $profile ? ($profile->portfolio ?? []) : [];
        $portfolioUrls = $this->processPortfolioUrls($portfolio);

        return [
            'id' => $user->id,
            'nome' => $user->nome,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'foto' => $this->getImageUrl($user->foto), // 🔥 CORRIGIDO
            'profissao' => $user->profissao,
            'sobre' => $user->sobre,
            'latitude' => $user->latitude ? (float) $user->latitude : null,
            'longitude' => $user->longitude ? (float) $user->longitude : null,
            'raio_atendimento' => $user->raio_atendimento ?? 10,
            'disponivel' => $user->disponivel ?? true,
            'verificado' => $user->verificado ?? false,
            'media_avaliacao' => $user->media_avaliacao ? (float) $user->media_avaliacao : 0,
            'total_avaliacoes' => $user->total_avaliacoes ?? 0,
            'endereco' => $profile ? $profile->endereco : null,
            'portfolio' => $portfolioUrls,
            'disponibilidade' => $profile ? ($profile->disponibilidade ?? null) : null,
            'documento_verificado' => $profile ? ($profile->status_documento === 'aprovado') : false,
            'status_documento' => $profile ? ($profile->status_documento ?? 'pendente') : 'pendente',
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * GET /prestador/portfolio - Buscar todas as fotos do portfólio (CORRIGIDO)
     */
    public function getPortfolio(Request $request)
    {
        $user = $request->user();
        $profile = $user->prestadorProfile;

        $portfolio = $profile ? ($profile->portfolio ?? []) : [];

        $portfolioItems = [];
        foreach ($portfolio as $index => $item) {
            if (is_string($item)) {
                $path = ltrim($item, '/');
                $portfolioItems[] = [
                    'id' => $index + 1,
                    'url' => $this->getImageUrl($path), // 🔥 CORRIGIDO
                    'path' => $path,
                    'titulo' => '',
                    'descricao' => '',
                    'created_at' => now()->toISOString()
                ];
            } else {
                if (isset($item['path'])) {
                    $item['url'] = $this->getImageUrl($item['path']); // 🔥 CORRIGIDO
                } elseif (isset($item['url'])) {
                    $path = $this->extractPathFromUrl($item['url']);
                    $item['url'] = $this->getImageUrl($path); // 🔥 CORRIGIDO
                }
                $portfolioItems[] = $item;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $portfolioItems
        ]);
    }

    /**
     * POST /prestador/portfolio - Adicionar foto ao portfólio (CORRIGIDO)
     */
    public function addPortfolio(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'titulo' => 'nullable|string|max:255',
            'descricao' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = $user->prestadorProfile()->firstOrCreate(['user_id' => $user->id]);

        $file = $request->file('foto');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(40) . '.' . $extension;

        // 🔥 SALVA NA PASTA CORRETA: portfolio/{prestador_id}/
        $path = $file->storeAs('portfolio/' . $user->id, $filename, 'public');

        $portfolio = $profile->portfolio ?? [];

        $newItem = [
            'id' => time() . rand(1000, 9999),
            'url' => $this->getImageUrl($path), // 🔥 CORRIGIDO
            'path' => $path,
            'titulo' => $request->input('titulo', ''),
            'descricao' => $request->input('descricao', ''),
            'created_at' => now()->toISOString()
        ];

        $portfolio[] = $newItem;
        $profile->portfolio = $portfolio;
        $profile->save();

        NotificationService::send('portfolio.foto_adicionada', $user->id, [
            'nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto adicionada ao portfólio',
            'data' => $newItem
        ]);
    }

    /**
     * PUT /prestador/portfolio/{id} - Atualizar título/descrição do portfólio
     */
    public function updatePortfolio(Request $request, $id)
    {
        $user = $request->user();
        $profile = $user->prestadorProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'nullable|string|max:255',
            'descricao' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $portfolio = $profile->portfolio ?? [];
        $itemEncontrado = null;
        $indexEncontrado = -1;

        foreach ($portfolio as $index => $item) {
            if (isset($item['id']) && (string) $item['id'] === (string) $id) {
                $itemEncontrado = $item;
                $indexEncontrado = $index;
                break;
            }
        }

        if ($indexEncontrado === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Item não encontrado no portfólio'
            ], 404);
        }

        $portfolio[$indexEncontrado]['titulo'] = $request->input('titulo', $itemEncontrado['titulo'] ?? '');
        $portfolio[$indexEncontrado]['descricao'] = $request->input('descricao', $itemEncontrado['descricao'] ?? '');

        $profile->portfolio = $portfolio;
        $profile->save();

        NotificationService::send('portfolio.atualizado', $user->id, [
            'nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Portfólio atualizado com sucesso',
            'data' => $portfolio[$indexEncontrado]
        ]);
    }

    /**
     * DELETE /prestador/portfolio/{id} - Remover foto do portfólio por ID
     */
    public function removePortfolio(Request $request, $id)
    {
        $user = $request->user();

        $profile = $user->prestadorProfile;
        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil não encontrado'
            ], 404);
        }

        $portfolio = $profile->portfolio ?? [];

        $itemEncontrado = null;
        $indexEncontrado = -1;

        foreach ($portfolio as $index => $item) {
            if (isset($item['id']) && (string) $item['id'] === (string) $id) {
                $itemEncontrado = $item;
                $indexEncontrado = $index;
                break;
            }
        }

        if ($indexEncontrado === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Foto não encontrada no portfólio'
            ], 404);
        }

        if (isset($itemEncontrado['path'])) {
            $cleanPath = $this->cleanStoragePath($itemEncontrado['path']);
            if (Storage::disk('public')->exists($cleanPath)) {
                Storage::disk('public')->delete($cleanPath);
            }
        }

        array_splice($portfolio, $indexEncontrado, 1);
        $profile->portfolio = $portfolio;
        $profile->save();

        NotificationService::send('portfolio.foto_removida', $user->id, [
            'nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto removida do portfólio'
        ]);
    }

    // ==========================================
    // SERVIÇOS
    // ==========================================

    /**
     * POST /prestador/servicos - Criar serviço
     */
    public function storeServico(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'preco' => 'required|numeric|min:0',
            'duracao' => 'required|integer|min:5',
            'icone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $servico = Servico::create([
            'prestador_id' => $user->id,
            'nome' => $request->nome,
            'descricao' => $request->descricao,
            'preco' => $request->preco,
            'duracao' => $request->duracao,
            'icone' => $request->icone ?? 'handyman',
            'ativo' => true,
        ]);

        NotificationService::send('servico.criado', $user->id, [
            'nome' => $servico->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serviço criado com sucesso',
            'data' => $servico
        ], 201);
    }

    /**
     * PUT /prestador/servicos/{id} - Atualizar serviço
     */
    public function updateServico(Request $request, $id)
    {
        $user = $request->user();

        $servico = Servico::where('prestador_id', $user->id)->where('id', $id)->first();

        if (!$servico) {
            return response()->json([
                'success' => false,
                'message' => 'Serviço não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255',
            'descricao' => 'nullable|string',
            'preco' => 'sometimes|numeric|min:0',
            'duracao' => 'sometimes|integer|min:5',
            'icone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $servico->update($request->only(['nome', 'descricao', 'preco', 'duracao', 'icone']));

        NotificationService::send('servico.atualizado', $user->id, [
            'nome' => $servico->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serviço atualizado com sucesso',
            'data' => $servico
        ]);
    }

    /**
     * DELETE /prestador/servicos/{id} - Remover serviço
     */
    public function destroyServico(Request $request, $id)
    {
        $user = $request->user();

        $servico = Servico::where('prestador_id', $user->id)->where('id', $id)->first();

        if (!$servico) {
            return response()->json([
                'success' => false,
                'message' => 'Serviço não encontrado'
            ], 404);
        }

        $servicoNome = $servico->nome;
        $servico->delete();

        NotificationService::send('servico.removido', $user->id, [
            'nome' => $servicoNome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serviço removido com sucesso'
        ]);
    }

    /**
     * GET /prestador/servicos - Listar serviços
     */
    public function listServicos(Request $request)
    {
        $user = $request->user();

        $servicos = Servico::where('prestador_id', $user->id)
            ->where('ativo', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $servicos
        ]);
    }

    /**
     * DELETE /api/prestador/perfil/conta - Excluir conta
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        $profile = $user->prestadorProfile;
        if ($profile && $profile->portfolio) {
            foreach ($profile->portfolio as $item) {
                if (isset($item['path'])) {
                    $cleanPath = $this->cleanStoragePath($item['path']);
                    if (Storage::disk('public')->exists($cleanPath)) {
                        Storage::disk('public')->delete($cleanPath);
                    }
                }
            }
        }

        if ($profile) {
            $profile->delete();
        }

        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
        }

        $user->enderecos()->delete();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conta excluída com sucesso'
        ]);
    }
}
