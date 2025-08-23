<?php

namespace App\UseCases\Product;

use App\Domain\Entities\ProductEntity;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Infrastructure\Services\FileUploadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateProductUseCase
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
     * Ejecuta el caso de uso para crear un producto
     *
     * @param  array  $data  Datos del producto
     * @param  array  $files  Archivos del producto (opcional)
     *
     * @throws \InvalidArgumentException
     */
    public function execute(array $data, array $files = []): ProductEntity
    {
        Log::info('🚀 Iniciando creación de producto', [
            'data_keys' => array_keys($data),
            'files_keys' => array_keys($files),
            'has_images' => ! empty($files['images']),
        ]);

        // Validar datos de entrada
        $this->validateProductData($data);

        // Buscar automáticamente el seller_id basándose en el user_id
        $sellerId = $this->getSellerIdFromUserId($data['user_id']);

        // Verificar si el vendedor es destacado
        $seller = $this->sellerRepository->find($sellerId);
        $isSellerFeatured = $seller && $seller->isFeatured();

        if ($isSellerFeatured) {
            Log::info('⭐ Auto-highlighting product because seller is featured', [
                'seller_id' => $sellerId,
                'seller_name' => $seller->getStoreName(),
                'product_name' => $data['name'] ?? 'Unknown',
            ]);
        }

        // Generar slug si no existe
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        // Procesar campos JSON
        $data['colors'] = $this->processJsonField($data['colors'] ?? null);
        $data['sizes'] = $this->processJsonField($data['sizes'] ?? null);
        $data['tags'] = $this->processJsonField($data['tags'] ?? null);
        $data['attributes'] = $this->processJsonField($data['attributes'] ?? null);

        // ✅ MEJORAR PROCESAMIENTO DE IMÁGENES CON LOGGING DETALLADO
        Log::info('📸 Procesando imágenes', [
            'has_files' => ! empty($files),
            'has_images' => ! empty($files['images']),
            'images_count' => ! empty($files['images']) ? count($files['images']) : 0,
        ]);

        $data['images'] = null; // Inicializar como null

        if (! empty($files['images'])) {
            try {
                $imageResult = $this->processImages($files, $data['user_id'] ?? null);

                Log::info('✅ Resultado del procesamiento de imágenes', [
                    'result_type' => gettype($imageResult),
                    'is_array' => is_array($imageResult),
                    'count' => is_array($imageResult) ? count($imageResult) : 0,
                    'result' => $imageResult,
                ]);

                if (is_array($imageResult) && ! empty($imageResult)) {
                    $data['images'] = $imageResult;
                } else {
                    Log::warning('⚠️ El resultado de procesamiento de imágenes no es un array válido');
                }
            } catch (\Exception $e) {
                Log::error('❌ Error al procesar imágenes', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // No fallar la creación del producto por las imágenes
                $data['images'] = null;
            }
        }

        // Preparar datos para la entidad
        $productData = [
            'userId' => $data['user_id'],
            'categoryId' => $data['category_id'],
            'sellerId' => $sellerId,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'price' => (float) $data['price'],
            'stock' => (int) ($data['stock'] ?? 0),
            'weight' => $data['weight'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'depth' => $data['depth'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'colors' => $data['colors'],
            'sizes' => $data['sizes'],
            'tags' => $data['tags'],
            'sku' => $data['sku'] ?? null,
            'attributes' => $data['attributes'],
            'images' => $data['images'],
            'featured' => (bool) ($data['featured'] ?? $isSellerFeatured),
            'published' => (bool) ($data['published'] ?? true),
            'status' => $data['status'] ?? 'active',
            'viewCount' => (int) ($data['view_count'] ?? 0),
            'salesCount' => (int) ($data['sales_count'] ?? 0),
            'discountPercentage' => (float) ($data['discount_percentage'] ?? 0),
            'shortDescription' => $data['short_description'] ?? null,
        ];

        Log::info('📝 Datos preparados para entidad', [
            'images' => $productData['images'],
            'images_type' => gettype($productData['images']),
        ]);

        // Crear la entidad de producto
        $productEntity = new ProductEntity(
            $productData['userId'],
            $productData['categoryId'],
            $productData['name'],
            $productData['slug'],
            $productData['description'],
            0, // rating
            0, // rating count
            $productData['price'],
            $productData['stock'],
            $productData['sellerId'],
            $productData['weight'],
            $productData['width'],
            $productData['height'],
            $productData['depth'],
            $productData['dimensions'],
            $productData['colors'],
            $productData['sizes'],
            $productData['tags'],
            $productData['sku'],
            $productData['attributes'],
            $productData['images'],
            $productData['featured'],
            $productData['published'],
            $productData['status'],
            $productData['viewCount'],
            $productData['salesCount'],
            $productData['discountPercentage'],
            $productData['shortDescription'],
        );

        // Guardar en el repositorio
        $createdProduct = $this->productRepository->create($productEntity);

        Log::info('✅ Producto creado exitosamente', [
            'id' => $createdProduct->getId(),
            'name' => $createdProduct->getName(),
            'images_stored' => $createdProduct->getImages(),
        ]);

        return $createdProduct;
    }

    /**
     * Busca automáticamente el seller_id basándose en el user_id
     *
     * @throws \InvalidArgumentException
     */
    private function getSellerIdFromUserId(int $userId): int
    {
        // Buscar el seller asociado al user_id
        $seller = $this->sellerRepository->findByUserId($userId);

        // Verificar que el usuario sea un vendedor registrado
        if (! $seller) {
            throw new \InvalidArgumentException('El usuario no está registrado como vendedor. Debe registrarse como vendedor antes de crear productos.');
        }

        // Verificar que el vendedor esté activo
        if (! $seller->isActive()) {
            throw new \InvalidArgumentException('La cuenta de vendedor no está activa. Estado actual: '.$seller->getStatus());
        }

        return $seller->getId();
    }

    /**
     * Validar los datos del producto
     *
     * @throws \InvalidArgumentException
     */
    private function validateProductData(array $data): void
    {
        $validator = Validator::make($data, [
            'user_id' => 'required|integer|exists:users,id',
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'status' => 'in:active,inactive,draft',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'user_id.required' => 'El ID de usuario es obligatorio.',
            'user_id.exists' => 'El usuario no existe.',
            'category_id.required' => 'El ID de categoría es obligatorio.',
            'category_id.exists' => 'La categoría no existe.',
            'name.required' => 'El nombre del producto es obligatorio.',
            'description.required' => 'La descripción es obligatoria.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio debe ser mayor o igual a cero.',
            'stock.required' => 'El stock es obligatorio.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser negativo.',
            'status.in' => 'El estado no es válido.',
            'images.*.image' => 'Los archivos deben ser imágenes.',
            'images.*.mimes' => 'Las imágenes deben ser de tipo: jpeg, png, jpg, gif.',
            'images.*.max' => 'Las imágenes no deben superar los 2MB.',
        ]);

        // Lanzar excepción si la validación falla
        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }
    }

    /**
     * ✅ VERSIÓN MEJORADA: Procesar imágenes subidas con logging detallado
     */
    private function processImages(array $files, ?int $userId): ?array
    {
        Log::info('🔄 Iniciando processImages', [
            'files_structure' => array_keys($files),
            'has_images_key' => array_key_exists('images', $files),
            'images_value' => $files['images'] ?? 'no_images_key',
        ]);

        if (empty($files['images'])) {
            Log::info('❌ No hay imágenes para procesar');

            return null;
        }

        // Verificar que sea un array de archivos
        if (! is_array($files['images'])) {
            Log::warning('⚠️ Las imágenes no son un array', [
                'type' => gettype($files['images']),
            ]);

            return null;
        }

        // Generar ruta de subida
        $path = $userId
            ? "products/{$userId}/".now()->format('Y-m-d')
            : 'products/'.now()->format('Y-m-d');

        Log::info('📁 Ruta de subida generada', ['path' => $path]);

        try {
            // Subir imágenes
            $uploadResult = $this->fileUploadService->uploadMultipleImages($files['images'], $path);

            Log::info('📤 Resultado de FileUploadService', [
                'result_type' => gettype($uploadResult),
                'is_array' => is_array($uploadResult),
                'result_content' => $uploadResult,
            ]);

            // Verificar que el resultado sea un array válido
            if (! is_array($uploadResult)) {
                Log::error('❌ FileUploadService no retornó un array', [
                    'returned_type' => gettype($uploadResult),
                    'returned_value' => $uploadResult,
                ]);

                return null;
            }

            if (empty($uploadResult)) {
                Log::warning('⚠️ FileUploadService retornó un array vacío');

                return null;
            }

            Log::info('✅ Imágenes procesadas exitosamente', [
                'images_count' => count($uploadResult),
                'first_image' => $uploadResult[0] ?? 'no_first_image',
            ]);

            return $uploadResult;
        } catch (\Exception $e) {
            Log::error('❌ Error en FileUploadService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-lanzar la excepción para que se maneje en el nivel superior
        }
    }

    /**
     * Procesar campos que pueden venir como JSON o string
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
