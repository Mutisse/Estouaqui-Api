<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class AdminUtilizadorController extends Controller
{
    private const PER_PAGE_DEFAULT = 15;

    // ==========================================
    // 🔥 LISTAR UTILIZADORES COM TODOS OS DADOS
    // ==========================================

    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', self::PER_PAGE_DEFAULT);

            $query = User::query();

            $this->applyFilters($query, $request);

            // 🔥 SELECIONAR TODOS OS CAMPOS IMPORTANTES
            $query->select([
                'id',
                'nome',
                'email',
                'telefone',
                'tipo',
                'verificado',
                'disponivel',
                'status',
                'profissao',
                'sobre',
                'media_avaliacao',
                'total_avaliacoes',
                'latitude',
                'longitude',
                'raio_atendimento',
                'configuracoes',
                'created_at',
                'updated_at'
            ]);

            // 🔥 CARREGAR RELACIONAMENTOS PARA MOSTRAR NA TABELA
            $query->with([
                'categorias:id,nome',
                'servicos:id,nome,preco,duracao',
                'prestadorProfile:id,user_id,status_documento'
            ]);

            $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // 🔥 FORMATAR OS DADOS PARA O FRONTEND
            $data = $paginated->items();
            $data = array_map(function ($user) {
                return [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'tipo' => $user->tipo,
                    'verificado' => (bool) $user->verificado,
                    'disponivel' => (bool) $user->disponivel,
                    'status' => $user->status,
                    'profissao' => $user->profissao,
                    'sobre' => $user->sobre,
                    'media_avaliacao' => $user->media_avaliacao ? (float) $user->media_avaliacao : 0,
                    'total_avaliacoes' => $user->total_avaliacoes ?? 0,
                    'raio_atendimento' => $user->raio_atendimento ?? 10,
                    'latitude' => $user->latitude ? (float) $user->latitude : null,
                    'longitude' => $user->longitude ? (float) $user->longitude : null,
                    'categorias' => $user->categorias->pluck('nome')->toArray(),
                    'servicos' => $user->servicos->map(function ($servico) {
                        return [
                            'nome' => $servico->nome,
                            'preco' => (float) $servico->preco,
                            'duracao' => $servico->duracao
                        ];
                    })->toArray(),
                    'status_documento' => $user->prestadorProfile?->status_documento ?? null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            }, $data);

            return response()->json([
                'success' => true,
                'data' => $data,
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao listar utilizadores: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar utilizadores: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 🔥 BUSCAR UTILIZADOR ESPECÍFICO (COMPLETO)
    // ==========================================

    public function show($id)
    {
        try {
            $utilizador = User::with([
                'prestadorProfile',
                'categorias',
                'servicos',
                'enderecos',
                'avaliacoesRecebidas',
                'avaliacoesFeitas',
                'pedidosComoCliente',
                'pedidosComoPrestador',
                'favoritos'
            ])->find($id);

            if (!$utilizador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilizador não encontrado'
                ], 404);
            }

            // 🔥 FORMATAR RESPOSTA COMPLETA
            $data = [
                'id' => $utilizador->id,
                'nome' => $utilizador->nome,
                'email' => $utilizador->email,
                'telefone' => $utilizador->telefone,
                'tipo' => $utilizador->tipo,
                'foto' => $utilizador->foto,
                'verificado' => (bool) $utilizador->verificado,
                'disponivel' => (bool) $utilizador->disponivel,
                'status' => $utilizador->status,
                'profissao' => $utilizador->profissao,
                'sobre' => $utilizador->sobre,
                'media_avaliacao' => $utilizador->media_avaliacao ? (float) $utilizador->media_avaliacao : 0,
                'total_avaliacoes' => $utilizador->total_avaliacoes ?? 0,
                'latitude' => $utilizador->latitude ? (float) $utilizador->latitude : null,
                'longitude' => $utilizador->longitude ? (float) $utilizador->longitude : null,
                'raio_atendimento' => $utilizador->raio_atendimento ?? 10,
                'configuracoes' => $utilizador->configuracoes,
                'created_at' => $utilizador->created_at,
                'updated_at' => $utilizador->updated_at,
                'prestador_profile' => $utilizador->prestadorProfile,
                'categorias' => $utilizador->categorias,
                'servicos' => $utilizador->servicos,
                'enderecos' => $utilizador->enderecos,
                'avaliacoes_recebidas' => $utilizador->avaliacoesRecebidas,
                'avaliacoes_feitas' => $utilizador->avaliacoesFeitas,
                'pedidos_como_cliente' => $utilizador->pedidosComoCliente,
                'pedidos_como_prestador' => $utilizador->pedidosComoPrestador,
                'favoritos' => $utilizador->favoritos,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar utilizador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 🔥 CRUD (store, update, destroy, etc)
    // ==========================================

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'telefone' => 'nullable|string|max:20',
                'tipo' => 'required|in:cliente,prestador,admin,root',
                'password' => 'required|string|min:6',
                'profissao' => 'nullable|string|max:255',
                'sobre' => 'nullable|string',
            ]);

            $utilizador = User::create([
                'nome' => $validated['nome'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
                'tipo' => $validated['tipo'],
                'password' => Hash::make($validated['password']),
                'profissao' => $validated['profissao'] ?? null,
                'sobre' => $validated['sobre'] ?? null,
                'verificado' => in_array($validated['tipo'], ['admin', 'root']),
                'disponivel' => true,
                'status' => 'ativo',
                'media_avaliacao' => 0,
                'total_avaliacoes' => 0,
                'raio_atendimento' => 10,
            ]);

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador criado com sucesso'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $utilizador = User::findOrFail($id);

            $validated = $request->validate([
                'nome' => 'sometimes|string|max:255',
                'email' => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
                'telefone' => 'nullable|string|max:20',
                'tipo' => 'sometimes|in:cliente,prestador,admin,root',
                'profissao' => 'nullable|string|max:255',
                'sobre' => 'nullable|string',
                'password' => 'nullable|string|min:6',
            ]);

            $updatableFields = ['nome', 'email', 'telefone', 'tipo', 'profissao', 'sobre'];
            foreach ($updatableFields as $field) {
                if (array_key_exists($field, $validated)) {
                    $utilizador->$field = $validated[$field];
                }
            }

            if (isset($validated['password'])) {
                $utilizador->password = Hash::make($validated['password']);
            }

            $utilizador->save();

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador atualizado com sucesso'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $currentUser = Auth::user();

            if ($currentUser && $utilizador->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não pode eliminar o seu próprio utilizador'
                ], 403);
            }

            if ($utilizador->foto) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($utilizador->foto);
            }

            $nome = $utilizador->nome;
            $utilizador->delete();

            Log::info('Utilizador eliminado', [
                'user_id' => $id,
                'nome' => $nome,
                'admin_id' => $currentUser?->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "Utilizador {$nome} eliminado com sucesso"
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao eliminar utilizador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao eliminar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 🔥 AÇÕES DE STATUS
    // ==========================================

    public function verificar($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $utilizador->verificado = true;
            $utilizador->save();

            Log::info('Utilizador verificado', [
                'user_id' => $utilizador->id,
                'nome' => $utilizador->nome
            ]);

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador verificado com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao verificar utilizador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function aprovar($id)
    {
        try {
            $utilizador = User::findOrFail($id);

            if ($utilizador->tipo !== 'prestador') {
                return response()->json([
                    'success' => false,
                    'message' => 'Apenas prestadores podem ser aprovados'
                ], 422);
            }

            $utilizador->verificado = true;
            $utilizador->status = 'ativo';
            $utilizador->save();

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Prestador aprovado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aprovar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reprovar($id)
    {
        try {
            $utilizador = User::findOrFail($id);

            if ($utilizador->tipo !== 'prestador') {
                return response()->json([
                    'success' => false,
                    'message' => 'Apenas prestadores podem ser reprovados'
                ], 422);
            }

            $utilizador->verificado = false;
            $utilizador->status = 'reprovado';
            $utilizador->save();

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Prestador reprovado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao reprovar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function ativar($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $utilizador->disponivel = true;
            $utilizador->status = 'ativo';
            $utilizador->save();

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador ativado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao ativar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function desativar($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $currentUser = Auth::user();

            if ($currentUser && $utilizador->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não pode desativar o seu próprio utilizador'
                ], 403);
            }

            $utilizador->disponivel = false;
            $utilizador->status = 'desativado';
            $utilizador->save();

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador desativado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao desativar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bloquear($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $currentUser = Auth::user();

            if ($currentUser && $utilizador->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não pode bloquear o seu próprio utilizador'
                ], 403);
            }

            $utilizador->disponivel = false;
            $utilizador->status = 'bloqueado';
            $utilizador->save();

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador bloqueado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao bloquear utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function desbloquear($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $utilizador->disponivel = true;
            $utilizador->status = 'ativo';
            $utilizador->save();

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador desbloqueado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao desbloquear utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 🔥 MÉTODOS PRIVADOS
    // ==========================================

    private function applyFilters($query, Request $request): void
    {
        $search = $request->input('search');
        $tipo = $request->input('tipo');
        $verificado = $request->input('verificado');
        $status = $request->input('status');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telefone', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('id', $search);
                }
            });
        }

        if ($tipo && in_array($tipo, ['cliente', 'prestador', 'admin', 'root'])) {
            $query->where('tipo', $tipo);
        }

        if ($verificado !== null && $verificado !== '') {
            $query->where('verificado', filter_var($verificado, FILTER_VALIDATE_BOOLEAN));
        }

        if ($status && in_array($status, ['ativo', 'desativado', 'bloqueado', 'reprovado', 'pendente'])) {
            $query->where('status', $status);
        }
    }
}
