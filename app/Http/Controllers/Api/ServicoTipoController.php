<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicoTipo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Notifications\DynamicNotification;

class ServicoTipoController extends Controller
{
    public function index()
    {
        $tipos = Cache::remember('servico_tipos_all', 3600, function () {
            return ServicoTipo::where('ativo', true)
                ->orderBy('ordem')
                ->get()
                ->map(function ($tipo) {
                    return [
                        'id' => $tipo->id,
                        'nome' => $tipo->nome,
                        'slug' => $tipo->slug,
                        'icone' => $tipo->icone,
                        'cor' => $tipo->cor,
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $tipos
        ]);
    }

    public function options()
    {
        $options = Cache::remember('servico_tipos_options', 3600, function () {
            return ServicoTipo::where('ativo', true)
                ->orderBy('ordem')
                ->get()
                ->map(function ($tipo) {
                    return [
                        'label' => $tipo->nome,
                        'value' => $tipo->slug,
                        'icone' => $tipo->icone,
                        'cor' => $tipo->cor
                    ];
                })
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * Criar novo tipo de serviço (admin)
     * POST /api/admin/servico-tipos
     */
    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nome' => 'required|string|max:255|unique:servico_tipos',
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
            'cor' => 'nullable|string',
            'ordem' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $tipo = ServicoTipo::create([
                'nome' => $request->nome,
                'slug' => \Illuminate\Support\Str::slug($request->nome),
                'descricao' => $request->descricao,
                'icone' => $request->icone ?? 'category',
                'cor' => $request->cor ?? 'primary',
                'ordem' => $request->ordem ?? 0,
                'ativo' => true,
            ]);

            // ✅ NOTIFICAÇÃO: Novo tipo de serviço para PRESTADORES
            $prestadores = User::where('tipo', 'prestador')->get();
            foreach ($prestadores as $prestador) {
                $prestador->notify(new DynamicNotification('novo_tipo_servico', [
                    'tipo_nome' => $tipo->nome,
                    'tipo_id' => $tipo->id,
                    'data_criacao' => now()->format('d/m/Y'),
                ]));
            }
            Log::info("Notificação 'novo_tipo_servico' enviada para " . count($prestadores) . " prestadores");

            $this->clearServicoTipoCache();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de serviço criado com sucesso',
                'data' => $tipo
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar tipo de serviço: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar tipo de serviço'
            ], 500);
        }
    }

    /**
     * Atualizar tipo de serviço (admin)
     * PUT /api/admin/servico-tipos/{id}
     */
    public function update(Request $request, $id)
    {
        $tipo = ServicoTipo::find($id);

        if (!$tipo) {
            return response()->json([
                'success' => false,
                'error' => 'Tipo de serviço não encontrado'
            ], 404);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255|unique:servico_tipos,nome,' . $id,
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
            'cor' => 'nullable|string',
            'ordem' => 'nullable|integer',
            'ativo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $ativoAnterior = $tipo->ativo;

        try {
            if ($request->has('nome')) {
                $tipo->nome = $request->nome;
                $tipo->slug = \Illuminate\Support\Str::slug($request->nome);
            }
            if ($request->has('descricao')) $tipo->descricao = $request->descricao;
            if ($request->has('icone')) $tipo->icone = $request->icone;
            if ($request->has('cor')) $tipo->cor = $request->cor;
            if ($request->has('ordem')) $tipo->ordem = $request->ordem;
            if ($request->has('ativo')) $tipo->ativo = $request->ativo;

            $tipo->save();

            // ✅ NOTIFICAÇÃO: Tipo de serviço atualizado (se status mudou)
            if ($ativoAnterior != $tipo->ativo) {
                $prestadores = User::where('tipo', 'prestador')->get();
                $statusTexto = $tipo->ativo ? 'disponível' : 'indisponível';
                foreach ($prestadores as $prestador) {
                    $prestador->notify(new DynamicNotification('tipo_servico_atualizado', [
                        'tipo_nome' => $tipo->nome,
                        'status' => $statusTexto,
                        'tipo_id' => $tipo->id,
                    ]));
                }
                Log::info("Notificação 'tipo_servico_atualizado' enviada para " . count($prestadores) . " prestadores");
            }

            $this->clearServicoTipoCache();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de serviço atualizado com sucesso',
                'data' => $tipo
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar tipo de serviço: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar tipo de serviço'
            ], 500);
        }
    }

    /**
     * Deletar tipo de serviço (admin)
     * DELETE /api/admin/servico-tipos/{id}
     */
    public function destroy($id)
    {
        $tipo = ServicoTipo::find($id);

        if (!$tipo) {
            return response()->json([
                'success' => false,
                'error' => 'Tipo de serviço não encontrado'
            ], 404);
        }

        $tipoNome = $tipo->nome;
        $tipo->delete();

        // ✅ NOTIFICAÇÃO: Tipo de serviço removido para PRESTADORES
        $prestadores = User::where('tipo', 'prestador')->get();
        foreach ($prestadores as $prestador) {
            $prestador->notify(new DynamicNotification('tipo_servico_removido', [
                'tipo_nome' => $tipoNome,
                'data_remocao' => now()->format('d/m/Y'),
            ]));
        }
        Log::info("Notificação 'tipo_servico_removido' enviada para " . count($prestadores) . " prestadores");

        $this->clearServicoTipoCache();

        return response()->json([
            'success' => true,
            'message' => 'Tipo de serviço removido com sucesso'
        ]);
    }

    /**
     * Limpar cache dos tipos de serviço
     */
    private function clearServicoTipoCache()
    {
        Cache::forget('servico_tipos_all');
        Cache::forget('servico_tipos_options');
    }
}
