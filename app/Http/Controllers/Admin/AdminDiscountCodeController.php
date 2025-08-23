<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Repositories\AdminDiscountCodeRepositoryInterface;
use App\Http\Controllers\Controller;
use App\UseCases\AdminDiscountCode\ApplyAdminDiscountCodeUseCase;
use App\UseCases\AdminDiscountCode\CreateAdminDiscountCodeUseCase;
use App\UseCases\AdminDiscountCode\UpdateAdminDiscountCodeUseCase;
use App\UseCases\AdminDiscountCode\ValidateAdminDiscountCodeUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminDiscountCodeController extends Controller
{
    private AdminDiscountCodeRepositoryInterface $repository;

    public function __construct(AdminDiscountCodeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of discount codes with filters and pagination.
     */
    public function index(Request $request): JsonResponse
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

            // Build filters
            $filters = [];

            if ($request->has('validity')) {
                $filters['validity'] = $request->input('validity');
            }

            if ($request->has('usage')) {
                $filters['is_used'] = $request->input('usage');
            }

            if ($request->has('percentage')) {
                $filters['percentage_range'] = $request->input('percentage');
            }

            if ($request->has('code')) {
                $filters['code'] = $request->input('code');
            }

            if ($request->has('from_date')) {
                $filters['from_date'] = $request->input('from_date');
            }

            if ($request->has('to_date')) {
                $filters['to_date'] = $request->input('to_date');
            }

            // Get discount codes
            $discountCodes = $this->repository->findAll($filters, $limit, $offset);
            $total = $this->repository->count($filters);

            // Format response with user and product information
            $formattedCodes = [];
            foreach ($discountCodes as $code) {
                $codeArray = $code->toArray();

                // Add user information if used
                if ($code->getUsedBy()) {
                    $user = \App\Models\User::find($code->getUsedBy());
                    if ($user) {
                        $codeArray['used_by_user'] = [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ];
                    }
                }

                // Add product information if used on product
                if ($code->getUsedOnProductId()) {
                    $product = \App\Models\Product::find($code->getUsedOnProductId());
                    if ($product) {
                        $codeArray['used_on_product'] = [
                            'id' => $product->id,
                            'name' => $product->name,
                            'price' => $product->price,
                        ];
                    }
                }

                // Add creator information
                $creator = \App\Models\User::find($code->getCreatedBy());
                if ($creator) {
                    $codeArray['created_by_user'] = [
                        'id' => $creator->id,
                        'name' => $creator->name,
                    ];
                }

                $formattedCodes[] = $codeArray;
            }

            return response()->json([
                'status' => 'success',
                'data' => $formattedCodes,
                'meta' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'current_page' => floor($offset / $limit) + 1,
                    'total_pages' => ceil($total / $limit),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching admin discount codes: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching discount codes',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the specified discount code.
     */
    public function show(int $id): JsonResponse
    {
        try {
            if (! Auth::user()->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 403);
            }

            $discountCode = $this->repository->findById($id);

            if (! $discountCode) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Discount code not found',
                ], 404);
            }

            $codeArray = $discountCode->toArray();

            // Add related information
            if ($discountCode->getUsedBy()) {
                $user = \App\Models\User::find($discountCode->getUsedBy());
                if ($user) {
                    $codeArray['used_by_user'] = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                }
            }

            if ($discountCode->getUsedOnProductId()) {
                $product = \App\Models\Product::find($discountCode->getUsedOnProductId());
                if ($product) {
                    $codeArray['used_on_product'] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                    ];
                }
            }

            $creator = \App\Models\User::find($discountCode->getCreatedBy());
            if ($creator) {
                $codeArray['created_by_user'] = [
                    'id' => $creator->id,
                    'name' => $creator->name,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $codeArray,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching discount code',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created discount code.
     */
    public function store(Request $request, CreateAdminDiscountCodeUseCase $createUseCase): JsonResponse
    {
        try {
            if (! Auth::user()->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:50',
                'discount_percentage' => 'required|integer|min:5|max:50',
                'expires_at' => 'required|date|after:now',
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $request->all();
            $data['created_by'] = Auth::id();

            $result = $createUseCase->execute($data);

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data'],
                ], 201);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error creating discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error creating discount code',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified discount code.
     */
    public function update(int $id, Request $request, UpdateAdminDiscountCodeUseCase $updateUseCase): JsonResponse
    {
        try {
            if (! Auth::user()->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|string|max:50',
                'discount_percentage' => 'sometimes|integer|min:5|max:50',
                'expires_at' => 'sometimes|date|after:now',
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $updateUseCase->execute($id, $request->all());

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data'],
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error updating discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error updating discount code',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified discount code.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            if (! Auth::user()->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 403);
            }

            $discountCode = $this->repository->findById($id);

            if (! $discountCode) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Discount code not found',
                ], 404);
            }

            $success = $this->repository->delete($id);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Discount code deleted successfully',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to delete discount code',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error deleting discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting discount code',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate a discount code (public endpoint for users).
     */
    public function validate(Request $request, ValidateAdminDiscountCodeUseCase $validateUseCase): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string',
                'product_id' => 'nullable|integer|exists:products,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $validateUseCase->execute(
                $request->input('code'),
                $request->input('product_id')
            );

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'valid' => $result['valid'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'valid' => false,
                'message' => 'Error validating discount code',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply a discount code (for checkout process).
     */
    public function apply(Request $request, ApplyAdminDiscountCodeUseCase $applyUseCase): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string',
                'product_id' => 'required|integer|exists:products,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $applyUseCase->execute(
                $request->input('code'),
                Auth::id(),
                $request->input('product_id')
            );

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data'],
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error applying discount code: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error applying discount code',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a random discount code.
     */
    public function generateCode(): JsonResponse
    {
        try {
            $code = CreateAdminDiscountCodeUseCase::generateRandomCode();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'code' => $code,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating code',
            ], 500);
        }
    }

    /**
     * Get discount code statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            if (! Auth::user()->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 403);
            }

            $total = $this->repository->count();
            $valid = $this->repository->count(['validity' => 'valid']);
            $expired = $this->repository->count(['validity' => 'expired']);
            $used = $this->repository->count(['is_used' => 'used']);
            $unused = $this->repository->count(['is_used' => 'unused']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total' => $total,
                    'valid' => $valid,
                    'expired' => $expired,
                    'used' => $used,
                    'unused' => $unused,
                    'active' => $valid - $used, // Valid and unused
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching discount code stats: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching statistics',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
