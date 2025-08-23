<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncProductSellerId extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'products:sync-seller-id';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Sincroniza seller_id en productos basado en user_id';

    /**
     * Ejecutar el comando.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Sincronizando seller_id en productos...');

        // Obtener todos los productos sin seller_id pero con user_id
        $products = Product::whereNull('seller_id')
            ->whereNotNull('user_id')
            ->get();

        $this->info("Se encontraron {$products->count()} productos sin seller_id para sincronizar.");

        // Obtener todos los vendedores para búsqueda eficiente
        $sellersByUserId = Seller::all()->keyBy('user_id');
        $this->info("Encontrados {$sellersByUserId->count()} vendedores para mapeo.");

        // Contadores
        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        DB::beginTransaction();

        try {
            foreach ($products as $product) {
                // Buscar el seller por user_id
                if ($sellersByUserId->has($product->user_id)) {
                    $seller = $sellersByUserId[$product->user_id];

                    // Actualizar el seller_id
                    $product->seller_id = $seller->id;
                    $product->save();

                    $this->line(" + Actualizado producto ID {$product->id} ({$product->name}): user_id {$product->user_id} -> seller_id {$seller->id}");
                    $updated++;
                } else {
                    // No se encontró un vendedor asociado
                    $this->warn(" - No se encontró vendedor para user_id {$product->user_id} en producto {$product->id}");
                    $notFound++;
                }
            }

            DB::commit();
            $this->info("Sincronización completada: {$updated} productos actualizados, {$notFound} sin vendedor asociado.");

            if ($notFound > 0) {
                $this->warn("ADVERTENCIA: Hay {$notFound} productos sin un vendedor asociado. Estos productos usarán user_id como seller_id.");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error durante la sincronización: '.$e->getMessage());
            Log::error('Error en SyncProductSellerId: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
