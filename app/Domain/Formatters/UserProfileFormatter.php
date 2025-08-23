<?php

namespace App\Domain\Formatters;

use App\Domain\Services\ProfileCompletenessCalculator;
use App\Domain\ValueObjects\UserProfile;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserProfileFormatter
{
    private ProfileCompletenessCalculator $completenessCalculator;

    private ProductFormatter $productFormatter;

    public function __construct(
        ProfileCompletenessCalculator $completenessCalculator,
        ProductFormatter $productFormatter
    ) {
        $this->completenessCalculator = $completenessCalculator;
        $this->productFormatter = $productFormatter;
    }

    /**
     * Formatea un perfil de usuario para la API.
     */
    public function format(UserProfile $userProfile, int $userId): array
    {
        try {
            // Obtener los 10 intereses principales
            $topInterests = array_slice($userProfile->getInterests(), 0, 10, true);

            // Obtener las 5 búsquedas más recientes
            $recentSearches = array_slice($userProfile->getSearchHistory(), 0, 5);

            // Obtener los 5 productos vistos más recientes
            $recentProducts = array_slice($userProfile->getViewedProducts(), 0, 5);

            // Datos demográficos adicionales del usuario
            $user = User::find($userId);
            $demographics = $userProfile->getDemographics();

            if ($user) {
                // Complementar con datos del usuario si están disponibles
                $demographics = array_merge($demographics, [
                    'age' => $user->age ?? null,
                    'gender' => $user->gender ?? null,
                    'location' => $user->location ?? null,
                ]);
            }

            // Calcular intereses principales para formato API
            $formattedTopInterests = $this->calculateTopInterests($userId);

            // Formatear productos recientes
            $formattedRecentProducts = $this->formatRecentProducts($recentProducts);

            return [
                'top_interests' => $formattedTopInterests,
                'recent_searches' => $recentSearches,
                'recent_products' => $formattedRecentProducts,
                'demographics' => $demographics,
                'interaction_score' => $userProfile->getInteractionScore(),
                'profile_completeness' => $this->completenessCalculator->calculate($userProfile),
            ];
        } catch (\Exception $e) {
            Log::error('Error formatting user profile: '.$e->getMessage());

            return [
                'top_interests' => [],
                'recent_searches' => [],
                'recent_products' => [],
                'demographics' => [],
                'interaction_score' => 0,
                'profile_completeness' => 0,
            ];
        }
    }

    /**
     * Formatea los productos recientes para la API.
     */
    private function formatRecentProducts(array $recentProducts): array
    {
        try {
            $formattedProducts = [];

            foreach ($recentProducts as $product) {
                $productId = $product['product_id'] ?? null;
                if ($productId) {
                    $productModel = Product::find($productId);
                    if ($productModel) {
                        $formattedProducts[] = $this->productFormatter->formatForApi($productModel);
                    }
                }
            }

            return $formattedProducts;
        } catch (\Exception $e) {
            Log::error('Error formatting recent products: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Calcula los intereses principales del usuario.
     */
    private function calculateTopInterests(int $userId): array
    {
        try {
            // Intereses basados en categorías
            $categoryInterests = DB::table('user_interactions as ui')
                ->join('products as p', 'ui.item_id', '=', 'p.id')
                ->join('categories as c', 'p.category_id', '=', 'c.id')
                ->where('ui.user_id', $userId)
                ->where('ui.interaction_type', 'view_product')
                ->groupBy('c.id', 'c.name')
                ->select('c.id', 'c.name', DB::raw('count(*) as count'))
                ->orderBy('count', 'desc')
                ->take(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'type' => 'category',
                        'strength' => $item->count,
                    ];
                })
                ->toArray();

            // Intereses basados en tags
            $tagInterests = $this->getTagInterests($userId);

            // Intereses basados en búsquedas
            $searchInterests = UserInteraction::where('user_id', $userId)
                ->where('interaction_type', 'search')
                ->orderBy('interaction_time', 'desc')
                ->take(20)
                ->get()
                ->map(function ($interaction) {
                    return $interaction->metadata['term'] ?? '';
                })
                ->filter()
                ->countBy()
                ->map(function ($count, $term) {
                    return [
                        'id' => null,
                        'name' => $term,
                        'type' => 'search',
                        'strength' => $count,
                    ];
                })
                ->values()
                ->toArray();

            // Combinar y ordenar todos los intereses
            $allInterests = array_merge($categoryInterests, $tagInterests, $searchInterests);
            usort($allInterests, function ($a, $b) {
                return $b['strength'] <=> $a['strength'];
            });

            return array_slice($allInterests, 0, 10);
        } catch (\Exception $e) {
            Log::error('Error calculating top interests: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Obtiene intereses basados en tags.
     */
    private function getTagInterests(int $userId): array
    {
        try {
            // Obtener productos vistos recientemente
            $viewedProductIds = UserInteraction::where('user_id', $userId)
                ->where('interaction_type', 'view_product')
                ->pluck('item_id')
                ->toArray();

            if (empty($viewedProductIds)) {
                return [];
            }

            // Contar frecuencia de tags
            $tagCounts = [];
            $products = Product::whereIn('id', $viewedProductIds)->get();

            foreach ($products as $product) {
                if (is_array($product->tags)) {
                    foreach ($product->tags as $tag) {
                        if (! isset($tagCounts[$tag])) {
                            $tagCounts[$tag] = 0;
                        }
                        $tagCounts[$tag]++;
                    }
                }
            }

            // Convertir a formato estándar
            $tagInterests = [];
            foreach ($tagCounts as $tag => $count) {
                $tagInterests[] = [
                    'id' => null,
                    'name' => $tag,
                    'type' => 'tag',
                    'strength' => $count,
                ];
            }

            // Ordenar por frecuencia
            usort($tagInterests, function ($a, $b) {
                return $b['strength'] <=> $a['strength'];
            });

            return array_slice($tagInterests, 0, 5);
        } catch (\Exception $e) {
            Log::error('Error getting tag interests: '.$e->getMessage());

            return [];
        }
    }
}
