<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixMalformedRatingIds extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ratings:fix-ids {--dry-run : Solo mostrar los cambios sin aplicarlos}';

    /**
     * The console command description.
     */
    protected $description = 'Detectar y corregir IDs malformados en la tabla ratings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Detectando IDs malformados en la tabla ratings...');

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️ Modo DRY-RUN: No se harán cambios reales');
        }

        try {
            // Encontrar IDs malformados
            $malformedRatings = $this->findMalformedIds();

            if (empty($malformedRatings)) {
                $this->info('✅ No se encontraron IDs malformados');

                return 0;
            }

            $this->warn('⚠️ Se encontraron '.count($malformedRatings).' IDs malformados:');

            // Mostrar IDs malformados
            $this->table(
                ['ID Actual', 'ID Corregido', 'User ID', 'Rating', 'Tipo'],
                collect($malformedRatings)->map(function ($rating) {
                    return [
                        $rating->id,
                        $this->sanitizeId($rating->id),
                        $rating->user_id,
                        $rating->rating,
                        $rating->type,
                    ];
                })->toArray()
            );

            if (! $dryRun) {
                if ($this->confirm('¿Deseas proceder con la corrección de estos IDs?')) {
                    $this->fixMalformedIds($malformedRatings);
                } else {
                    $this->info('❌ Operación cancelada');

                    return 1;
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            Log::error('Error en FixMalformedRatingIds: '.$e->getMessage());

            return 1;
        }

        return 0;
    }

    /**
     * Encontrar IDs malformados en la tabla ratings
     */
    private function findMalformedIds(): array
    {
        // Buscar IDs que contienen puntos decimales
        $malformedRatings = DB::table('ratings')
            ->whereRaw("id LIKE '%.%'")
            ->orWhereRaw("CAST(id AS CHAR) LIKE '%.%'")
            ->get()
            ->toArray();

        return $malformedRatings;
    }

    /**
     * Sanitizar ID eliminando decimales
     */
    private function sanitizeId($id): int
    {
        if (! $id) {
            return 0;
        }

        $idStr = (string) $id;
        $sanitized = explode('.', $idStr)[0];
        $parsed = (int) $sanitized;

        return max(1, $parsed);
    }

    /**
     * Corregir IDs malformados
     */
    private function fixMalformedIds(array $malformedRatings): void
    {
        $this->info('🔧 Iniciando corrección de IDs malformados...');

        DB::beginTransaction();

        try {
            // Primero, obtener el siguiente ID válido
            $maxValidId = DB::table('ratings')
                ->whereRaw("id NOT LIKE '%.%'")
                ->max('id') ?? 0;

            $nextId = $maxValidId + 1;

            foreach ($malformedRatings as $rating) {
                $originalId = $rating->id;
                $sanitizedId = $this->sanitizeId($originalId);

                // Si el ID sanitizado ya existe, usar el siguiente disponible
                $existsCount = DB::table('ratings')->where('id', $sanitizedId)->count();
                if ($existsCount > 1) { // Más de 1 porque ya existe el malformado
                    $sanitizedId = $nextId++;
                }

                // Actualizar el ID
                DB::table('ratings')
                    ->where('id', $originalId)
                    ->update(['id' => $sanitizedId]);

                $this->line("✅ ID {$originalId} → {$sanitizedId}");

                Log::info("Rating ID corregido: {$originalId} → {$sanitizedId}");
            }

            // Resetear el auto-increment al siguiente valor correcto
            $newMaxId = DB::table('ratings')->max('id');
            $nextAutoIncrement = $newMaxId + 1;

            DB::statement("ALTER TABLE ratings AUTO_INCREMENT = {$nextAutoIncrement}");

            DB::commit();

            $this->info('✅ Se corrigieron '.count($malformedRatings).' IDs malformados');
            $this->info("📈 AUTO_INCREMENT configurado a: {$nextAutoIncrement}");

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
