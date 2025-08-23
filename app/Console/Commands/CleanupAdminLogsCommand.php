<?php

namespace App\Console\Commands;

use App\Models\AdminLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CleanupAdminLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'logs:cleanup 
                            {--days=30 : Number of days to keep logs (default: 30)}
                            {--batch-size=100 : Number of records to delete per batch}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old admin logs older than specified days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysToKeep = (int) $this->option('days');
        $batchSize = (int) $this->option('batch-size');
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        $this->info('🧹 Admin Logs Cleanup Tool');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Validar parámetros
        if ($daysToKeep < 1) {
            $this->error('❌ Days to keep must be at least 1');

            return 1;
        }

        if ($batchSize < 10 || $batchSize > 1000) {
            $this->error('❌ Batch size must be between 10 and 1000');

            return 1;
        }

        $cutoffDate = now()->subDays($daysToKeep);

        // Obtener estadísticas antes de la limpieza
        $this->showCurrentStats($cutoffDate);

        // Contar logs que serán eliminados
        $logsToDelete = AdminLog::where('created_at', '<', $cutoffDate)->count();

        if ($logsToDelete === 0) {
            $this->info("✅ No logs found older than {$daysToKeep} days. Nothing to clean up.");

            return 0;
        }

        $this->warn("⚠️  Found {$logsToDelete} logs older than {$daysToKeep} days");
        $this->line("📅 Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No logs will actually be deleted');
            $this->showLogsPreview($cutoffDate);

            return 0;
        }

        // Confirmar eliminación (a menos que se use --force)
        if (! $isForced && ! $this->confirm("❓ Are you sure you want to delete these {$logsToDelete} old logs?")) {
            $this->info('❌ Operation cancelled by user');

            return 0;
        }

        // Ejecutar limpieza
        $this->performCleanup($cutoffDate, $batchSize, $logsToDelete);

        return 0;
    }

    /**
     * Mostrar estadísticas actuales de logs
     */
    private function showCurrentStats(Carbon $cutoffDate): void
    {
        $stats = AdminLog::getStats();
        $oldLogs = AdminLog::where('created_at', '<', $cutoffDate)->count();

        $this->line('📊 Current Log Statistics:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total logs', number_format($stats['total'])],
                ['Critical logs', number_format($stats['critical'])],
                ['Error logs', number_format($stats['errors'])],
                ['Logs today', number_format($stats['today'])],
                ['Logs this week', number_format($stats['this_week'])],
                ['Old logs (to delete)', number_format($oldLogs)],
            ]
        );
    }

    /**
     * Mostrar preview de logs que serán eliminados
     */
    private function showLogsPreview(Carbon $cutoffDate): void
    {
        $previewLogs = AdminLog::where('created_at', '<', $cutoffDate)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['level', 'event_type', 'message', 'created_at']);

        if ($previewLogs->isNotEmpty()) {
            $this->line("\n🔍 Preview of logs that would be deleted:");
            $this->table(
                ['Level', 'Event Type', 'Message', 'Created At'],
                $previewLogs->map(function ($log) {
                    return [
                        $log->level,
                        $log->event_type,
                        Str::limit($log->message, 40),
                        $log->created_at->format('Y-m-d H:i:s'),
                    ];
                })->toArray()
            );
        }
    }

    /**
     * Ejecutar la limpieza de logs
     */
    private function performCleanup(Carbon $cutoffDate, int $batchSize, int $totalToDelete): void
    {
        $this->line("\n🚀 Starting cleanup process...");

        $progressBar = $this->output->createProgressBar($totalToDelete);
        $progressBar->setFormat('verbose');

        $totalDeleted = 0;
        $batchNumber = 0;
        $startTime = microtime(true);

        do {
            $batchNumber++;

            // Eliminar lote
            $deleted = AdminLog::where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();

            $totalDeleted += $deleted;
            $progressBar->advance($deleted);

            // Pequeña pausa para no sobrecargar la BD
            if ($deleted > 0) {
                usleep(10000); // 10ms entre lotes
            }

            // Mostrar progreso cada 10 lotes
            if ($batchNumber % 10 === 0) {
                $this->line("\n📈 Processed {$batchNumber} batches, deleted {$totalDeleted} logs so far...");
            }

        } while ($deleted > 0);

        $progressBar->finish();

        $duration = round(microtime(true) - $startTime, 2);
        $this->line("\n");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($totalDeleted > 0) {
            $this->info('✅ Cleanup completed successfully!');
            $this->line("📊 Deleted: {$totalDeleted} logs");
            $this->line("⏱️  Duration: {$duration}s");
            $this->line("📦 Batches: {$batchNumber}");
            $this->line('🔄 Avg per batch: '.round($totalDeleted / max(1, $batchNumber), 1));

            // Mostrar estadísticas después de la limpieza
            $this->line("\n📊 Updated Statistics:");
            $newStats = AdminLog::getStats();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total logs remaining', number_format($newStats['total'])],
                    ['Critical logs', number_format($newStats['critical'])],
                    ['Error logs', number_format($newStats['errors'])],
                ]
            );
        } else {
            $this->warn('⚠️  No logs were deleted. They may have been already cleaned up.');
        }
    }

    /**
     * Mostrar ayuda adicional
     */
    private function showHelp(): void
    {
        $this->line('📖 Usage Examples:');
        $this->line('  php artisan logs:cleanup                    # Clean logs older than 30 days');
        $this->line('  php artisan logs:cleanup --days=7           # Clean logs older than 7 days');
        $this->line('  php artisan logs:cleanup --dry-run          # Preview what would be deleted');
        $this->line('  php artisan logs:cleanup --force            # Skip confirmation');
        $this->line('  php artisan logs:cleanup --batch-size=50    # Use smaller batches');
    }
}
