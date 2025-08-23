<?php

// app/Http/Middleware/AdminOrOwner.php

namespace App\Http\Middleware;

use App\Domain\Repositories\ProductRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminOrOwner
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Si es administrador, permitir acceso total
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return $next($request);
        }

        // Si es una ruta de producto específico, verificar propiedad
        $productId = $request->route('id');

        if ($productId) {
            $product = $this->productRepository->findById($productId);

            if (! $product) {
                return response()->json(['message' => 'Producto no encontrado'], 404);
            }

            // Verificar si el usuario es el propietario del producto
            if ($product->getUserId() !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permisos para modificar este producto',
                ], 403);
            }
        }

        return $next($request);
    }
}
