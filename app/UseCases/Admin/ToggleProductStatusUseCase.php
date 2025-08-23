<?php

// app/UseCases/Admin/ToggleProductStatusUseCase.php

namespace App\UseCases\Admin;

use App\Domain\Repositories\ProductRepositoryInterface;
use App\Models\User;

class ToggleProductStatusUseCase
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Cambia el estado featured de un producto.
     *
     * @throws \Exception
     */
    public function toggleFeatured(int $productId, bool $featured, User $user): bool
    {
        // Verificar permisos
        if (! $this->hasPermissionToToggle($productId, $user)) {
            throw new \Exception('No tienes permisos para modificar este producto');
        }

        // Verificar que el producto existe
        $product = $this->productRepository->findById($productId);
        if (! $product) {
            throw new \Exception('Producto no encontrado');
        }

        // Actualizar solo el campo featured
        return $this->productRepository->updatePartial($productId, [
            'featured' => $featured,
        ]);
    }

    /**
     * Cambia el estado published de un producto.
     *
     * @throws \Exception
     */
    public function togglePublished(int $productId, bool $published, User $user): bool
    {
        // Verificar permisos
        if (! $this->hasPermissionToToggle($productId, $user)) {
            throw new \Exception('No tienes permisos para modificar este producto');
        }

        // Verificar que el producto existe
        $product = $this->productRepository->findById($productId);
        if (! $product) {
            throw new \Exception('Producto no encontrado');
        }

        // Actualizar solo el campo published
        return $this->productRepository->updatePartial($productId, [
            'published' => $published,
        ]);
    }

    /**
     * Cambia el status de un producto.
     *
     * @throws \Exception
     */
    public function updateStatus(int $productId, string $status, User $user): bool
    {
        // Validar status
        $validStatuses = ['active', 'inactive', 'draft'];
        if (! in_array($status, $validStatuses)) {
            throw new \Exception('Estado no válido');
        }

        // Verificar permisos
        if (! $this->hasPermissionToToggle($productId, $user)) {
            throw new \Exception('No tienes permisos para modificar este producto');
        }

        // Verificar que el producto existe
        $product = $this->productRepository->findById($productId);
        if (! $product) {
            throw new \Exception('Producto no encontrado');
        }

        // Actualizar solo el campo status
        return $this->productRepository->updatePartial($productId, [
            'status' => $status,
        ]);
    }

    /**
     * Verifica si el usuario tiene permisos para modificar el producto.
     */
    private function hasPermissionToToggle(int $productId, User $user): bool
    {
        // Los administradores pueden modificar cualquier producto
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        // Los vendedores solo pueden modificar sus propios productos
        $product = $this->productRepository->findById($productId);
        if (! $product) {
            return false;
        }

        return $product->getUserId() === $user->id;
    }
}
