<?php

namespace App\Console\Commands;

use App\Models\SellerOrder;
use App\Models\Shipping;
use Illuminate\Console\Command;

class MigrateShippingToSellerOrders extends Command
{
    protected $signature = 'shipping:migrate-to-seller-orders';

    protected $description = 'Migrar shippings existentes de order_id a seller_order_id';

    public function handle()
    {
        $this->info('Iniciando migración de shippings a seller_orders...');

        $shippings = Shipping::whereNotNull('order_id')->whereNull('seller_order_id')->get();

        $this->info("Encontrados {$shippings->count()} shippings para migrar.");

        $migrated = 0;
        $errors = 0;

        foreach ($shippings as $shipping) {
            try {
                // Buscar SellerOrder para esta Order
                // En caso de múltiples vendedores, tomar el primero o manejar según lógica de negocio
                $sellerOrder = SellerOrder::where('order_id', $shipping->order_id)->first();

                if ($sellerOrder) {
                    $shipping->seller_order_id = $sellerOrder->id;
                    $shipping->save();
                    $migrated++;

                    $this->line("✅ Shipping #{$shipping->id} migrado a SellerOrder #{$sellerOrder->id}");
                } else {
                    $this->error("❌ No se encontró SellerOrder para Order #{$shipping->order_id} (Shipping #{$shipping->id})");
                    $errors++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Error migrando Shipping #{$shipping->id}: ".$e->getMessage());
                $errors++;
            }
        }

        $this->info("\n🎉 Migración completada:");
        $this->info("   ✅ Migrados: {$migrated}");
        $this->info("   ❌ Errores: {$errors}");

        return 0;
    }
}
