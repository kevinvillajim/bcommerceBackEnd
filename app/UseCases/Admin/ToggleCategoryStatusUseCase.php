<?php

// app/UseCases/Admin/ToggleCategoryStatusUseCase.php

namespace App\UseCases\Admin;

use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Models\User;

class ToggleCategoryStatusUseCase
{
    private CategoryRepositoryInterface $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Cambia el estado featured de una categoría.
     *
     * @throws \Exception
     */
    public function toggleFeatured(int $categoryId, bool $featured, User $user): bool
    {
        // Solo administradores pueden modificar categorías
        if (! $this->hasPermissionToToggle($user)) {
            throw new \Exception('No tienes permisos para modificar categorías');
        }

        // Verificar que la categoría existe
        $category = $this->categoryRepository->findById($categoryId);
        if (! $category) {
            throw new \Exception('Categoría no encontrada');
        }

        // Actualizar usando el repositorio
        $updatedCategory = $this->categoryRepository->updateFromArray($categoryId, [
            'featured' => $featured,
        ]);

        return $updatedCategory !== null;
    }

    /**
     * Cambia el estado activo de una categoría.
     *
     * @throws \Exception
     */
    public function toggleActive(int $categoryId, bool $isActive, User $user): bool
    {
        // Solo administradores pueden modificar categorías
        if (! $this->hasPermissionToToggle($user)) {
            throw new \Exception('No tienes permisos para modificar categorías');
        }

        // Verificar que la categoría existe
        $category = $this->categoryRepository->findById($categoryId);
        if (! $category) {
            throw new \Exception('Categoría no encontrada');
        }

        // Si se está desactivando, verificar que no tenga productos activos
        if (! $isActive) {
            // Aquí podrías agregar lógica para verificar productos activos
            // Por ahora permitimos la desactivación
        }

        // Actualizar usando el repositorio
        $updatedCategory = $this->categoryRepository->updateFromArray($categoryId, [
            'is_active' => $isActive,
        ]);

        return $updatedCategory !== null;
    }

    /**
     * Actualiza el orden de una categoría.
     *
     * @throws \Exception
     */
    public function updateOrder(int $categoryId, int $order, User $user): bool
    {
        // Solo administradores pueden modificar categorías
        if (! $this->hasPermissionToToggle($user)) {
            throw new \Exception('No tienes permisos para modificar categorías');
        }

        // Verificar que la categoría existe
        $category = $this->categoryRepository->findById($categoryId);
        if (! $category) {
            throw new \Exception('Categoría no encontrada');
        }

        // Validar orden
        if ($order < 0) {
            throw new \Exception('El orden no puede ser negativo');
        }

        // Actualizar usando el repositorio
        $updatedCategory = $this->categoryRepository->updateFromArray($categoryId, [
            'order' => $order,
        ]);

        return $updatedCategory !== null;
    }

    /**
     * Verifica si el usuario tiene permisos para modificar categorías.
     */
    private function hasPermissionToToggle(User $user): bool
    {
        // Solo los administradores pueden modificar categorías
        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }
}
