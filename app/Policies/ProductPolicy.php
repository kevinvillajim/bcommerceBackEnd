<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    /**
     * Determina si el usuario puede ver cualquier producto.
     */
    public function viewAny(?User $user): bool
    {
        // Cualquier usuario puede ver la lista de productos, incluso sin estar autenticado
        return true;
    }

    /**
     * Determina si el usuario puede ver el producto.
     */
    public function view(?User $user, Product $product): bool
    {
        // Solo los productos publicados y activos son visibles para todos
        if ($product->status === 'active' && $product->published) {
            return true;
        }

        // Si el usuario no está autenticado, no puede ver productos no publicados
        if (! $user) {
            return false;
        }

        // Si el usuario es el vendedor o un admin, puede ver el producto
        return $this->isSellerOrAdmin($user, $product);
    }

    /**
     * Determina si el usuario puede crear productos.
     */
    public function create(User $user): bool
    {
        // Un usuario autenticado no bloqueado puede crear productos
        return ! $user->is_blocked;
    }

    /**
     * Determina si el usuario puede actualizar el producto.
     */
    public function update(User $user, Product $product): bool
    {
        return $this->isSellerOrAdmin($user, $product);
    }

    /**
     * Determina si el usuario puede eliminar el producto.
     */
    public function delete(User $user, Product $product): bool
    {
        return $this->isSellerOrAdmin($user, $product);
    }

    /**
     * Determina si el usuario puede restaurar el producto.
     */
    public function restore(User $user, Product $product): bool
    {
        return $this->isSellerOrAdmin($user, $product);
    }

    /**
     * Determina si el usuario puede eliminar permanentemente el producto.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        // Solo administradores pueden eliminar permanentemente
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede destacar o quitar de destacados el producto.
     */
    public function feature(User $user, Product $product): bool
    {
        // Solo administradores pueden destacar productos
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede cambiar el estado de publicación del producto.
     */
    public function publish(User $user, Product $product): bool
    {
        return $this->isSellerOrAdmin($user, $product);
    }

    /**
     * Verifica si el usuario es vendedor del producto o administrador.
     */
    private function isSellerOrAdmin(User $user, Product $product): bool
    {
        return $user->id === $product->user_id || $this->isAdmin($user);
    }

    /**
     * Verifica si el usuario es administrador.
     */
    private function isAdmin(User $user): bool
    {
        return Admin::where('user_id', $user->id)->exists();
    }
}
