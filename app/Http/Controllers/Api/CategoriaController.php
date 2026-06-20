<?php
// app/Http/Controllers/Api/CategoriaController.php

namespace App\Http\Controllers\Api;

use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends BaseController
{
    // ==========================================
    // 🔥 HELPER PARA GERAR URL DE IMAGENS
    // ==========================================

    /**
     * Gera URL correta para imagens usando a rota /imagem
     */
    private function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Limpa o path
        $path = ltrim($path, '/');
        $path = str_replace('storage/', '', $path);
        $path = str_replace('public/', '', $path);
        $path = str_replace('app/public/', '', $path);
        $path = str_replace('categorias/', '', $path);

        // 🔥 USA A ROTA /imagem
        return url('/imagem/' . $path);
    }

    // ==========================================
    // 🔥 LISTAR CATEGORIAS (CORRIGIDO)
    // ==========================================

    /**
     * GET /categorias
     * Listar todas as categorias ativas
     */
    public function index(Request $request)
    {
        $categorias = Categoria::where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->withCount('servicos')
            ->get();

        // 🔥 CORRIGIR URL DO ÍCONE (se for imagem)
        $categorias->each(function ($categoria) {
            // Se o ícone for uma imagem (path), gerar URL
            if ($categoria->icone && !str_starts_with($categoria->icone, 'http')) {
                $categoria->icone = $this->getImageUrl($categoria->icone);
            }

            // Garantir que o campo existe para o frontend
            $categoria->servicos_count = $categoria->servicos_count ?? 0;
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    // ==========================================
    // 🔥 MOSTRAR CATEGORIA (CORRIGIDO)
    // ==========================================

    /**
     * GET /categorias/{id}
     * Mostrar detalhes de uma categoria
     */
    public function show($id)
    {
        $categoria = Categoria::withCount('servicos')->find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoria não encontrada'
            ], 404);
        }

        // 🔥 CORRIGIR URL DO ÍCONE (se for imagem)
        if ($categoria->icone && !str_starts_with($categoria->icone, 'http')) {
            $categoria->icone = $this->getImageUrl($categoria->icone);
        }

        $categoria->servicos_count = $categoria->servicos_count ?? 0;

        return response()->json([
            'success' => true,
            'data' => $categoria
        ]);
    }

    // ==========================================
    // 🔥 CATEGORIAS COM PRESTADORES (CORRIGIDO)
    // ==========================================

    /**
     * GET /categorias/com-prestadores
     * Listar categorias que têm prestadores disponíveis
     */
    public function comPrestadores(Request $request)
    {
        $categorias = Categoria::where('ativo', true)
            ->whereHas('prestadores', function ($query) {
                $query->where('disponivel', true);
            })
            ->withCount(['prestadores' => function ($query) {
                $query->where('disponivel', true);
            }])
            ->orderBy('nome')
            ->get();

        // 🔥 CORRIGIR URL DO ÍCONE
        $categorias->each(function ($categoria) {
            if ($categoria->icone && !str_starts_with($categoria->icone, 'http')) {
                $categoria->icone = $this->getImageUrl($categoria->icone);
            }
            $categoria->prestadores_count = $categoria->prestadores_count ?? 0;
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    // ==========================================
    // 🔥 CATEGORIAS POPULARES (CORRIGIDO)
    // ==========================================

    /**
     * GET /categorias/populares
     * Listar categorias mais populares (mais pedidos)
     */
    public function populares(Request $request)
    {
        $limit = $request->input('limit', 6);

        $categorias = Categoria::where('ativo', true)
            ->withCount(['pedidos' => function ($query) {
                $query->where('status', 'concluido');
            }])
            ->orderBy('pedidos_count', 'desc')
            ->limit($limit)
            ->get();

        // 🔥 CORRIGIR URL DO ÍCONE
        $categorias->each(function ($categoria) {
            if ($categoria->icone && !str_starts_with($categoria->icone, 'http')) {
                $categoria->icone = $this->getImageUrl($categoria->icone);
            }
            $categoria->pedidos_count = $categoria->pedidos_count ?? 0;
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    // ==========================================
    // 🔥 CATEGORIAS POR PRESTADOR (CORRIGIDO)
    // ==========================================

    /**
     * GET /categorias/prestador/{prestadorId}
     * Listar categorias de um prestador específico
     */
    public function porPrestador($prestadorId)
    {
        $prestador = \App\Models\User::find($prestadorId);

        if (!$prestador) {
            return response()->json([
                'success' => false,
                'message' => 'Prestador não encontrado'
            ], 404);
        }

        $categorias = $prestador->categorias()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        // 🔥 CORRIGIR URL DO ÍCONE
        $categorias->each(function ($categoria) {
            if ($categoria->icone && !str_starts_with($categoria->icone, 'http')) {
                $categoria->icone = $this->getImageUrl($categoria->icone);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }
}
