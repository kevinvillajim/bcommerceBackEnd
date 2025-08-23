<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $randomSuffix = $this->faker->randomNumber(3);
        $name = $this->faker->randomElement([
            'Accesorios '.$randomSuffix,
            'Novedades '.$randomSuffix,
            'Ofertas '.$randomSuffix,
            'Productos destacados '.$randomSuffix,
            'Importados '.$randomSuffix,
        ]);

        // Generar un slug que cumpla con la expresión regular /^[a-z0-9]+(?:-[a-z0-9]+)*$/
        // Generar un slug único
        $slug = Str::slug($name);
        $validSlug = $this->generateValidSlug($slug);

        return [
            'name' => $name,
            'slug' => $validSlug,
            'description' => $this->faker->sentence(),
            'parent_id' => null,
            'icon' => 'fa-tag',
            'image' => 'https://images.unsplash.com/photo-'.$this->faker->randomNumber(8, true).'?auto=format&fit=crop&w=800&q=80',
            'order' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
            'featured' => $this->faker->boolean(20),
        ];
    }

    /**
     * Genera un slug válido que cumpla con el formato requerido /^[a-z0-9]+(?:-[a-z0-9]+)*$/
     */
    private function generateValidSlug(string $baseSlug): string
    {
        // Asegurarse de que el slug no comience ni termine con guiones
        $baseSlug = trim($baseSlug, '-');

        // Verificar si es único
        $slug = $baseSlug;
        $counter = 1;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        // Verificar que el slug cumpla con el formato válido
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            // Si no cumple, creamos uno más simple
            $slug = 'categoria-'.strtolower(Str::random(5));
        }

        return $slug;
    }

    /**
     * Indica que la categoría es destacada.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    /**
     * Indica que la categoría es una subcategoría.
     */
    public function subcategoryOf(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }
}
