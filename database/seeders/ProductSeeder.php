<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    protected $faker;

    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $this->faker = Faker::create('es_ES');

        // Verificar primero si hay categorías
        if (Category::count() == 0) {
            $this->command->info('Creando categorías básicas...');

            // Crear manualmente unas categorías básicas
            $categories = [
                ['name' => 'Electrónicos', 'slug' => 'electronicos', 'description' => 'Todo tipo de dispositivos electrónicos'],
                ['name' => 'Computación', 'slug' => 'computacion', 'description' => 'Equipos y accesorios de computación'],
                ['name' => 'Smartphones', 'slug' => 'smartphones', 'description' => 'Teléfonos inteligentes de todas las marcas'],
                ['name' => 'Audio', 'slug' => 'audio', 'description' => 'Equipos de sonido y audio'],
                ['name' => 'Auriculares inalámbricos', 'slug' => 'auriculares_bt', 'description' => 'Auriculares inalámbricos bluetooth'],
                ['name' => 'Accesorios para móviles', 'slug' => 'acc_movil', 'description' => 'Accesorios para móviles'],
                ['name' => 'Smartwatches', 'slug' => 'smartwatches', 'description' => 'Relojes inteligentes'],
            ];

            foreach ($categories as $category) {
                Category::create($category);
            }
        }

        // Verifica si ya existen productos
        if (Product::count() > 0) {
            $this->command->info('Ya existen productos en la base de datos. Omitiendo seed de productos.');

            return;
        }

        // PASO 1: Crear primero los productos manuales
        $mockProducts = $this->getMockProducts();
        $manualProductIds = [];

        $this->command->info('Creando productos manuales con información detallada...');

        foreach ($mockProducts as $mockProduct) {
            // Buscar la categoría
            $category = Category::where('name', $mockProduct['category'])->first();

            if (! $category) {
                $this->command->error("Categoría no encontrada: {$mockProduct['category']}");

                continue;
            }

            try {
                // Obtener un usuario aleatorio
                $user = User::inRandomOrder()->first() ?? User::factory()->create();

                // Generar un slug válido según los requisitos
                $baseSlug = Str::slug($mockProduct['name']);
                $slug = $this->generateValidSlug($baseSlug);

                // Crear el producto
                $product = new Product;
                // NO asignar ID explícitamente, dejar que sea autoincremental
                $product->user_id = $user->id;
                $product->category_id = $category->id;
                $product->name = $mockProduct['name'];
                $product->slug = $slug;
                $product->description = $mockProduct['description'];
                $product->short_description = Str::limit(strip_tags($mockProduct['description']), 160);
                $product->price = $mockProduct['price'];
                $product->stock = rand(10, 100);
                $product->discount_percentage = $mockProduct['discount'] ?? 0;

                // Imágenes
                $product->images = [
                    [
                        'original' => $mockProduct['image'],
                        'thumbnail' => $mockProduct['image'],
                        'medium' => $mockProduct['image'],
                        'large' => $mockProduct['image'],
                    ],
                ];

                // Datos importantes de rating - usar exactamente los proporcionados si existen
                $product->rating = $mockProduct['rating'] ?? rand(3, 5);
                $product->rating_count = $mockProduct['rating_count'] ?? rand(10, 100);

                // Estadísticas - dando valores altos para destacar
                $product->view_count = rand(200, 1000);
                $product->sales_count = rand(50, 200);

                // Estados
                $product->featured = true; // Todos los manuales serán destacados
                $product->published = true;
                $product->status = 'active';

                // Guardar el producto
                $product->save();
                $manualProductIds[] = $product->id;

                $this->command->info("  ✓ Producto creado: {$product->name} (ID: {$product->id}, Rating: {$product->rating}, Votos: {$product->rating_count})");
            } catch (\Exception $e) {
                $this->command->error("Error al crear producto '{$mockProduct['name']}': ".$e->getMessage());
            }
        }

        $this->command->info('Total de productos manuales creados: '.count($manualProductIds));

        // PASO 2: Ahora, crear productos aleatorios (tendrán IDs más altos)
        $randomProductCount = 20; // Ajusta esta cantidad según necesites

        $this->command->info("Creando {$randomProductCount} productos aleatorios adicionales...");

        // Crear productos aleatorios con valores de rating aleatorios pero menores que los manuales
        for ($i = 0; $i < $randomProductCount; $i++) {
            try {
                $product = Product::factory()->create([
                    'featured' => false, // Los aleatorios no serán destacados
                    'rating' => $this->faker->randomFloat(1, 1.0, 3.0), // Calificaciones bajas
                    'rating_count' => $this->faker->numberBetween(1, 20), // Pocos votos
                    'view_count' => $this->faker->numberBetween(10, 100), // Menos vistas
                    'sales_count' => $this->faker->numberBetween(1, 20), // Menos ventas
                ]);
            } catch (\Exception $e) {
                $this->command->error('Error al crear producto aleatorio: '.$e->getMessage());
            }
        }

        $this->command->info('¡Seeder de productos completado!');
        $this->command->info('Total de productos creados: '.Product::count());

        // Verificar que los productos manuales estén correctamente en la base de datos
        $this->command->info("\nVerificando productos manuales:");
        foreach ($manualProductIds as $id) {
            $prod = Product::find($id);
            $this->command->info(" - {$prod->name}: Rating {$prod->rating} ({$prod->rating_count} votos)");
        }
    }

    /**
     * Lista de productos proporcionados manualmente con toda su información.
     * Estos aparecerán primero en las consultas.
     */
    private function getMockProducts(): array
    {
        return [
            [
                // No incluir ID explícito
                'name' => 'Auriculares inalámbricos',
                'description' => 'Auriculares premium con cancelación de ruido y 30h de batería',
                'price' => 199.99,
                'discount' => 15, // 15% de descuento
                'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Auriculares inalámbricos',
                'rating' => 4.2,
                'rating_count' => 202,
            ],
            [
                // No incluir ID explícito
                'name' => 'Smartwatch Pro',
                'description' => 'Monitoriza tu estado físico, salud y mantente conectado con estilo',
                'price' => 249.99,
                'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Smartwatches',
                'rating' => 3.2,
                'rating_count' => 112,
            ],
            [
                // No incluir ID explícito
                'name' => 'Cámara de acción 4K',
                'description' => 'Captura tus aventuras en impresionante resolución 4K',
                'price' => 329.99,
                'discount' => 20, // 20% de descuento
                'image' => 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Accesorios para móviles',
                'rating' => 4.0,
                'rating_count' => 94,
            ],
            [
                // No incluir ID explícito
                'name' => 'iWatch Ultra',
                'description' => 'Alto rendimiento en un diseño elegante y ligero',
                'price' => 1099.99,
                'image' => 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Smartwatches',
                'rating' => 4.7,
                'rating_count' => 189,
            ],
            [
                // No incluir ID explícito
                'name' => 'Altavoz inteligente',
                'description' => 'Altavoz con control por voz y calidad de audio premium',
                'price' => 129.99,
                'discount' => 10, // 10% de descuento
                'image' => 'https://images.unsplash.com/photo-1589003077984-894e133dabab?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Audio',
                'rating' => 4.1,
                'rating_count' => 156,
            ],
            [
                // No incluir ID explícito
                'name' => 'Gafas inteligentes',
                'description' => 'Gafas con bluetooth, música in ear y sistema operativo android, manejable con los ojos',
                'price' => 149.99,
                'image' => 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Accesorios para móviles',
                'rating' => 3.8,
                'rating_count' => 78,
            ],
            [
                // No incluir ID explícito
                'name' => 'Smartphone Galaxy S25',
                'description' => 'El último smartphone con cámara de 108MP y pantalla AMOLED de 6.8 pulgadas',
                'price' => 1299.99,
                'discount' => 12,
                'image' => 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Smartphones',
                'rating' => 4.6,
                'rating_count' => 312,
            ],
            [
                // No incluir ID explícito
                'name' => 'Laptop Pro X',
                'description' => 'Potente laptop con procesador de última generación y 32GB de RAM',
                'price' => 1899.99,
                'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Computación',
                'rating' => 4.9,
                'rating_count' => 275,
            ],
            [
                // No incluir ID explícito
                'name' => 'Monitor UltraWide 34"',
                'description' => 'Monitor curvo ultrawide con resolución 4K y tecnología HDR',
                'price' => 599.99,
                'discount' => 8,
                'image' => 'https://images.unsplash.com/photo-1616763355548-1b606f439f86?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Computación',
                'rating' => 4.5,
                'rating_count' => 164,
            ],
            [
                // No incluir ID explícito
                'name' => 'TV OLED 65"',
                'description' => 'Televisor OLED de 65 pulgadas con tecnología de imagen avanzada',
                'price' => 2499.99,
                'discount' => 15,
                'image' => 'https://images.unsplash.com/photo-1593784991095-a205069470b6?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Electrónicos',
                'rating' => 4.4,
                'rating_count' => 208,
            ],
            [
                // No incluir ID explícito
                'name' => 'Auriculares Gaming',
                'description' => 'Auriculares con micrófono de alta calidad y sonido envolvente 7.1',
                'price' => 159.99,
                'image' => 'https://images.unsplash.com/photo-1591370874773-6702e8f12fd8?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Auriculares inalámbricos',
                'rating' => 4.3,
                'rating_count' => 198,
            ],
            [
                // No incluir ID explícito
                'name' => 'Tablet Pro 12.9',
                'description' => 'Tablet de 12.9 pulgadas con pantalla Retina y procesador potente',
                'price' => 1099.99,
                'discount' => 10,
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80',
                'category' => 'Electrónicos',
                'rating' => 4.7,
                'rating_count' => 245,
            ],
        ];
    }

    /**
     * Genera un slug válido que cumpla con el formato requerido
     */
    private function generateValidSlug(string $baseSlug): string
    {
        // Asegurarse de que el slug no comience ni termine con guiones
        $baseSlug = trim($baseSlug, '-');

        // Verificar si es único
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        // Verificar que el slug cumpla con el formato válido
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            // Si no cumple, creamos uno más simple
            $slug = 'producto-'.strtolower(Str::random(5));
        }

        return $slug;
    }
}
