<?php
// app/Http/Controllers/Api/PrestadorPerfilController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Servico;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PrestadorPerfilController extends BaseController
{
    /**
     * GET /prestador/perfil - Buscar perfil completo
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $profile = $user->prestadorProfile;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'profissao' => $profile ? $profile->profissao : null,
                'sobre' => $profile ? $profile->sobre : null,
                'media_avaliacao' => $profile ? $profile->media_avaliacao : 0,
                'total_avaliacoes' => $profile ? $profile->total_avaliacoes : 0,
                'portfolio' => $profile ? ($profile->portfolio ?? []) : [],
                'disponibilidade' => $profile ? ($profile->disponibilidade ?? null) : null,
                'documento_verificado' => $profile ? ($profile->status_documento === 'aprovado') : false,
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Atualizar dados do user
        $userData = $request->only(['nome', 'email', 'telefone']);
        if (!empty($userData)) {
            $user->update($userData);
        }

        // Atualizar dados do profile
        $profile = $user->prestadorProfile()->firstOrCreate(['user_id' => $user->id]);
        $profileData = $request->only(['profissao', 'sobre']);
        if (!empty($profileData)) {
            $profile->update($profileData);
        }

        // 🔔 NOTIFICAÇÃO: Perfil atualizado
        NotificationService::send('sistema.perfil_atualizado', $user->id, [
            'nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil atualizado com sucesso',
            'data' => $userData
        ]);
    }

    /**
     * POST /prestador/perfil/foto - Upload de foto de perfil
     */
    public function uploadFoto(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('foto')) {
            // Remover foto antiga
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }

            $path = $request->file('foto')->store('usuarios', 'public');
            $user->foto = $path;
            $user->save();

            // 🔔 NOTIFICAÇÃO: Foto atualizada
            NotificationService::send('perfil.foto_atualizada', $user->id, [
                'nome' => $user->nome,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto atualizada com sucesso',
                'data' => ['foto' => asset('storage/' . $path)]
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

            // 🔔 NOTIFICAÇÃO: Foto removida
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
                'avaliacao_media' => $profile ? $profile->media_avaliacao : 0,
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

            // 🔔 NOTIFICAÇÃO: Categoria adicionada
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

        // 🔔 NOTIFICAÇÃO: Categoria removida
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

        // 🔔 NOTIFICAÇÃO: Disponibilidade atualizada
        NotificationService::send('disponibilidade.atualizada', $user->id, [
            'nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidade atualizada com sucesso',
            'data' => $profile->disponibilidade
        ]);
    }

    /**
     * POST /prestador/perfil/portfolio - Adicionar foto ao portfólio
     */
    public function addPortfolio(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = $user->prestadorProfile()->firstOrCreate(['user_id' => $user->id]);

        $path = $request->file('foto')->store('portfolio/' . $user->id, 'public');

        $portfolio = $profile->portfolio ?? [];
        $portfolio[] = $path;
        $profile->portfolio = $portfolio;
        $profile->save();

        // 🔔 NOTIFICAÇÃO: Foto adicionada ao portfólio
        NotificationService::send('portfolio.foto_adicionada', $user->id, [
            'nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto adicionada ao portfólio',
            'data' => ['url' => asset('storage/' . $path)]
        ]);
    }

    /**
     * DELETE /prestador/perfil/portfolio - Remover foto do portfólio
     */
    public function removePortfolio(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'url' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = $user->prestadorProfile;
        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil não encontrado'
            ], 404);
        }

        $portfolio = $profile->portfolio ?? [];
        $url = $request->url;
        $path = str_replace(asset('storage/'), '', $url);

        $index = array_search($path, $portfolio);
        if ($index !== false) {
            unset($portfolio[$index]);
            $profile->portfolio = array_values($portfolio);
            $profile->save();

            Storage::disk('public')->delete($path);

            // 🔔 NOTIFICAÇÃO: Foto removida do portfólio
            NotificationService::send('portfolio.foto_removida', $user->id, [
                'nome' => $user->nome,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto removida do portfólio'
        ]);
    }

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

        // 🔔 NOTIFICAÇÃO: Serviço criado
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

        // 🔔 NOTIFICAÇÃO: Serviço atualizado
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

        // 🔔 NOTIFICAÇÃO: Serviço removido
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
     * DELETE /api/prestador/perfil/conta
     * Excluir conta do prestador
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Excluir perfil do prestador
        if ($user->prestadorProfile) {
            $user->prestadorProfile->delete();
        }

        // Excluir endereços
        $user->enderecos()->delete();

        // Excluir tokens
        $user->tokens()->delete();

        // Excluir o usuário
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conta excluída com sucesso'
        ]);
    }
}
