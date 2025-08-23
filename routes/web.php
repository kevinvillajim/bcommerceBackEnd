<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ✅ ENDPOINT PARA DATAFAST RESULT - Redirige al frontend con parámetros
Route::get('/datafast-result', function (Illuminate\Http\Request $request) {
    $frontendUrl = config('app.frontend_url', 'http://localhost:3001');

    // Obtener todos los parámetros de query de Datafast
    $queryParams = $request->query();

    // Construir URL del frontend con parámetros
    $redirectUrl = $frontendUrl.'/datafast-result?'.http_build_query($queryParams);

    \Illuminate\Support\Facades\Log::info('Datafast Result: Redirigiendo desde backend a frontend', [
        'query_params' => $queryParams,
        'redirect_url' => $redirectUrl,
    ]);

    return redirect($redirectUrl);
})->name('datafast.result');
