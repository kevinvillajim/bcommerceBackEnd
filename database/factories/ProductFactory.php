<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        $price = $this->faker->randomFloat(2, 10, 2500);
        $discounted = $this->faker->boolean(30);
        $discountPercentage = $discounted ? $this->faker->randomElement([5, 10, 15, 20, 25]) : 0;

        // Generar un slug válido según los requisitos
        $baseSlug = Str::slug($name);
        $validSlug = $this->generateValidSlug($baseSlug);

        // Imagen aleatoria de Unsplash
        $imageId = $this->faker->numberBetween(1, 500);
        $image = "https://picsum.photos/seed/tech{$imageId}/1200/800";

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'name' => ucfirst($name),
            'slug' => $validSlug,
            'description' => $this->faker->paragraph(),
            'short_description' => $this->faker->sentence(),
            'price' => $price,
            'stock' => $this->faker->numberBetween(0, 50), // Menos stock que los manuales
            'discount_percentage' => $discountPercentage,
            'images' => [
                [
                    'original' => $image,
                    'thumbnail' => $image,
                    'medium' => $image,
                    'large' => $image,
                ],
            ],
            'featured' => $this->faker->boolean(10), // Solo 10% de probabilidad de ser destacado
            'published' => true,
            'status' => 'active',
            'rating' => $this->faker->randomFloat(1, 1.0, 3.5), // Ratings más bajos que los manuales
            'rating_count' => $this->faker->numberBetween(1, 30), // Menos votos que los manuales
            'view_count' => $this->faker->numberBetween(5, 100), // Menos vistas
            'sales_count' => $this->faker->numberBetween(0, 20), // Menos ventas
            // Aseguramos que no incluimos el campo seller_id que no existe
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

    /**
     * Producto destacado.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    /**
     * Producto con descuento.
     */
    public function discounted(float $percentage = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percentage' => $percentage,
        ]);
    }

    /**
     * Producto sin stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * Asigna una categoría existente.
     */
    public function inCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }
}
