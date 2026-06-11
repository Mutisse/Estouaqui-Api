<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminConfiguracoesController extends Controller
{
    /**
     * GET /admin/configuracoes/todas
     * Carrega todas as configurações diretamente da base de dados
     */
    public function getTodasConfiguracoes()
    {
        try {
            // Configurações gerais - direto do banco
            $gerais = Configuracoes::where('grupo', 'geral')
                ->where('ativo', true)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->chave => $this->convertValue($config->valor, $config->tipo)];
                });

            // Configurações de prestador - direto do banco
            $prestador = Configuracoes::where('grupo', 'prestador')
                ->where('ativo', true)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->chave => $this->convertValue($config->valor, $config->tipo)];
                });

            // Configurações de pagamento - direto do banco
            $pagamento = Configuracoes::where('grupo', 'pagamento')
                ->where('ativo', true)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->chave => $this->convertValue($config->valor, $config->tipo)];
                });

            // Opções - direto do banco
            $opcoes = [
                'raios' => Configuracoes::get('opcoes_raios', null, 'geral'),
                'dias_semana' => Configuracoes::get('opcoes_dias_semana', null, 'geral'),
                'documentos' => Configuracoes::get('opcoes_documentos', null, 'geral'),
                'fuso_horario' => Configuracoes::get('opcoes_fuso_horario', null, 'geral'),
                'moeda' => Configuracoes::get('opcoes_moeda', null, 'geral'),
                'criptografia' => Configuracoes::get('opcoes_criptografia', null, 'geral'),
                'modulos' => Configuracoes::get('opcoes_modulos', null, 'geral'),
            ];

            // Permissões - direto do banco
            $permissoes = [];
            if (Schema::hasTable('permissoes')) {
                $permissoes = DB::table('permissoes')
                    ->select('id', 'nome', 'descricao', 'modulo')
                    ->get();

                if (Schema::hasTable('permissao_role') && Schema::hasTable('roles')) {
                    foreach ($permissoes as $p) {
                        $p->roles = DB::table('permissao_role')
                            ->join('roles', 'permissao_role.role_id', '=', 'roles.id')
                            ->where('permissao_role.permissao_id', $p->id)
                            ->pluck('roles.nome')
                            ->toArray();
                    }
                }
            }

            // Roles - direto do banco
            $roles = [];
            if (Schema::hasTable('roles')) {
                $roles = DB::table('roles')->select('id', 'nome', 'descricao')->get();
                if (Schema::hasTable('permissao_role')) {
                    foreach ($roles as $role) {
                        $role->permissoes = DB::table('permissao_role')
                            ->where('role_id', $role->id)
                            ->pluck('permissao_id')
                            ->toArray();
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'configuracoes_gerais' => $gerais,
                    'configuracoes_prestador' => $prestador,
                    'configuracoes_pagamento' => $pagamento,
                    'opcoes' => $opcoes,
                    'permissoes' => $permissoes,
                    'roles' => $roles,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro em getTodasConfiguracoes: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao carregar configurações'], 500);
        }
    }

    /**
     * GET /admin/configuracoes/gerais
     */
    public function getConfiguracoesGerais()
    {
        try {
            $configuracoes = Configuracoes::where('grupo', 'geral')
                ->where('ativo', true)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->chave => $this->convertValue($config->valor, $config->tipo)];
                });

            return response()->json(['success' => true, 'data' => $configuracoes]);
        } catch (\Exception $e) {
            Log::error('Erro em getConfiguracoesGerais: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao carregar configurações'], 500);
        }
    }

    /**
     * GET /admin/configuracoes/prestador
     */
    public function getConfiguracoesPrestador()
    {
        try {
            $configuracoes = Configuracoes::where('grupo', 'prestador')
                ->where('ativo', true)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->chave => $this->convertValue($config->valor, $config->tipo)];
                });

            return response()->json(['success' => true, 'data' => $configuracoes]);
        } catch (\Exception $e) {
            Log::error('Erro em getConfiguracoesPrestador: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao carregar configurações'], 500);
        }
    }

    /**
     * GET /admin/configuracoes/pagamento
     */
    public function getConfiguracoesPagamento()
    {
        try {
            $configuracoes = Configuracoes::where('grupo', 'pagamento')
                ->where('ativo', true)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->chave => $this->convertValue($config->valor, $config->tipo)];
                });

            return response()->json(['success' => true, 'data' => $configuracoes]);
        } catch (\Exception $e) {
            Log::error('Erro em getConfiguracoesPagamento: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao carregar configurações'], 500);
        }
    }

    /**
     * GET /admin/permissoes
     */
    public function getPermissoes()
    {
        try {
            if (!Schema::hasTable('permissoes')) {
                return response()->json(['success' => false, 'message' => 'Tabela de permissões não encontrada'], 404);
            }

            $permissoes = DB::table('permissoes')
                ->select('id', 'nome', 'descricao', 'modulo')
                ->get();

            if (Schema::hasTable('permissao_role') && Schema::hasTable('roles')) {
                foreach ($permissoes as $permissao) {
                    $permissao->roles = DB::table('permissao_role')
                        ->join('roles', 'permissao_role.role_id', '=', 'roles.id')
                        ->where('permissao_role.permissao_id', $permissao->id)
                        ->pluck('roles.nome')
                        ->toArray();
                }
            } else {
                foreach ($permissoes as $permissao) {
                    $permissao->roles = [];
                }
            }

            return response()->json(['success' => true, 'data' => $permissoes]);
        } catch (\Exception $e) {
            Log::error('Erro em getPermissoes: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao carregar permissões'], 500);
        }
    }

    /**
     * GET /admin/roles
     */
    public function getRoles()
    {
        try {
            if (!Schema::hasTable('roles')) {
                return response()->json(['success' => false, 'message' => 'Tabela de papéis não encontrada'], 404);
            }

            $roles = DB::table('roles')->select('id', 'nome', 'descricao')->get();

            if (Schema::hasTable('permissao_role')) {
                foreach ($roles as $role) {
                    $role->permissoes = DB::table('permissao_role')
                        ->where('role_id', $role->id)
                        ->pluck('permissao_id')
                        ->toArray();
                }
            } else {
                foreach ($roles as $role) {
                    $role->permissoes = [];
                }
            }

            return response()->json(['success' => true, 'data' => $roles]);
        } catch (\Exception $e) {
            Log::error('Erro em getRoles: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao carregar papéis'], 500);
        }
    }

    /**
     * PUT /admin/configuracoes/gerais
     */
    public function atualizarConfiguracoesGerais(Request $request)
    {
        try {
            $configuracoes = $request->all();

            foreach ($configuracoes as $chave => $valor) {
                Configuracoes::set($chave, $valor, 'geral');
            }

            return response()->json(['success' => true, 'message' => 'Configurações gerais atualizadas com sucesso']);
        } catch (\Exception $e) {
            Log::error('Erro em atualizarConfiguracoesGerais: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao atualizar configurações'], 500);
        }
    }

    /**
     * PUT /admin/configuracoes/prestador
     */
    public function atualizarConfiguracoesPrestador(Request $request)
    {
        try {
            $configuracoes = $request->all();

            foreach ($configuracoes as $chave => $valor) {
                Configuracoes::set($chave, $valor, 'prestador');
            }

            return response()->json(['success' => true, 'message' => 'Configurações de prestador atualizadas com sucesso']);
        } catch (\Exception $e) {
            Log::error('Erro em atualizarConfiguracoesPrestador: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao atualizar configurações'], 500);
        }
    }

    /**
     * PUT /admin/configuracoes/pagamento
     */
    public function atualizarConfiguracoesPagamento(Request $request)
    {
        try {
            $configuracoes = $request->all();

            foreach ($configuracoes as $chave => $valor) {
                Configuracoes::set($chave, $valor, 'pagamento');
            }

            return response()->json(['success' => true, 'message' => 'Configurações de pagamento atualizadas com sucesso']);
        } catch (\Exception $e) {
            Log::error('Erro em atualizarConfiguracoesPagamento: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao atualizar configurações'], 500);
        }
    }

    /**
     * PUT /admin/permissoes/{id}
     */
    public function atualizarPermissao(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'role' => 'required|string',
                'ativar' => 'required|boolean'
            ]);

            $role = DB::table('roles')->where('nome', $validated['role'])->first();

            if (!$role) {
                return response()->json(['success' => false, 'message' => 'Papel não encontrado'], 404);
            }

            $permissao = DB::table('permissoes')->where('id', $id)->first();

            if (!$permissao) {
                return response()->json(['success' => false, 'message' => 'Permissão não encontrada'], 404);
            }

            if ($validated['ativar']) {
                DB::table('permissao_role')->updateOrInsert(
                    ['permissao_id' => $id, 'role_id' => $role->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            } else {
                DB::table('permissao_role')
                    ->where('permissao_id', $id)
                    ->where('role_id', $role->id)
                    ->delete();
            }

            return response()->json(['success' => true, 'message' => 'Permissão atualizada com sucesso']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro em atualizarPermissao: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao atualizar permissão'], 500);
        }
    }

    /**
     * PUT /admin/roles/{id}
     */
    public function atualizarRole(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'nome' => 'sometimes|string',
                'descricao' => 'nullable|string',
                'permissoes' => 'nullable|array',
                'permissoes.*' => 'exists:permissoes,id'
            ]);

            if (isset($validated['nome'])) {
                DB::table('roles')->where('id', $id)->update([
                    'nome' => $validated['nome'],
                    'descricao' => $validated['descricao'] ?? null,
                    'updated_at' => now()
                ]);
            }

            if (isset($validated['permissoes'])) {
                DB::table('permissao_role')->where('role_id', $id)->delete();

                foreach ($validated['permissoes'] as $permissaoId) {
                    DB::table('permissao_role')->insert([
                        'permissao_id' => $permissaoId,
                        'role_id' => $id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            $role = DB::table('roles')->where('id', $id)->first();
            $role->permissoes = DB::table('permissao_role')
                ->where('role_id', $id)
                ->pluck('permissao_id')
                ->toArray();

            return response()->json(['success' => true, 'data' => $role, 'message' => 'Papel atualizado com sucesso']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro em atualizarRole: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao atualizar papel'], 500);
        }
    }

    private function convertValue($valor, string $tipo)
    {
        return match ($tipo) {
            'boolean' => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $valor,
            'float' => (float) $valor,
            'array', 'json' => json_decode($valor, true) ?? [],
            default => $valor,
        };
    }
}
