<?php
// app/Http/Controllers/Api/DashboardController.php

namespace App\Http\Controllers\Api;

use App\Models\Pedido;
use App\Models\Favorito;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    /**
     * Get dashboard data for client
     * GET /api/cliente/dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $data = [
            'pedidos_pendentes' => Pedido::where('cliente_id', $user->id)
                ->where('status', 'pendente')
                ->count(),
            'favoritos_count' => Favorito::where('cliente_id', $user->id)->count(),
            'pedidos_count' => Pedido::where('cliente_id', $user->id)->count(),
            'pedidos_concluidos' => Pedido::where('cliente_id', $user->id)
                ->where('status', 'concluido')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
