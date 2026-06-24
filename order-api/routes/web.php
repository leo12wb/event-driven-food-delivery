<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app'     => 'Uber Eats MVP',
        'version' => 'Laravel ' . app()->version(),
        'docs'    => [
            'POST /api/orders'     => 'Criar pedido',
            'GET  /api/orders'     => 'Listar pedidos',
            'GET  /api/orders/{id}' => 'Detalhe do pedido',
        ],
    ]);
});
