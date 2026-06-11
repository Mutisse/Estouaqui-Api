<?php
// app/Http/Controllers/Api/PrestadorServicoController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Servico;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;

class PrestadorServicoController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $servicos = Servico::where('prestador_id', $user->id)
            ->with('categoria')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $servicos
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'categoria_id' => 'required|exists:categorias,id',
            'duracao' => 'required|integer|min:15',
            'preco_base' => 'required|numeric|min:0',
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
            'categoria_id' => $request->categoria_id,
            'duracao' => $request->duracao,
            'preco_base' => $request->preco_base,
            'ativo' => true,
        ]);

        // 🔔 NOTIFICAÇÃO: Serviço criado
        NotificationService::send('servico.criado', $user->id, [
            'nome' => $servico->nome,
            'servico_id' => $servico->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serviço criado com sucesso',
            'data' => $servico
        ], 201);
    }

    public function update($id, Request $request)
    {
        $user = $request->user();

        $servico = Servico::where('prestador_id', $user->id)->find($id);

        if (!$servico) {
            return response()->json([
                'success' => false,
                'message' => 'Serviço não encontrado'
            ], 404);
        }

        $servico->update($request->only([
            'nome', 'descricao', 'categoria_id', 'duracao', 'preco_base', 'ativo'
        ]));

        // 🔔 NOTIFICAÇÃO: Serviço atualizado
        NotificationService::send('servico.atualizado', $user->id, [
            'nome' => $servico->nome,
            'servico_id' => $servico->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serviço atualizado com sucesso',
            'data' => $servico
        ]);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();

        $servico = Servico::where('prestador_id', $user->id)->find($id);

        if (!$servico) {
            return response()->json([
                'success' => false,
                'message' => 'Serviço não encontrado'
            ], 404);
        }

        $nomeServico = $servico->nome;
        $servico->delete();

        // 🔔 NOTIFICAÇÃO: Serviço removido
        NotificationService::send('servico.removido', $user->id, [
            'nome' => $nomeServico,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serviço removido com sucesso'
        ]);
    }
}
