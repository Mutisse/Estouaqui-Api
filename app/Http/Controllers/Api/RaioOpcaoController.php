<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RaioOpcao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RaioOpcaoController extends Controller
{
    /**
     * Listar todas as opções de raio - COM CACHE
     * GET /api/raio-opcoes
     */
    public function index()
    {
        $opcoes = Cache::remember('raio_opcoes_all', 3600, function() {
            return RaioOpcao::where('ativo', true)
                ->orderBy('ordem')
                ->get()
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $opcoes
        ]);
    }

    /**
     * Listar opções para select - COM CACHE
     * GET /api/raio-opcoes/options
     */
    public function options()
    {
        $options = Cache::remember('raio_opcoes_options', 3600, function() {
            $opcoes = RaioOpcao::where('ativo', true)
                ->orderBy('ordem')
                ->get();

            return $opcoes->map(function ($opcao) {
                return [
                    'label' => $opcao->label,
                    'value' => $opcao->valor
                ];
            })->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * Criar nova opção de raio (Admin)
     * POST /api/admin/raio-opcoes
     */
    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'valor' => 'required|integer|min:1',
            'label' => 'required|string|max:50',
            'ordem' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $opcao = RaioOpcao::create([
                'valor' => $request->valor,
                'label' => $request->label,
                'ordem' => $request->ordem ?? $request->valor,
                'ativo' => true,
            ]);

            // Limpar cache
            Cache::forget('raio_opcoes_all');
            Cache::forget('raio_opcoes_options');

            return response()->json([
                'success' => true,
                'message' => 'Opção de raio criada com sucesso',
                'data' => $opcao
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar opção de raio'
            ], 500);
        }
    }

    /**
     * Atualizar opção de raio (Admin)
     * PUT /api/admin/raio-opcoes/{id}
     */
    public function update(Request $request, $id)
    {
        $opcao = RaioOpcao::find($id);

        if (!$opcao) {
            return response()->json([
                'success' => false,
                'error' => 'Opção de raio não encontrada'
            ], 404);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'valor' => 'sometimes|integer|min:1',
            'label' => 'sometimes|string|max:50',
            'ordem' => 'nullable|integer',
            'ativo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('valor')) $opcao->valor = $request->valor;
            if ($request->has('label')) $opcao->label = $request->label;
            if ($request->has('ordem')) $opcao->ordem = $request->ordem;
            if ($request->has('ativo')) $opcao->ativo = $request->ativo;

            $opcao->save();

            // Limpar cache
            Cache::forget('raio_opcoes_all');
            Cache::forget('raio_opcoes_options');

            return response()->json([
                'success' => true,
                'message' => 'Opção de raio atualizada',
                'data' => $opcao
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar opção de raio'
            ], 500);
        }
    }

    /**
     * Deletar opção de raio (Admin)
     * DELETE /api/admin/raio-opcoes/{id}
     */
    public function destroy($id)
    {
        $opcao = RaioOpcao::find($id);

        if (!$opcao) {
            return response()->json([
                'success' => false,
                'error' => 'Opção de raio não encontrada'
            ], 404);
        }

        try {
            $opcao->delete();

            // Limpar cache
            Cache::forget('raio_opcoes_all');
            Cache::forget('raio_opcoes_options');

            return response()->json([
                'success' => true,
                'message' => 'Opção de raio removida'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover opção de raio'
            ], 500);
        }
    }
}
