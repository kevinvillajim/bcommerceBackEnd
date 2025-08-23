<?php

namespace App\Http\Middleware;

use App\Models\Seller;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckExpiredFeaturedSellers
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo verificar cada 30 minutos para no sobrecargar
        $cacheKey = 'last_featured_cleanup';
        $lastCleanup = Cache::get($cacheKey);

        if (! $lastCleanup || now()->diffInMinutes($lastCleanup) >= 30) {
            $this->cleanupExpiredFeaturedSellers();
            Cache::put($cacheKey, now(), now()->addMinutes(30));
        }

        return $next($request);
    }

    /**
     * Clean up expired featured sellers
     */
    private function cleanupExpiredFeaturedSellers(): void
    {
        try {
            $expiredSellers = Seller::where('is_featured', true)
                ->whereNotNull('featured_expires_at')
                ->where('featured_expires_at', '<=', now())
                ->get();

            foreach ($expiredSellers as $seller) {
                $seller->update(['is_featured' => false]);

                Log::info('Featured status expired for seller (auto-cleanup)', [
                    'seller_id' => $seller->id,
                    'store_name' => $seller->store_name,
                    'expired_at' => $seller->featured_expires_at,
                    'cleanup_method' => 'middleware',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in auto-cleanup expired featured sellers', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
