<?php
// app/Http/Controllers/Api/PrestadorPreferenciaController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class PrestadorPreferenciaController extends BaseController
{
    /**
     * GET /api/prestador/preferencias
     * Buscar preferências do prestador
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso permitido apenas para prestadores'
            ], 403);
        }

        // Buscar configurações salvas no campo configuracoes do user
        $configuracoes = $user->configuracoes ?? [];

        // Buscar dados de pagamento
        $pagamentos = $user->pagamentos ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'notificacoes_push' => $configuracoes['notificacoes_push'] ?? true,
                'notificacoes_sms' => $configuracoes['notificacoes_sms'] ?? false,
                'notificacoes_email' => $configuracoes['notificacoes_email'] ?? true,
                'aceitar_automatico' => $configuracoes['aceitar_automatico'] ?? false,
                'modo_nao_perturbe' => $configuracoes['modo_nao_perturbe'] ?? false,
                'perfil_publico' => $configuracoes['perfil_publico'] ?? true,
                'mostrar_localizacao' => $configuracoes['mostrar_localizacao'] ?? true,
                'mpesa_configurado' => isset($pagamentos['mpesa_numero']),
                'mpesa_numero' => $pagamentos['mpesa_numero'] ?? null,
                'mpesa_nome' => $pagamentos['mpesa_nome'] ?? null,
                'conta_configurada' => isset($pagamentos['conta_banco']),
                'conta_banco' => $pagamentos['conta_banco'] ?? null,
                'conta_numero' => $pagamentos['conta_numero'] ?? null,
                'conta_titular' => $pagamentos['conta_titular'] ?? null,
                'idioma' => $configuracoes['idioma'] ?? 'pt',
            ]
        ]);
    }

    /**
     * PUT /api/prestador/preferencias
     * Atualizar preferências do prestador
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso permitido apenas para prestadores'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'notificacoes_push' => 'sometimes|boolean',
            'notificacoes_sms' => 'sometimes|boolean',
            'notificacoes_email' => 'sometimes|boolean',
            'aceitar_automatico' => 'sometimes|boolean',
            'modo_nao_perturbe' => 'sometimes|boolean',
            'perfil_publico' => 'sometimes|boolean',
            'mostrar_localizacao' => 'sometimes|boolean',
            'idioma' => 'sometimes|string|in:pt,en,fr',
            'mpesa_numero' => 'nullable|string',
            'mpesa_nome' => 'nullable|string',
            'mpesa_configurado' => 'sometimes|boolean',
            'conta_banco' => 'nullable|string',
            'conta_numero' => 'nullable|string',
            'conta_titular' => 'nullable|string',
            'conta_configurada' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Atualizar configurações gerais
        $configuracoes = $user->configuracoes ?? [];
        $configuracoesAtualizadas = array_merge($configuracoes, $request->only([
            'notificacoes_push', 'notificacoes_sms', 'notificacoes_email',
            'aceitar_automatico', 'modo_nao_perturbe', 'perfil_publico',
            'mostrar_localizacao', 'idioma'
        ]));

        $user->configuracoes = $configuracoesAtualizadas;

        // Atualizar dados de pagamento
        $pagamentos = $user->pagamentos ?? [];
        $pagamentosAtualizados = $pagamentos;

        if ($request->has('mpesa_numero')) {
            $pagamentosAtualizados['mpesa_numero'] = $request->mpesa_numero;
            $pagamentosAtualizados['mpesa_nome'] = $request->mpesa_nome;
        }

        if ($request->has('mpesa_configurado')) {
            if (!$request->mpesa_configurado) {
                unset($pagamentosAtualizados['mpesa_numero']);
                unset($pagamentosAtualizados['mpesa_nome']);
            }
        }

        if ($request->has('conta_banco')) {
            $pagamentosAtualizados['conta_banco'] = $request->conta_banco;
            $pagamentosAtualizados['conta_numero'] = $request->conta_numero;
            $pagamentosAtualizados['conta_titular'] = $request->conta_titular;
        }

        if ($request->has('conta_configurada')) {
            if (!$request->conta_configurada) {
                unset($pagamentosAtualizados['conta_banco']);
                unset($pagamentosAtualizados['conta_numero']);
                unset($pagamentosAtualizados['conta_titular']);
            }
        }

        $user->pagamentos = $pagamentosAtualizados;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Preferências atualizadas com sucesso',
            'data' => [
                'configuracoes' => $user->configuracoes,
                'pagamentos' => $user->pagamentos,
            ]
        ]);
    }

    /**
     * DELETE /api/prestador/preferencias/mpesa
     * Remover configuração do M-Pesa
     */
    public function removerMpesa(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso permitido apenas para prestadores'
            ], 403);
        }

        $pagamentos = $user->pagamentos ?? [];
        unset($pagamentos['mpesa_numero']);
        unset($pagamentos['mpesa_nome']);

        $user->pagamentos = $pagamentos;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuração do M-Pesa removida com sucesso'
        ]);
    }

    /**
     * DELETE /api/prestador/preferencias/conta
     * Remover configuração da conta bancária
     */
    public function removerConta(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso permitido apenas para prestadores'
            ], 403);
        }

        $pagamentos = $user->pagamentos ?? [];
        unset($pagamentos['conta_banco']);
        unset($pagamentos['conta_numero']);
        unset($pagamentos['conta_titular']);

        $user->pagamentos = $pagamentos;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuração da conta bancária removida com sucesso'
        ]);
    }
}
