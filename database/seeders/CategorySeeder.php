<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si ya hay categorías
        if (Category::count() > 0) {
            $this->command->info('Ya existen categorías en la base de datos. Omitiendo seed de categorías.');

            return;
        }

        $categories = [
            [
                'name' => 'Computadoras',
                'description' => 'Descubre nuestra selección de equipos para trabajo y gaming',
                'icon' => 'fa-laptop',
                'featured' => true,
                'subcategories' => [
                    'Laptops',
                    'Computadoras de escritorio',
                    'All-in-One',
                    'Chromebooks',
                    'Mini PC',
                    'Computación',
                ],
            ],
            [
                'name' => 'Componentes',
                'description' => 'Actualiza tu equipo con los mejores componentes del mercado',
                'icon' => 'fa-microchip',
                'featured' => true,
                'subcategories' => [
                    'Procesadores',
                    'Tarjetas gráficas',
                    'Memorias RAM',
                    'Discos duros y SSD',
                    'Placas madre',
                    'Electrónicos',
                ],
            ],
            [
                'name' => 'Dispositivos Móviles',
                'description' => 'Smartphones, tablets y accesorios de última generación',
                'icon' => 'fa-mobile-alt',
                'featured' => true,
                'subcategories' => [
                    'Smartphones',
                    'Tablets',
                    'Smartwatches',
                    'Auriculares',
                    'Auriculares inalámbricos',
                    'Accesorios para móviles',
                ],
            ],
            [
                'name' => 'Periféricos',
                'description' => 'Mejora tu experiencia con periféricos de alta calidad',
                'icon' => 'fa-keyboard',
                'featured' => false,
                'subcategories' => [
                    'Teclados',
                    'Ratones',
                    'Monitores',
                    'Audífonos',
                    'Webcams',
                ],
            ],
            [
                'name' => 'Gaming',
                'description' => 'Todo lo que necesitas para una experiencia de juego inmersiva',
                'icon' => 'fa-gamepad',
                'featured' => true,
                'subcategories' => [
                    'Laptops gaming',
                    'Monitores gaming',
                    'Teclados mecánicos',
                    'Ratones gaming',
                    'Sillas gamer',
                ],
            ],
            [
                'name' => 'Almacenamiento',
                'description' => 'Soluciones de almacenamiento para tus datos más importantes',
                'icon' => 'fa-hdd',
                'featured' => false,
                'subcategories' => [
                    'Discos duros externos',
                    'Unidades SSD',
                    'Memorias USB',
                    'Tarjetas SD',
                    'NAS',
                ],
            ],
            [
                'name' => 'Smart Home',
                'description' => 'Dispositivos inteligentes para automatizar tu hogar',
                'icon' => 'fa-home',
                'featured' => true,
                'subcategories' => [
                    'Asistentes de voz',
                    'Iluminación inteligente',
                    'Seguridad hogar',
                    'Electrodomésticos inteligentes',
                    'Termostatos',
                ],
            ],
            [
                'name' => 'Redes',
                'description' => 'Equipos para mejorar tu conectividad y redes domésticas',
                'icon' => 'fa-wifi',
                'featured' => false,
                'subcategories' => [
                    'Routers',
                    'Repetidores WiFi',
                    'Cables de red',
                    'Adaptadores WiFi',
                    'Sistemas mesh',
                ],
            ],
            // Categorías adicionales para los productos específicos
            [
                'name' => 'Relojes',
                'description' => 'Relojes inteligentes y tradicionales',
                'icon' => 'fa-clock',
                'featured' => false,
                'subcategories' => [],
            ],
            [
                'name' => 'Cámaras',
                'description' => 'Cámaras fotográficas y de video',
                'icon' => 'fa-camera',
                'featured' => false,
                'subcategories' => [],
            ],
            [
                'name' => 'Altavoces',
                'description' => 'Altavoces y sistemas de sonido',
                'icon' => 'fa-volume-up',
                'featured' => false,
                'subcategories' => ['Audio'],
            ],
            [
                'name' => 'Gadgets',
                'description' => 'Dispositivos tecnológicos innovadores',
                'icon' => 'fa-rocket',
                'featured' => false,
                'subcategories' => [],
            ],
            [
                'name' => 'TVs',
                'description' => 'Televisores y pantallas',
                'icon' => 'fa-tv',
                'featured' => false,
                'subcategories' => [],
            ],
        ];

        $this->command->info('Creando categorías y subcategorías...');

        // Crear las categorías principales y sus subcategorías
        foreach ($categories as $categoryData) {
            $subcategories = $categoryData['subcategories'] ?? [];
            unset($categoryData['subcategories']);

            // Generar slug válido (solo minúsculas, números y guiones)
            $baseSlug = Str::slug($categoryData['name']);
            $slug = $this->generateValidSlug($baseSlug);

            // Insertar categoría principal
            $category = Category::create([
                'name' => $categoryData['name'],
                'slug' => $slug,
                'description' => $categoryData['description'] ?? null,
                'icon' => $categoryData['icon'] ?? null,
                'order' => $categoryData['order'] ?? 0,
                'is_active' => $categoryData['is_active'] ?? true,
                'featured' => $categoryData['featured'] ?? false,
            ]);

            // Insertar subcategorías
            foreach ($subcategories as $index => $subcategoryName) {
                // Generar slug válido para la subcategoría
                $baseSubcategorySlug = Str::slug($subcategoryName);
                $subcategorySlug = $this->generateValidSlug($baseSubcategorySlug);

                Category::create([
                    'name' => $subcategoryName,
                    'slug' => $subcategorySlug,
                    'parent_id' => $category->id,
                    'order' => $index + 1,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Se han creado '.Category::count().' categorías en total.');
    }

    /**
     * Genera un slug válido que cumpla con el formato requerido
     */
    private function generateValidSlug(string $baseSlug): string
    {
        // Asegurarse de que el slug no comience ni termine con guiones
        $baseSlug = trim($baseSlug, '-');

        // Añadir un sufijo único para evitar colisiones
        $uniqueSuffix = '-'.substr(md5(uniqid(rand(), true)), 0, 6);
        $slug = $baseSlug.$uniqueSuffix;

        // Verificar que el slug cumpla con el formato válido
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $slug = 'categoria-'.strtolower(Str::random(5));
        }

        return $slug;
    }
}
