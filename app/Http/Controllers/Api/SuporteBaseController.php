<?php
// app/Http/Controllers/Api/SuporteBaseController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\MensagemTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SuporteBaseController extends Controller
{
    protected function getUserTickets($userId, $tipo)
    {
        $campoId = $tipo === 'cliente' ? 'cliente_id' : 'prestador_id';
        return Ticket::where($campoId, $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    protected function getTicketById($id, $userId, $tipo)
    {
        $campoId = $tipo === 'cliente' ? 'cliente_id' : 'prestador_id';
        return Ticket::where($campoId, $userId)
            ->where('id', $id)
            ->first();
    }

    protected function getUserName($user)
    {
        return $user->nome ?? $user->name ?? 'Usuário';
    }
}
