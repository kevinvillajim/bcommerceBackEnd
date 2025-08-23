<?php

namespace App\Infrastructure\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio para la carga y manipulación de archivos
 * Especialmente útil para imágenes de productos y categorías
 */
class FileUploadService
{
    /**
     * Tamaños de imagen para productos
     */
    private array $productImageSizes = [
        'thumbnail' => ['width' => 100, 'height' => 100, 'quality' => 80],
        'small' => ['width' => 300, 'height' => 300, 'quality' => 85],
        'medium' => ['width' => 600, 'height' => 600, 'quality' => 85],
        'large' => ['width' => 1200, 'height' => 1200, 'quality' => 90],
    ];

    /**
     * Tamaños de imagen para categorías
     */
    private array $categoryImageSizes = [
        'thumbnail' => ['width' => 100, 'height' => 100, 'quality' => 80],
        'medium' => ['width' => 400, 'height' => 300, 'quality' => 85],
        'banner' => ['width' => 1200, 'height' => 300, 'quality' => 90],
    ];

    /**
     * Tipos MIME permitidos para imágenes
     */
    private array $allowedImageMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Sube múltiples imágenes y genera diferentes tamaños
     *
     * @param  array  $images  Array de archivos subidos
     * @param  string  $path  Ruta base donde guardar las imágenes
     * @param  string  $type  Tipo de imagen (product|category)
     * @return array Información de las imágenes subidas
     */
    public function uploadMultipleImages(array $images, string $path, string $type = 'product'): array
    {
        $uploadedImages = [];

        foreach ($images as $image) {
            $uploadedImages[] = $this->uploadImage($image, $path, $type);
        }

        return $uploadedImages;
    }

    /**
     * Sube una imagen y genera diferentes tamaños
     *
     * @param  UploadedFile  $image  Archivo de imagen
     * @param  string  $path  Ruta base donde guardar la imagen
     * @param  string  $type  Tipo de imagen (product|category)
     * @return array Información de la imagen subida
     */
    public function uploadImage(UploadedFile $image, string $path, string $type = 'product'): array
    {
        // Validar tipo MIME
        if (! in_array($image->getMimeType(), $this->allowedImageMimes)) {
            throw new \InvalidArgumentException('Tipo de archivo no permitido.');
        }

        // Crear un nombre único para la imagen
        $filename = Str::uuid().'.'.$image->getClientOriginalExtension();

        // Seleccionar los tamaños según el tipo
        $sizes = $type === 'product' ? $this->productImageSizes : $this->categoryImageSizes;

        // Usar específicamente el disco 'public'
        $imageInfo = [
            'original' => Storage::disk('public')
                ->putFileAs("{$path}/original", $image, $filename),
        ];

        // Por ahora, todos los tamaños apuntarán a la imagen original
        foreach ($sizes as $sizeName => $sizeConfig) {
            $imageInfo[$sizeName] = $imageInfo['original'];
        }

        return $imageInfo;
    }

    /**
     * Guarda la imagen original en el almacenamiento
     *
     * @param  UploadedFile  $image  Archivo de imagen
     * @param  string  $path  Ruta base
     * @param  string  $filename  Nombre del archivo
     * @return string URL relativa de la imagen guardada
     */
    private function saveOriginalImage(UploadedFile $image, string $path, string $filename): string
    {
        $fullPath = "public/{$path}/original";

        Storage::disk('public')->putFileAs($fullPath, $image, $filename);

        return "{$path}/original/{$filename}";
    }

    /**
     * Elimina todas las versiones de una imagen
     *
     * @param  array  $imageSet  Conjunto de imágenes a eliminar
     * @return bool Éxito de la operación
     */
    public function deleteImage(array $imageSet): bool
    {
        $deleted = true;

        foreach ($imageSet as $path) {
            if (! Storage::delete('public/'.$path)) {
                $deleted = false;
            }
        }

        return $deleted;
    }

    /**
     * Elimina múltiples conjuntos de imágenes
     *
     * @param  array  $imageSets  Array de conjuntos de imágenes
     * @return bool Éxito de la operación
     */
    public function deleteMultipleImages(array $imageSets): bool
    {
        $allDeleted = true;

        foreach ($imageSets as $imageSet) {
            if (! $this->deleteImage($imageSet)) {
                $allDeleted = false;
            }
        }

        return $allDeleted;
    }
}
