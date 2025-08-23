<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\ImageDownloader;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadProductImages extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'products:download-images 
                           {--force : Forzar descarga de todas las imágenes, incluso las ya descargadas}
                           {--limit= : Limitar número de productos a procesar}';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Descarga las imágenes de productos desde URLs externas';

    /**
     * Servicio de descarga de imágenes.
     *
     * @var ImageDownloader
     */
    protected $imageDownloader;

    /**
     * Crear una nueva instancia del comando.
     */
    public function __construct(ImageDownloader $imageDownloader)
    {
        parent::__construct();
        $this->imageDownloader = $imageDownloader;
    }

    /**
     * Ejecutar el comando de consola.
     */
    public function handle()
    {
        $this->info('Iniciando descarga de imágenes de productos...');

        // Comprobar si storage/app/public existe y está enlazado correctamente
        if (! file_exists(public_path('storage'))) {
            $this->warn('El enlace simbólico para el almacenamiento no existe, creándolo...');
            $this->call('storage:link');
        }

        // Opciones
        $forceDownload = $this->option('force');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // Tamaños a generar para cada imagen
        $sizes = [
            'thumbnail' => [150, 150],
            'medium' => [300, 300],
            'large' => [600, 600],
        ];

        // Obtener productos con imágenes externas
        $query = Product::select('id', 'name', 'images', 'category_id');

        if (! $forceDownload) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(images, '$[0].original')) LIKE 'http%'");
        }

        if ($limit) {
            $query->limit($limit);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->info('No hay productos con imágenes externas para procesar.');

            return;
        }

        $this->info("{$products->count()} productos encontrados para procesar.");

        $bar = $this->output->createProgressBar(count($products));
        $bar->start();

        $updated = 0;
        $failed = 0;

        foreach ($products as $product) {
            try {
                // Obtener la categoría para la ruta
                $categoryName = 'misc';
                if ($product->category_id) {
                    $category = \App\Models\Category::find($product->category_id);
                    if ($category) {
                        $categoryName = strtolower(Str::slug($category->name));
                    }
                }

                $images = $product->images ?? [];
                $newImages = [];
                $changed = false;

                foreach ($images as $index => $image) {
                    // Comprobar si es una imagen externa o si debemos forzar la descarga
                    $isExternalImage = isset($image['original']) && strpos($image['original'], 'http') === 0;

                    if ($isExternalImage || $forceDownload) {
                        // Si no es externa pero forzamos, usamos la ruta actual para generar las variantes
                        $url = $isExternalImage
                            ? $image['original']
                            : (Storage::url($image['original']) ?? $image['original']);

                        // Ruta de almacenamiento
                        $basePath = "products/{$categoryName}";
                        $filename = Str::slug($product->name)."-{$index}.jpg";

                        // Generar variantes de la imagen
                        $variants = [];

                        // Descargar la imagen original
                        $originalPath = $this->imageDownloader->downloadImage($url, $basePath, $filename);

                        if ($originalPath) {
                            $variants['original'] = $originalPath;

                            // Crear variantes
                            foreach ($sizes as $sizeName => $dimensions) {
                                $variantFilename = "{$sizeName}-{$filename}";
                                $variantPath = $this->imageDownloader->downloadImage(
                                    $url,
                                    "{$basePath}/{$sizeName}",
                                    $variantFilename
                                );

                                if ($variantPath) {
                                    $variants[$sizeName] = $variantPath;
                                }
                            }

                            $newImages[] = $variants;
                            $changed = true;

                            // Mensaje silencioso para no saturar la salida
                            $this->line("\n<info>✓</info> Imagen {$index} descargada para: {$product->name}");
                        } else {
                            // Si falla la descarga, mantener la imagen original
                            $newImages[] = $image;
                            $this->line("\n<error>✗</error> Error descargando imagen {$index} para: {$product->name}");
                            $failed++;
                        }
                    } else {
                        // Mantener imágenes que no necesitan procesamiento
                        $newImages[] = $image;
                    }
                }

                // Actualizar el producto si hubo cambios
                if ($changed) {
                    $product->images = $newImages;
                    $product->save();
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->error("\nError procesando producto {$product->id}: ".$e->getMessage());
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();

        $this->info("\n\nProcesamiento completado:");
        $this->info("- {$updated} productos actualizados con éxito");
        $this->info("- {$failed} errores encontrados");
    }

    /**
     * Procesa una imagen para un producto específico.
     *
     * @param  Product  $product  El producto al que pertenece la imagen
     * @param  string  $imageUrl  URL de la imagen a procesar
     * @param  string  $baseDirectory  Directorio base para guardar la imagen
     * @return array|null Array con las rutas de las variantes o null si hubo error
     */
    private function processImageForProduct(Product $product, string $imageUrl, string $baseDirectory): ?array
    {
        try {
            // Directorio específico para el producto
            $productSlug = Str::slug($product->name);
            $productDirectory = $baseDirectory.'/'.$productSlug;

            // Tamaños a generar
            $sizes = [
                'thumbnail' => [150, 150],
                'medium' => [300, 300],
                'large' => [600, 600],
            ];

            // Descargar y crear variantes
            return $this->imageDownloader->downloadAndCreateVariants(
                $imageUrl,
                $productDirectory,
                $sizes
            );
        } catch (\Exception $e) {
            $this->error('Error procesando imagen: '.$e->getMessage());

            return null;
        }
    }
}
