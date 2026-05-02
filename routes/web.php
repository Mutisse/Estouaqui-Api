<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Rota para a documentação - USANDO O ARQUIVO QUE VOCÊ CRIOU
Route::get('/docs', function () {
    return view('docs');
});

// Rota raiz redireciona para documentação
Route::get('/', function () {
    return redirect('/docs');
});
