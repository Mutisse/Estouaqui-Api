<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class ServicoController extends Controller
{
    /**
     * Listar serviços (admin) - COM CACHE
     * GET /api/admin/servicos
     */
    public function index(Request $request)
    {
        $cacheKey = 'admin_servicos_' . md5($request->fullUrl());

        $servicos = Cache::remember($cacheKey, 300, function () use ($request) {
            $query = Servico::with(['prestador:id,nome,foto,telefone', 'categoria:id,nome,icone,cor']);

            if ($request->has('categoria_id')) {
                $query->where('categoria_id', $request->categoria_id);
            }

            if ($request->has('prestador_id')) {
                $query->where('prestador_id', $request->prestador_id);
            }

            if ($request->has('ativo')) {
                $query->where('ativo', $request->ativo);
            }

            return $query->orderBy('created_at', 'desc')->paginate(20);
        });

        return response()->json([
            'success' => true,
            'data' => $servicos
        ]);
    }

    /**
     * Mostrar um serviço - COM CACHE
     * GET /api/servicos/{id}
     */
    public function show($id)
    {
        $cacheKey = "servico_detalhes_{$id}";

        $servico = Cache::remember($cacheKey, 3600, function () use ($id) {
            return Servico::with(['prestador:id,nome,foto,telefone,media_avaliacao', 'categoria:id,nome,icone,cor', 'pedidos' => function ($q) {
                $q->latest()->limit(5);
            }])->find($id);
        });

        if (!$servico) {
            return response()->json([
                'success' => false,
                'error' => 'Serviço não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $servico
        ]);
    }

    /**
     * Criar serviço (admin) - LIMPAR CACHE
     * POST /api/admin/servicos
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prestador_id' => 'required|exists:users,id',
            'categoria_id' => 'required|exists:categorias,id',
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'preco' => 'required|numeric|min:0',
            'duracao' => 'required|integer|min:5',
            'icone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $servico = Servico::create([
                'prestador_id' => $request->prestador_id,
                'categoria_id' => $request->categoria_id,
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'preco' => $request->preco,
                'duracao' => $request->duracao,
                'icone' => $request->icone ?? 'handyman',
                'ativo' => true,
            ]);

            $this->clearServicoCache($servico->id, $request->prestador_id);

            return response()->json([
                'success' => true,
                'message' => 'Serviço criado com sucesso',
                'data' => $servico
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar serviço'
            ], 500);
        }
    }

    /**
     * Atualizar serviço - LIMPAR CACHE
     * PUT /api/admin/servicos/{id}
     */
    public function update(Request $request, $id)
    {
        $servico = Servico::find($id);

        if (!$servico) {
            return response()->json([
                'success' => false,
                'error' => 'Serviço não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255',
            'categoria_id' => 'sometimes|exists:categorias,id',
            'preco' => 'sometimes|numeric|min:0',
            'duracao' => 'sometimes|integer|min:5',
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
            'ativo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('nome')) $servico->nome = $request->nome;
            if ($request->has('categoria_id')) $servico->categoria_id = $request->categoria_id;
            if ($request->has('preco')) $servico->preco = $request->preco;
            if ($request->has('duracao')) $servico->duracao = $request->duracao;
            if ($request->has('descricao')) $servico->descricao = $request->descricao;
            if ($request->has('icone')) $servico->icone = $request->icone;
            if ($request->has('ativo')) $servico->ativo = $request->ativo;

            $servico->save();

            $this->clearServicoCache($id, $servico->prestador_id);

            return response()->json([
                'success' => true,
                'message' => 'Serviço atualizado com sucesso',
                'data' => $servico
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar serviço'
            ], 500);
        }
    }

    /**
     * Deletar serviço - LIMPAR CACHE
     * DELETE /api/admin/servicos/{id}
     */
    public function destroy($id)
    {
        $servico = Servico::find($id);

        if (!$servico) {
            return response()->json([
                'success' => false,
                'error' => 'Serviço não encontrado'
            ], 404);
        }

        $prestadorId = $servico->prestador_id;
        $servico->delete();

        $this->clearServicoCache($id, $prestadorId);

        return response()->json([
            'success' => true,
            'message' => 'Serviço removido com sucesso'
        ]);
    }

    /**
     * Limpar cache do serviço
     */
    private function clearServicoCache($servicoId, $prestadorId)
    {
        Cache::forget("servico_detalhes_{$servicoId}");
        Cache::forget("prestador_servicos_{$prestadorId}");

        // Limpar cache de listagens (várias páginas)
        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("admin_servicos_page_{$page}");
        }
    }
}
