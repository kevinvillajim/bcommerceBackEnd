<?php

namespace App\Infrastructure\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageDownloader
{
    /**
     * Descarga una imagen desde una URL y la guarda en el almacenamiento.
     *
     * @param  string  $url  URL de la imagen a descargar
     * @param  string  $path  Ruta donde guardar la imagen (sin nombre de archivo)
     * @param  string|null  $filename  Nombre del archivo (si es null, se genera automáticamente)
     * @return string|null Ruta completa donde se guardó la imagen o null si hubo un error
     */
    public function downloadImage(string $url, string $path, ?string $filename = null): ?string
    {
        try {
            // Crear el directorio si no existe
            Storage::makeDirectory($path);

            // Generar nombre de archivo si no se proporciona
            if ($filename === null) {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                $extension = $extension ?: 'jpg'; // Default to jpg if extension cannot be determined
                $filename = uniqid('img_').'.'.$extension;
            }

            // Ruta completa donde se guardará la imagen
            $fullPath = $path.'/'.$filename;

            // Descargar la imagen
            $response = Http::timeout(15)->get($url);

            if ($response->successful()) {
                // Guardar la imagen en el storage
                Storage::put($fullPath, $response->body());

                return $fullPath;
            } else {
                Log::error("Error descargando imagen. URL: {$url}, Código: {$response->status()}");

                return null;
            }
        } catch (Exception $e) {
            Log::error('Excepción descargando imagen: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Crea diferentes tamaños de una imagen para thumbnails.
     *
     * @param  string  $originalPath  Ruta de la imagen original
     * @param  array  $sizes  Array asociativo con tamaños deseados ['thumbnail' => [width, height], 'medium' => [...]]
     * @return array|null Array con las rutas de las diferentes versiones o null si hubo un error
     */
    public function createImageVariants(string $originalPath, array $sizes): ?array
    {
        try {
            if (! Storage::exists($originalPath)) {
                Log::error("La imagen original no existe: {$originalPath}");

                return null;
            }

            $variants = [];
            $variants['original'] = $originalPath;

            // Obtener información de la imagen
            $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
            $basename = pathinfo($originalPath, PATHINFO_FILENAME);
            $directory = pathinfo($originalPath, PATHINFO_DIRNAME);

            // Crear versiones redimensionadas
            foreach ($sizes as $name => $dimensions) {
                [$width, $height] = $dimensions;

                $newPath = "{$directory}/{$name}_{$basename}.{$extension}";

                // Leer la imagen original
                $originalContent = Storage::get($originalPath);
                $originalImage = imagecreatefromstring($originalContent);

                // Obtener dimensiones originales
                $originalWidth = imagesx($originalImage);
                $originalHeight = imagesy($originalImage);

                // Crear nueva imagen
                $newImage = imagecreatetruecolor($width, $height);

                // Preservar transparencia para PNG
                if ($extension === 'png') {
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                }

                // Redimensionar
                imagecopyresampled(
                    $newImage,
                    $originalImage,
                    0,
                    0,
                    0,
                    0,
                    $width,
                    $height,
                    $originalWidth,
                    $originalHeight
                );

                // Guardar la nueva imagen
                ob_start();
                if ($extension === 'jpg' || $extension === 'jpeg') {
                    imagejpeg($newImage, null, 90);
                } elseif ($extension === 'png') {
                    imagepng($newImage, null, 9);
                } elseif ($extension === 'gif') {
                    imagegif($newImage);
                }
                $imageData = ob_get_clean();

                Storage::put($newPath, $imageData);

                // Liberar memoria
                imagedestroy($newImage);

                $variants[$name] = $newPath;
            }

            imagedestroy($originalImage);

            return $variants;
        } catch (Exception $e) {
            Log::error('Error creando variantes de imagen: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Descarga una imagen y crea sus variantes en un solo paso.
     *
     * @param  string  $url  URL de la imagen a descargar
     * @param  string  $path  Ruta donde guardar la imagen
     * @param  array  $sizes  Tamaños de las variantes
     * @return array|null Array con las rutas de todas las versiones o null si hubo un error
     */
    public function downloadAndCreateVariants(string $url, string $path, array $sizes): ?array
    {
        $originalPath = $this->downloadImage($url, $path);

        if (! $originalPath) {
            return null;
        }

        return $this->createImageVariants($originalPath, $sizes);
    }
}
