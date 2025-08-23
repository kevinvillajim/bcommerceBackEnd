<?php

namespace App\UseCases\User;

use App\Domain\Entities\RatingEntity;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\RatingRepositoryInterface;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\Rating;
use Illuminate\Support\Facades\Log;

class RateSellerUseCase
{
    private RatingRepositoryInterface $ratingRepository;

    private SellerRepositoryInterface $sellerRepository;

    private OrderRepositoryInterface $orderRepository;

    private UserRepositoryInterface $userRepository;

    /**
     * Constructor
     */
    public function __construct(
        RatingRepositoryInterface $ratingRepository,
        SellerRepositoryInterface $sellerRepository,
        OrderRepositoryInterface $orderRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->ratingRepository = $ratingRepository;
        $this->sellerRepository = $sellerRepository;
        $this->orderRepository = $orderRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Ejecuta el caso de uso
     *
     * @param  int  $userId  ID del usuario que califica
     * @param  int  $sellerId  ID del vendedor a calificar
     * @param  float  $rating  Calificación (1-5)
     * @param  int|null  $orderId  ID de la orden relacionada (opcional)
     * @param  int|null  $productId  ID del producto relacionado (opcional)
     * @param  string|null  $title  Título de la calificación (opcional)
     * @param  string|null  $comment  Comentario de la calificación (opcional)
     */
    public function execute(
        int $userId,
        int $sellerId,
        float $rating,
        ?int $orderId = null,
        ?int $productId = null,
        ?string $title = null,
        ?string $comment = null
    ): array {
        try {
            // Validar rating
            if ($rating < 1 || $rating > 5) {
                return [
                    'success' => false,
                    'message' => 'La calificación debe estar entre 1 y 5',
                ];
            }

            // Verificar que el usuario existe
            $user = $this->userRepository->findById($userId);
            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ];
            }

            // Verificar que el vendedor existe
            $seller = $this->sellerRepository->findById($sellerId);
            if (! $seller) {
                return [
                    'success' => false,
                    'message' => 'Vendedor no encontrado',
                ];
            }

            // Si se proporciona un ID de orden, verificar que existe y pertenece al usuario
            if ($orderId) {
                $order = $this->orderRepository->findById($orderId);
                if (! $order) {
                    return [
                        'success' => false,
                        'message' => 'Orden no encontrada',
                    ];
                }

                if ($order->getUserId() !== $userId) {
                    return [
                        'success' => false,
                        'message' => 'La orden no pertenece a este usuario',
                    ];
                }

                if ($order->getSellerId() !== $sellerId) {
                    return [
                        'success' => false,
                        'message' => 'La orden no corresponde a este vendedor',
                    ];
                }

                // Verificar si el usuario ya calificó esta orden
                if ($this->ratingRepository->hasUserRatedSellerForOrder($userId, $sellerId, $orderId)) {
                    return [
                        'success' => false,
                        'message' => 'Ya has calificado esta orden',
                    ];
                }
            } else {
                // Si no hay orden, verificar si ya calificó al vendedor recientemente
                if ($this->ratingRepository->hasUserRatedSellerRecently($userId, $sellerId)) {
                    return [
                        'success' => false,
                        'message' => 'Ya has calificado a este vendedor recientemente',
                    ];
                }
            }

            // Crear la entidad de calificación
            $ratingEntity = new RatingEntity(
                $userId,
                $rating,
                Rating::TYPE_USER_TO_SELLER, // Asumimos una constante similar a la de seller_to_user
                null, // El usuario no tiene seller_id
                $orderId,
                $productId,
                $title,
                $comment,
                Rating::STATUS_PENDING // Las calificaciones empiezan como pendientes
            );

            // Guardar la calificación
            $savedRating = $this->ratingRepository->create($ratingEntity);

            return [
                'success' => true,
                'message' => 'Calificación registrada exitosamente',
                'data' => [
                    'rating_id' => $savedRating->getId(),
                    'rating' => $savedRating->getRating(),
                    'seller_id' => $sellerId,
                    'order_id' => $orderId,
                    'product_id' => $productId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error en RateSellerUseCase: '.$e->getMessage(), [
                'user_id' => $userId,
                'seller_id' => $sellerId,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al procesar la calificación: '.$e->getMessage(),
            ];
        }
    }
}
