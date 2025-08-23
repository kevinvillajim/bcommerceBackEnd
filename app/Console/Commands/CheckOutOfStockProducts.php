<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\NotificationService;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOutOfStockProducts extends Command
{
    protected $signature = 'app:check-out-of-stock';

    protected $description = 'Revisa productos completamente agotados y notifica a los administradores';

    public function handle(NotificationService $notificationService)
    {
        $this->info('Verificando productos sin stock...');

        // Obtener productos que ahora tienen stock 0
        $outOfStockProducts = Product::where('stock', 0)
            ->where('published', true)
            ->where('status', 'active')
            ->get();

        $count = 0;

        foreach ($outOfStockProducts as $product) {
            try {
                $notifications = $notificationService->notifyAdminOutOfStock($product);

                if (count($notifications) > 0) {
                    $count++;
                    $this->info("Notificado: Producto agotado #{$product->id} ({$product->name})");
                }
            } catch (\Exception $e) {
                Log::error("Error notificando producto agotado #{$product->id}: ".$e->getMessage());
                $this->error("Error con producto #{$product->id}: ".$e->getMessage());
            }
        }

        $this->info("Se procesaron {$count} productos agotados de {$outOfStockProducts->count()} encontrados.");
    }
}
