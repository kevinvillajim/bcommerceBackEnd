<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FindValidProduct extends Command
{
    protected $signature = 'debug:find-valid-product';
    protected $description = 'Encuentra productos con seller_id válido';

    public function handle()
    {
        $this->info("Buscando productos con seller_id válido...");
        
        $products = DB::table('products')
            ->whereNotNull('seller_id')
            ->select('id', 'name', 'price', 'seller_id', 'discount_percentage')
            ->limit(5)
            ->get();
            
        if ($products->count() > 0) {
            $this->table(['ID', 'Nombre', 'Precio', 'Seller ID', 'Descuento'], 
                $products->map(fn($p) => [
                    $p->id, 
                    substr($p->name, 0, 30), 
                    $p->price, 
                    $p->seller_id, 
                    $p->discount_percentage ?? 0
                ])->toArray()
            );
        } else {
            $this->error("No se encontraron productos con seller_id válido");
        }
    }
}