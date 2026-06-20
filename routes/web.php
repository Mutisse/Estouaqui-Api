<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log; // 🔥 ADICIONAR ESTA LINHA

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// ==========================================
// 🔥 ROTA PRINCIPAL (welcome)
// ==========================================
Route::get('/', function () {
    return view('welcome');
});

// ==========================================
// 🔥 ROTA PARA SERVIR IMAGENS DO STORAGE
// ==========================================
Route::get('/imagem/{path}', function ($path) {
    // 🔥 SANITIZAR O PATH PARA SEGURANÇA
    $path = str_replace(['../', '..\\', '//', '\\\\', '%00'], '', $path);
    $path = ltrim($path, '/');

    // 🔥 REMOVER PREFIXOS DESNECESSÁRIOS
    $path = str_replace('storage/', '', $path);
    $path = str_replace('public/', '', $path);
    $path = str_replace('app/public/', '', $path);

    // 🔥 CAMINHO COMPLETO DO ARQUIVO
    $fullPath = storage_path('app/public/' . $path);

    // 🔥 VERIFICAR SE O ARQUIVO EXISTE
    if (!file_exists($fullPath)) {
        // Log para debug (agora funciona com a importação)
        Log::warning('Imagem não encontrada: ' . $path);
        abort(404);
    }

    // 🔥 PEGAR O MIME TYPE CORRETO
    $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

    // 🔥 RETORNAR A IMAGEM COM HEADERS DE CACHE
    return response()->file($fullPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=86400, immutable',
        'Accept-Ranges' => 'bytes',
    ]);
})->where('path', '.*');
