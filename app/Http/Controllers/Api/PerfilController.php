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

class PerfilController extends BaseController
{
    /**
     * GET /api/perfil
     * Obter dados do perfil do usuário logado (INCLUINDO ENDEREÇO)
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Buscar o endereço principal do usuário (ou o primeiro endereço)
        $enderecoPrincipal = $user->enderecos()
            ->where('principal', true)
            ->first();

        // Se não tiver endereço principal, pega o primeiro endereço
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
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'created_at' => $user->created_at,
                'profissao' => $user->profissao,
                'sobre' => $user->sobre,
                'media_avaliacao' => $user->media_avaliacao,
                'total_avaliacoes' => $user->total_avaliacoes,
                // ✅ ADICIONADO: Endereço do usuário
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

    /**
     * PUT /api/perfil
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
            // ✅ VALIDAÇÃO DO ENDEREÇO
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

        // ✅ ATUALIZAR ENDEREÇO
        if ($request->has('endereco') && $request->endereco) {
            $enderecoData = $request->endereco;

            // Buscar endereço principal ou criar um novo
            $endereco = $user->enderecos()->where('principal', true)->first();

            if (!$endereco) {
                // Se não existir endereço, cria um novo como principal
                $endereco = $user->enderecos()->create(array_merge($enderecoData, ['principal' => true]));
            } else {
                // Atualiza o endereço existente
                $endereco->update($enderecoData);
            }
        }

        NotificationService::send('sistema.perfil_atualizado', $user->id, [
            'nome' => $user->nome
        ]);

        // Retornar o perfil atualizado COM O ENDEREÇO
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
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'profissao' => $user->profissao,
                'sobre' => $user->sobre,
                // ✅ RETORNAR ENDEREÇO ATUALIZADO
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

    /**
     * POST /api/perfil/foto
     * Atualizar foto do perfil
     */
    public function uploadFoto(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'foto' => 'required|image|max:5120|mimes:jpeg,png,jpg,gif'
        ]);

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
            'data' => [
                'foto' => asset('storage/' . $path)
            ]
        ]);
    }

    /**
     * DELETE /api/perfil/foto
     * Remover foto do perfil
     */
    public function removerFoto(Request $request)
    {
        $user = $request->user();

        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
            $user->foto = null;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto removida com sucesso!'
        ]);
    }

    /**
     * GET /api/perfil/dashboard
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

    // ===================== ENDEREÇOS (ADAPTADO PARA MOÇAMBIQUE) =====================

    /**
     * GET /api/perfil/enderecos
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
     * POST /api/perfil/enderecos
     * Adicionar novo endereço (adaptado para Moçambique)
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
     * PUT /api/perfil/enderecos/{id}
     * Atualizar endereço (adaptado para Moçambique)
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
     * PUT /api/perfil/enderecos/{id}/principal
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
     * DELETE /api/perfil/enderecos/{id}
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

    // ===================== CONFIGURAÇÕES =====================

    /**
     * GET /api/perfil/configuracoes
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
     * PUT /api/perfil/configuracoes
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
