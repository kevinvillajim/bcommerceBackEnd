<?php

namespace App\Console\Commands;

use App\Models\Rating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixRatingIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ratings:fix-ids {--dry-run : Run without making changes} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix malformed or discontinuous rating IDs and reset auto-increment sequence';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('=== CORRECCIÓN DE IDs DE RATINGS ===');

        try {
            // 1. Análisis inicial
            $this->info('Analizando estado actual de la tabla ratings...');

            $totalRatings = Rating::count();
            $maxId = Rating::max('id') ?? 0;
            $minId = Rating::min('id') ?? 0;

            $this->table(['Métrica', 'Valor'], [
                ['Total de ratings', $totalRatings],
                ['ID mínimo', $minId],
                ['ID máximo', $maxId],
            ]);

            // Verificar secuencia en SQLite
            $dbDriver = config('database.default');
            $currentSeq = null;
            if ($dbDriver === 'sqlite') {
                $seqInfo = DB::select("SELECT seq FROM sqlite_sequence WHERE name = 'ratings'");
                $currentSeq = $seqInfo[0]->seq ?? 0;
                $this->info("Secuencia SQLite actual: {$currentSeq}");
            }

            // 2. Detectar problemas
            $ratings = Rating::orderBy('id')->get(['id', 'created_at']);
            $problems = [];
            $largeJumps = [];
            $previousId = 0;

            foreach ($ratings as $rating) {
                $currentId = intval($rating->id);

                // Detectar saltos grandes (más de 10)
                if ($previousId > 0 && ($currentId - $previousId) > 10) {
                    $largeJumps[] = [
                        'from' => $previousId,
                        'to' => $currentId,
                        'jump' => $currentId - $previousId,
                    ];
                }

                $previousId = $currentId;
            }

            if (! empty($largeJumps)) {
                $this->warn('Se detectaron saltos grandes en los IDs:');
                $jumpData = [];
                foreach ($largeJumps as $jump) {
                    $jumpData[] = [
                        'Desde ID', $jump['from'],
                        'Hasta ID', $jump['to'],
                        'Salto', $jump['jump'],
                    ];
                }
                $this->table(['Desde ID', 'Hasta ID', 'Salto'], $jumpData);
                $problems[] = 'saltos_grandes';
            }

            // Verificar secuencia incorrecta
            if ($dbDriver === 'sqlite' && $currentSeq !== null && $currentSeq != $maxId) {
                $this->warn("La secuencia SQLite ({$currentSeq}) no coincide con el ID máximo ({$maxId})");
                $problems[] = 'secuencia_incorrecta';
            }

            if (empty($problems)) {
                $this->info('✅ No se detectaron problemas en los IDs de ratings.');

                return Command::SUCCESS;
            }

            // 3. Mostrar plan de corrección
            $this->warn('Se requiere corrección. Plan de acción:');
            $plan = [];

            if (in_array('saltos_grandes', $problems)) {
                $plan[] = '1. Reorganizar IDs en secuencia continua (1, 2, 3, ...)';
            }

            if (in_array('secuencia_incorrecta', $problems)) {
                $plan[] = '2. Actualizar secuencia de auto-incremento';
            }

            $plan[] = '3. Crear backup de seguridad antes de los cambios';
            $plan[] = '4. Verificar integridad después de los cambios';

            foreach ($plan as $step) {
                $this->line($step);
            }

            if ($isDryRun) {
                $this->info('');
                $this->info('=== MODO DRY-RUN: No se realizarán cambios ===');
                $this->info('Para aplicar los cambios, ejecuta: php artisan ratings:fix-ids');

                return Command::SUCCESS;
            }

            // 4. Confirmar ejecución
            if (! $force) {
                if (! $this->confirm('¿Deseas continuar con la corrección?')) {
                    $this->info('Operación cancelada.');

                    return Command::FAILURE;
                }
            }

            // 5. Ejecutar corrección
            $this->info('Iniciando corrección...');

            // Crear backup
            $backupTable = 'ratings_backup_'.date('Ymd_His');
            $this->info("Creando backup en tabla: {$backupTable}");
            DB::statement("CREATE TABLE IF NOT EXISTS {$backupTable} AS SELECT * FROM ratings");

            if (in_array('saltos_grandes', $problems)) {
                $this->info('Reorganizando IDs...');

                // Deshabilitar foreign key checks temporalmente
                if ($dbDriver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys = OFF');
                }

                // Obtener todos los ratings ordenados por fecha de creación
                $allRatings = Rating::orderBy('created_at')->get();

                // Crear tabla temporal
                DB::statement('CREATE TEMPORARY TABLE ratings_temp AS SELECT * FROM ratings WHERE 1=0');

                $newId = 1;
                $bar = $this->output->createProgressBar($allRatings->count());
                $bar->start();

                foreach ($allRatings as $rating) {
                    DB::table('ratings_temp')->insert([
                        'id' => $newId,
                        'user_id' => $rating->user_id,
                        'seller_id' => $rating->seller_id,
                        'order_id' => $rating->order_id,
                        'product_id' => $rating->product_id,
                        'rating' => $rating->rating,
                        'title' => $rating->title,
                        'comment' => $rating->comment,
                        'status' => $rating->status,
                        'type' => $rating->type,
                        'created_at' => $rating->created_at,
                        'updated_at' => $rating->updated_at,
                    ]);
                    $newId++;
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();

                // Reemplazar tabla original
                DB::statement('DELETE FROM ratings');
                DB::statement('INSERT INTO ratings SELECT * FROM ratings_temp');
                DB::statement('DROP TABLE ratings_temp');

                $newMaxId = $newId - 1;

                // Reactivar foreign key checks
                if ($dbDriver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys = ON');
                    // Actualizar secuencia
                    DB::statement("UPDATE sqlite_sequence SET seq = {$newMaxId} WHERE name = 'ratings'");
                }

                $this->info("✅ IDs reorganizados. Nuevo ID máximo: {$newMaxId}");
            }

            if (in_array('secuencia_incorrecta', $problems) && ! in_array('saltos_grandes', $problems)) {
                // Solo actualizar secuencia si no se reorganizaron los IDs
                $this->info('Corrigiendo secuencia de auto-incremento...');

                if ($dbDriver === 'sqlite') {
                    DB::statement("UPDATE sqlite_sequence SET seq = {$maxId} WHERE name = 'ratings'");
                    $this->info("✅ Secuencia actualizada a: {$maxId}");
                }
            }

            // 6. Verificación final
            $this->info('Verificando resultado...');

            $finalTotal = Rating::count();
            $finalMaxId = Rating::max('id') ?? 0;
            $finalMinId = Rating::min('id') ?? 0;

            if ($dbDriver === 'sqlite') {
                $finalSeqInfo = DB::select("SELECT seq FROM sqlite_sequence WHERE name = 'ratings'");
                $finalSeq = $finalSeqInfo[0]->seq ?? 0;
            }

            $this->table(['Métrica', 'Valor Final'], [
                ['Total de ratings', $finalTotal],
                ['ID mínimo', $finalMinId],
                ['ID máximo', $finalMaxId],
                ['Secuencia SQLite', $finalSeq ?? 'N/A'],
            ]);

            // Verificar que no hay más saltos grandes
            $finalRatings = Rating::orderBy('id')->get(['id']);
            $hasRemaining = false;
            $prevId = 0;

            foreach ($finalRatings as $rating) {
                $currentId = intval($rating->id);
                if ($prevId > 0 && ($currentId - $prevId) != 1) {
                    $this->warn("⚠️ Aún hay un salto de {$prevId} a {$currentId}");
                    $hasRemaining = true;
                }
                $prevId = $currentId;
            }

            if (! $hasRemaining) {
                $this->info('✅ Todos los IDs están en secuencia correcta');
            }

            $this->info('');
            $this->info('=== CORRECCIÓN COMPLETADA ===');
            $this->info("Backup guardado en tabla: {$backupTable}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error durante la corrección: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
