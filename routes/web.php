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

// ✅ ENDPOINT PARA PAGOS EXTERNOS DATAFAST RESULT - Redirige al frontend con link_code
Route::get('/pay/{linkCode}/result', function (Illuminate\Http\Request $request, string $linkCode) {
    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

    // Obtener todos los parámetros de query de Datafast
    $queryParams = $request->query();

    // Construir URL del frontend para pagos externos con el link_code
    $redirectUrl = $frontendUrl.'/pay/'.$linkCode.'/result?'.http_build_query($queryParams);

    \Illuminate\Support\Facades\Log::info('External Payment Datafast Result: Redirigiendo desde backend a frontend', [
        'link_code' => $linkCode,
        'query_params' => $queryParams,
        'redirect_url' => $redirectUrl,
    ]);

    return redirect($redirectUrl);
})->name('external.payment.datafast.result');
