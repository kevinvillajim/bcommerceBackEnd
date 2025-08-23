<?php

namespace App\Http\Middleware;

use App\UseCases\Recommendation\TrackUserInteractionsUseCase;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductInteractionMiddleware
{
    private ?TrackUserInteractionsUseCase $trackUserInteractionsUseCase;

    /**
     * Constructor con inyección de dependencias.
     */
    public function __construct(?TrackUserInteractionsUseCase $trackUserInteractionsUseCase = null)
    {
        $this->trackUserInteractionsUseCase = $trackUserInteractionsUseCase;
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $interactionType = 'view_product')
    {
        // Procesar la solicitud primero
        $response = $next($request);

        // Obtener el usuario autenticado (si existe)
        $user = Auth::user();

        // Solo registrar interacciones si:
        // 1. Hay un usuario autenticado
        // 2. El servicio de tracking está disponible
        // 3. La petición fue exitosa (código 200)
        if ($user && $this->trackUserInteractionsUseCase && $response->getStatusCode() === 200) {
            $this->processInteraction($request, $user->id, $interactionType);
        }

        return $response;
    }

    /**
     * Procesa y registra la interacción del usuario.
     */
    private function processInteraction(Request $request, int $userId, string $interactionType): void
    {
        $metadata = [];
        $itemId = 0;

        switch ($interactionType) {
            case 'view_product':
                // Para visualización de producto individual
                $itemId = $this->extractProductId($request);
                $metadata['view_time'] = time();
                $metadata['source'] = $request->header('referer') ?? 'direct';
                $metadata['device'] = $this->detectDevice($request);
                break;

            case 'search_products':
                // Para búsquedas de productos
                $metadata['term'] = $request->input('term', '');
                $metadata['filters'] = $request->except(['term', 'limit', 'offset', 'page']);
                $metadata['results_count'] = $request->input('count', 0);
                break;

            case 'browse_category':
                // Para navegación por categoría
                $itemId = $this->extractCategoryId($request);
                $metadata['category_id'] = $itemId;
                break;

            case 'add_to_cart':
                // Para añadir al carrito
                $itemId = $request->input('product_id');
                $metadata['quantity'] = $request->input('quantity', 1);
                $metadata['price'] = $request->input('price', 0);
                break;

            default:
                // Cualquier otro tipo de interacción
                $itemId = $request->input('item_id', 0);
                $metadata = array_merge($metadata, $request->input('metadata', []));
                break;
        }

        // Registrar la interacción
        if ($this->trackUserInteractionsUseCase) {
            $this->trackUserInteractionsUseCase->execute(
                $userId,
                $interactionType,
                $itemId,
                $metadata
            );
        }
    }

    /**
     * Extrae el ID del producto de la solicitud.
     */
    private function extractProductId(Request $request): int
    {
        // Intentar obtener el ID del producto de diferentes fuentes
        $id = $request->route('id') ?? $request->route('product') ?? $request->input('id');

        if (is_numeric($id)) {
            return (int) $id;
        }

        return 0;
    }

    /**
     * Extrae el ID de la categoría de la solicitud.
     */
    private function extractCategoryId(Request $request): int
    {
        // Intentar obtener el ID de la categoría de diferentes fuentes
        $id = $request->route('categoryId') ?? $request->route('category') ?? $request->input('category_id');

        if (is_numeric($id)) {
            return (int) $id;
        }

        return 0;
    }

    /**
     * Detecta el tipo de dispositivo del usuario.
     */
    private function detectDevice(Request $request): string
    {
        $userAgent = $request->header('User-Agent');

        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
