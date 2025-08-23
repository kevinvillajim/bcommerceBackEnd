<?php

namespace App\Http\Controllers;

use App\Domain\Repositories\FeedbackRepositoryInterface;
use App\Http\Requests\ApplyDiscountRequest;
use App\Http\Requests\FeedbackRequest;
use App\Http\Requests\ReviewFeedbackRequest;
use App\Models\Seller;
use App\Models\User;
use App\UseCases\Feedback\ApplyDiscountCodeUseCase;
use App\UseCases\Feedback\GenerateDiscountCodeUseCase;
use App\UseCases\Feedback\ReviewFeedbackUseCase;
use App\UseCases\Feedback\SubmitFeedbackUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    private FeedbackRepositoryInterface $feedbackRepository;

    /**
     * FeedbackController constructor.
     */
    public function __construct(FeedbackRepositoryInterface $feedbackRepository)
    {
        $this->feedbackRepository = $feedbackRepository;
    }

    /**
     * Display a listing of user's feedbacks.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            $feedbacks = $this->feedbackRepository->findByUserId($userId, $limit, $offset);
            $total = $this->feedbackRepository->count(['user_id' => $userId]);

            // Obtener todos los user_ids únicos para una sola consulta optimizada
            $userIds = array_unique(array_map(function ($feedback) {
                return $feedback->getUserId();
            }, $feedbacks));

            // Cargar usuarios con sus sellers de una sola vez usando eager loading
            $users = User::with('seller')->whereIn('id', $userIds)->get()->keyBy('id');

            $formattedFeedbacks = array_map(function ($feedback) use ($users) {
                $feedbackData = $feedback->toArray();

                // Obtener user con su relación seller ya cargada
                $user = $users->get($feedback->getUserId());
                if ($user) {
                    $feedbackData['user'] = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];

                    // Si el usuario es seller, agregar información de seller
                    if ($user->seller) {
                        $seller = $user->seller;
                        $feedbackData['seller'] = [
                            'id' => $seller->id,
                            'store_name' => $seller->store_name,
                            'user_id' => $seller->user_id,
                        ];
                        $feedbackData['seller_id'] = $seller->id;

                        // If feedback is approved and seller was featured, include seller featured info
                        if ($feedback->getStatus() === 'approved' && $seller->featured_reason === 'feedback') {
                            $feedbackData['seller_featured'] = [
                                'featured_at' => $seller->featured_at?->toISOString(),
                                'featured_expires_at' => $seller->featured_expires_at?->toISOString(),
                                'featured_days' => $seller->featured_at && $seller->featured_expires_at
                                    ? $seller->featured_at->diffInDays($seller->featured_expires_at)
                                    : 15,
                                'is_active' => $seller->isCurrentlyFeatured(),
                            ];
                        }
                    }
                }

                // Si el feedback está aprobado, buscar código de descuento
                if ($feedback->getStatus() === 'approved') {
                    $discountCode = \App\Models\DiscountCode::where('feedback_id', $feedback->getId())->first();
                    if ($discountCode) {
                        $isExpired = $discountCode->expires_at && now()->isAfter($discountCode->expires_at);
                        $daysLeft = 0;
                        if (! $discountCode->is_used && ! $isExpired) {
                            $daysLeft = max(0, now()->diffInDays($discountCode->expires_at, false));
                        }

                        $feedbackData['discount_code'] = [
                            'code' => $discountCode->code,
                            'discount_percentage' => $discountCode->discount_percentage,
                            'is_used' => $discountCode->is_used,
                            'used_at' => $discountCode->used_at,
                            'expires_at' => $discountCode->expires_at,
                            'is_expired' => $isExpired,
                            'days_left' => $daysLeft,
                        ];
                    }
                }

                return $feedbackData;
            }, $feedbacks);

            return response()->json([
                'status' => 'success',
                'data' => $formattedFeedbacks,
                'meta' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching feedbacks: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching feedbacks',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created feedback.
     */
    public function store(FeedbackRequest $request, SubmitFeedbackUseCase $submitFeedbackUseCase): JsonResponse
    {
        try {
            $userId = Auth::id();
            $feedback = $submitFeedbackUseCase->execute(
                $userId,
                $request->input('title'),
                $request->input('description'),
                $request->input('type')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback submitted successfully. Our team will review it soon.',
                'data' => $feedback->toArray(),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error submitting feedback: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error submitting feedback',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified feedback.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $feedback = $this->feedbackRepository->findById($id);

            if (! $feedback) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Feedback not found',
                ], 404);
            }

            // Check if the user is authorized to view this feedback
            $userId = Auth::id();
            if ($feedback->getUserId() !== $userId && ! Auth::user()->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized to view this feedback',
                ], 403);
            }

            $feedbackData = $feedback->toArray();

            // Load user information with seller relationship
            $user = User::with('seller')->find($feedback->getUserId());
            if ($user) {
                $feedbackData['user'] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];

                // Si el usuario es seller, agregar información de seller
                if ($user->seller) {
                    $seller = $user->seller;
                    $feedbackData['seller'] = [
                        'id' => $seller->id,
                        'store_name' => $seller->store_name,
                        'user_id' => $seller->user_id,
                    ];
                    $feedbackData['seller_id'] = $seller->id;

                    // If feedback is approved and seller was featured, include seller featured info
                    if ($feedback->getStatus() === 'approved' && $seller->featured_reason === 'feedback') {
                        $feedbackData['seller_featured'] = [
                            'featured_at' => $seller->featured_at?->toISOString(),
                            'featured_expires_at' => $seller->featured_expires_at?->toISOString(),
                            'featured_days' => $seller->featured_at && $seller->featured_expires_at
                                ? $seller->featured_at->diffInDays($seller->featured_expires_at)
                                : 15,
                            'is_active' => $seller->isCurrentlyFeatured(),
                        ];
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $feedbackData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching feedback: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching feedback',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of feedbacks with filters (admin only).
     * If no status filter is provided, shows all feedbacks. If status=pending, shows only pending.
     */
    public function pendingFeedbacks(Request $request): JsonResponse
    {
        try {
            if (! Auth::user()->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 403);
            }

            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $status = $request->input('status');
            $type = $request->input('type');
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            // Build filters
            $filters = [];
            if ($status && $status !== 'all') {
                $filters['status'] = $status;
            }
            if ($type && $type !== 'all') {
                $filters['type'] = $type;
            }

            // Get feedbacks using Eloquent directly to handle date filters
            $query = \App\Models\Feedback::query()->orderBy('created_at', 'desc');

            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (isset($filters['type'])) {
                $query->where('type', $filters['type']);
            }
            if ($fromDate) {
                $query->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('created_at', '<=', $toDate);
            }

            $total = $query->count();
            $feedbackModels = $query->skip($offset)->take($limit)->get();

            // Convert to entities using the same logic as the repository
            $feedbacks = [];
            foreach ($feedbackModels as $model) {
                $feedbacks[] = new \App\Domain\Entities\FeedbackEntity(
                    $model->user_id,
                    $model->title,
                    $model->description,
                    $model->seller_id,
                    $model->type,
                    $model->status,
                    $model->admin_notes,
                    $model->reviewed_by,
                    $model->reviewed_at ? $model->reviewed_at->toDateTimeString() : null,
                    $model->id,
                    $model->created_at ? $model->created_at->toDateTimeString() : null,
                    $model->updated_at ? $model->updated_at->toDateTimeString() : null
                );
            }

            // Obtener todos los user_ids únicos para una sola consulta optimizada
            $userIds = array_unique(array_map(function ($feedback) {
                return $feedback->getUserId();
            }, $feedbacks));

            // Cargar usuarios con sus sellers de una sola vez usando eager loading
            $users = User::with('seller')->whereIn('id', $userIds)->get()->keyBy('id');

            $formattedFeedbacks = array_map(function ($feedback) use ($users) {
                $feedbackData = $feedback->toArray();

                // Obtener user con su relación seller ya cargada
                $user = $users->get($feedback->getUserId());
                if ($user) {
                    $feedbackData['user'] = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];

                    // Si el usuario es seller, agregar información de seller
                    if ($user->seller) {
                        $seller = $user->seller;
                        $feedbackData['seller'] = [
                            'id' => $seller->id,
                            'store_name' => $seller->store_name,
                            'user_id' => $seller->user_id,
                        ];
                        $feedbackData['seller_id'] = $seller->id;

                        // If feedback is approved and seller was featured, include seller featured info
                        if ($feedback->getStatus() === 'approved' && $seller->featured_reason === 'feedback') {
                            $feedbackData['seller_featured'] = [
                                'featured_at' => $seller->featured_at?->toISOString(),
                                'featured_expires_at' => $seller->featured_expires_at?->toISOString(),
                                'featured_days' => $seller->featured_at && $seller->featured_expires_at
                                    ? $seller->featured_at->diffInDays($seller->featured_expires_at)
                                    : 15,
                                'is_active' => $seller->isCurrentlyFeatured(),
                            ];
                        }
                    }
                }

                return $feedbackData;
            }, $feedbacks);

            return response()->json([
                'status' => 'success',
                'data' => $formattedFeedbacks,
                'meta' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'current_page' => floor($offset / $limit) + 1,
                    'total_pages' => ceil($total / $limit),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching feedbacks: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching feedbacks',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Review a feedback (admin only).
     */
    public function review(
        int $id,
        ReviewFeedbackRequest $request,
        ReviewFeedbackUseCase $reviewFeedbackUseCase,
        GenerateDiscountCodeUseCase $generateDiscountCodeUseCase
    ): JsonResponse {
        try {
            $adminId = Auth::user()->admin->id;
            $status = $request->get('status');
            $notes = $request->get('admin_notes');

            if ($status === 'approved') {
                $feedback = $reviewFeedbackUseCase->approve($id, $adminId, $notes);

                // Generate discount code only for regular users (not sellers)
                $discountCode = null;
                $generateDiscount = $request->has('generate_discount') ? $request->get('generate_discount') : true;

                // Only generate discount codes for users, not sellers
                // Sellers get featured store status instead (handled in ReviewFeedbackUseCase)
                if ($generateDiscount && ! $feedback->getSellerId()) {
                    $validityDays = $request->get('validity_days', 30);
                    $discountCode = $generateDiscountCodeUseCase->execute($id, $validityDays);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Feedback approved successfully',
                    'data' => [
                        'feedback' => $feedback->toArray(),
                        'discount_code' => $discountCode ? [
                            'code' => $discountCode->getCode(),
                            'discount_percentage' => $discountCode->getDiscountPercentage(),
                            'expires_at' => $discountCode->getExpiresAt(),
                        ] : null,
                    ],
                ]);
            } else {
                $feedback = $reviewFeedbackUseCase->reject($id, $adminId, $notes);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Feedback rejected',
                    'data' => $feedback->toArray(),
                ]);
            }
        } catch (\InvalidArgumentException $e) {
            Log::error('Validation error reviewing feedback: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error reviewing feedback: '.$e->getMessage()."\n".$e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Error reviewing feedback',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate a discount code for a product.
     */
    public function validateDiscountCode(
        ApplyDiscountRequest $request,
        ApplyDiscountCodeUseCase $applyDiscountCodeUseCase
    ): JsonResponse {
        try {
            $code = $request->input('code');
            $productId = $request->input('product_id');

            $result = $applyDiscountCodeUseCase->validate($code, $productId);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'data' => $result['success'] ? [
                    'discount_percentage' => $result['discount_percentage'],
                    'discount_amount' => $result['discount_amount'],
                    'original_price' => $result['original_price'],
                    'final_price' => $result['final_price'],
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error validating discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error validating discount code',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply a discount code to a product.
     */
    public function applyDiscountCode(
        ApplyDiscountRequest $request,
        ApplyDiscountCodeUseCase $applyDiscountCodeUseCase
    ): JsonResponse {
        try {
            $userId = Auth::id();
            $code = $request->input('code');
            $productId = $request->input('product_id');

            $result = $applyDiscountCodeUseCase->execute($code, $productId, $userId);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'data' => $result['success'] ? [
                    'discount_percentage' => $result['discount_percentage'],
                    'discount_amount' => $result['discount_amount'],
                    'original_price' => $result['original_price'],
                    'final_price' => $result['final_price'],
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error applying discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error applying discount code',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
