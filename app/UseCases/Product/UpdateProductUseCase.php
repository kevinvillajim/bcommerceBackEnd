<?php

namespace App\UseCases\Product;

use App\Domain\Entities\ProductEntity;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Infrastructure\Services\FileUploadService;
use Illuminate\Support\Str;

class UpdateProductUseCase
{
    private ProductRepositoryInterface $productRepository;

    private SellerRepositoryInterface $sellerRepository;

    private FileUploadService $fileUploadService;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        SellerRepositoryInterface $sellerRepository,
        FileUploadService $fileUploadService
    ) {
        $this->productRepository = $productRepository;
        $this->sellerRepository = $sellerRepository;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Ejecuta el caso de uso
     *
     * @param  int|null  $userId  Usuario que está actualizando (para verificar permisos)
     *
     * @throws \InvalidArgumentException
     */
    public function execute(int $productId, array $data, array $files = [], ?int $userId = null): ?ProductEntity
    {
        // Buscar el producto existente
        $existingProduct = $this->productRepository->findById($productId);

        if (! $existingProduct) {
            return null;
        }

        // Si se proporciona userId, verificar que el usuario tenga permisos para editar este producto
        if ($userId) {
            $this->verifyUserCanEditProduct($existingProduct, $userId);
        }

        // Actualizar los campos con los nuevos valores
        if (isset($data['name'])) {
            $existingProduct->setName($data['name']);

            // Actualizar el slug solo si cambió el nombre y no se proporcionó un slug específico
            if (! isset($data['slug'])) {
                $existingProduct->setSlug(Str::slug($data['name']));
            }
        }

        if (isset($data['slug'])) {
            $existingProduct->setSlug($data['slug']);
        }

        if (isset($data['category_id'])) {
            $existingProduct->setCategoryId($data['category_id']);
        }

        if (isset($data['description'])) {
            $existingProduct->setDescription($data['description']);
        }

        if (isset($data['price'])) {
            $existingProduct->setPrice((float) $data['price']);
        }

        if (isset($data['stock'])) {
            $existingProduct->setStock((int) $data['stock']);
        }

        if (isset($data['weight'])) {
            $existingProduct->setWeight($data['weight']);
        }

        if (isset($data['width'])) {
            $existingProduct->setWidth($data['width']);
        }

        if (isset($data['height'])) {
            $existingProduct->setHeight($data['height']);
        }

        if (isset($data['depth'])) {
            $existingProduct->setDepth($data['depth']);
        }

        if (isset($data['dimensions'])) {
            $existingProduct->setDimensions($data['dimensions']);
        }

        if (isset($data['colors'])) {
            $existingProduct->setColors($this->processJsonField($data['colors']));
        }

        if (isset($data['sizes'])) {
            $existingProduct->setSizes($this->processJsonField($data['sizes']));
        }

        if (isset($data['tags'])) {
            $existingProduct->setTags($this->processJsonField($data['tags']));
        }

        if (isset($data['sku'])) {
            $existingProduct->setSku($data['sku']);
        }

        if (isset($data['attributes'])) {
            $existingProduct->setAttributes($this->processJsonField($data['attributes']));
        }

        if (isset($data['featured'])) {
            $existingProduct->setFeatured((bool) $data['featured']);
        }

        if (isset($data['published'])) {
            $existingProduct->setPublished((bool) $data['published']);
        }

        if (isset($data['status'])) {
            $existingProduct->setStatus($data['status']);
        }

        if (isset($data['discount_percentage'])) {
            $existingProduct->setDiscountPercentage((float) $data['discount_percentage']);
        }

        if (isset($data['short_description'])) {
            $existingProduct->setShortDescription($data['short_description']);
        }

        // Procesar imágenes nuevas
        if (! empty($files['images'])) {
            $newImages = $this->uploadProductImages($files['images'], $existingProduct->getUserId());

            // Combinar con imágenes existentes si es necesario
            $currentImages = $existingProduct->getImages() ?? [];

            if (isset($data['replace_images']) && $data['replace_images']) {
                // Eliminar imágenes antiguas
                if (! empty($currentImages)) {
                    foreach ($currentImages as $imageSet) {
                        $this->fileUploadService->deleteImage($imageSet);
                    }
                }

                // Establecer solo las nuevas imágenes
                $existingProduct->setImages($newImages);
            } else {
                // Agregar nuevas imágenes a las existentes
                $allImages = array_merge($currentImages, $newImages);
                $existingProduct->setImages($allImages);
            }
        }

        // Remover imágenes específicas si se solicita
        if (! empty($data['remove_images']) && is_array($data['remove_images'])) {
            $currentImages = $existingProduct->getImages() ?? [];
            $updatedImages = [];

            foreach ($currentImages as $index => $imageSet) {
                if (! in_array($index, $data['remove_images'])) {
                    $updatedImages[] = $imageSet;
                } else {
                    // Eliminar físicamente las imágenes
                    $this->fileUploadService->deleteImage($imageSet);
                }
            }

            $existingProduct->setImages($updatedImages);
        }

        // Guardar los cambios en el repositorio
        $updatedProduct = $this->productRepository->update($existingProduct);

        return $updatedProduct;
    }

    /**
     * Verificar que el usuario tenga permisos para editar este producto
     *
     * @throws \InvalidArgumentException
     */
    private function verifyUserCanEditProduct(ProductEntity $product, int $userId): void
    {
        // El producto debe pertenecer al usuario
        if ($product->getUserId() !== $userId) {
            throw new \InvalidArgumentException('No tienes permisos para editar este producto');
        }

        // Verificar que el usuario siga siendo un vendedor activo
        $seller = $this->sellerRepository->findByUserId($userId);

        if (! $seller) {
            throw new \InvalidArgumentException('Ya no estás registrado como vendedor');
        }

        if (! $seller->isActive()) {
            throw new \InvalidArgumentException('Tu cuenta de vendedor no está activa');
        }

        // Verificar que el seller_id del producto coincida con el seller actual del usuario
        if ($product->getSellerId() !== $seller->getId()) {
            throw new \InvalidArgumentException('Inconsistencia en los datos del vendedor. Contacta al soporte.');
        }
    }

    /**
     * Sube las imágenes del producto
     */
    private function uploadProductImages(array $images, int $userId): array
    {
        $path = "products/{$userId}/".now()->format('Y-m-d');

        return $this->fileUploadService->uploadMultipleImages($images, $path);
    }

    /**
     * Procesa un campo que puede venir como JSON string o como array
     *
     * @param  mixed  $field
     */
    private function processJsonField($field): ?array
    {
        if (is_string($field)) {
            // Intentar decodificar si es un string JSON
            $decoded = json_decode($field, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            // Si no es JSON pero es un string con comas, dividirlo
            if (strpos($field, ',') !== false) {
                return array_map('trim', explode(',', $field));
            }

            // Si es un solo valor, convertirlo en array
            return [$field];
        }

        if (is_array($field)) {
            return $field;
        }

        return null;
    }
}
