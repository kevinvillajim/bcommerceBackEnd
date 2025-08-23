<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SellerApplicationController extends Controller
{
    /**
     * Store a new seller application (for users)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'store_name' => 'required|string|min:3|max:100',
                'business_activity' => 'required|string|max:500',
                'products_to_sell' => 'required|string|max:1000',
                'ruc' => 'required|string|max:20|unique:seller_applications,ruc',
                'contact_email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'physical_address' => 'required|string|max:500',
                'business_description' => 'nullable|string|max:1000',
                'experience' => 'nullable|string|max:1000',
                'additional_info' => 'nullable|string|max:1000',
            ]);

            $user = Auth::user();

            // Check if user already has a pending or approved application
            $existingApplication = SellerApplication::where('user_id', $user->id)
                ->whereIn('status', [SellerApplication::STATUS_PENDING, SellerApplication::STATUS_APPROVED])
                ->first();

            if ($existingApplication) {
                $statusText = $existingApplication->status === SellerApplication::STATUS_PENDING ? 'en proceso' : 'aprobada';

                return response()->json([
                    'status' => 'error',
                    'message' => "Ya tienes una solicitud {$statusText}",
                ], 400);
            }

            // Check if user is already a seller
            $existingSeller = Seller::where('user_id', $user->id)->first();
            if ($existingSeller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ya eres un vendedor registrado',
                ], 400);
            }

            // Create the application
            $application = SellerApplication::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'business_activity' => $request->business_activity,
                'products_to_sell' => $request->products_to_sell,
                'ruc' => $request->ruc,
                'contact_email' => $request->contact_email,
                'phone' => $request->phone,
                'physical_address' => $request->physical_address,
                'business_description' => $request->business_description,
                'experience' => $request->experience,
                'additional_info' => $request->additional_info,
                'status' => SellerApplication::STATUS_PENDING,
            ]);

            Log::info('New seller application submitted', [
                'user_id' => $user->id,
                'application_id' => $application->id,
                'store_name' => $request->store_name,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Solicitud enviada exitosamente. Te contactaremos pronto.',
                'data' => $application,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating seller application: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al enviar la solicitud',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current user's application status
     */
    public function getMyApplication()
    {
        try {
            $user = Auth::user();
            $application = SellerApplication::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $application) {
                return response()->json([
                    'status' => 'success',
                    'data' => null,
                    'message' => 'No tienes solicitudes de vendedor',
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $application,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user application: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la solicitud',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all applications for admin
     */
    public function index(Request $request)
    {
        try {
            $query = SellerApplication::with(['user', 'reviewer']);

            // Filter by status
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            // Search by store name or user name
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('store_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDir = $request->input('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Pagination
            $perPage = $request->input('per_page', 15);
            $applications = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $applications,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching seller applications: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las solicitudes',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show specific application details for admin
     */
    public function show($id)
    {
        try {
            $application = SellerApplication::with(['user', 'reviewer'])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $application,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Solicitud no encontrada',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching seller application: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la solicitud',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve seller application and create seller account
     */
    public function approve(Request $request, $id)
    {
        try {
            $request->validate([
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $application = SellerApplication::findOrFail($id);

            if ($application->status !== SellerApplication::STATUS_PENDING) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Esta solicitud ya ha sido procesada',
                ], 400);
            }

            // Check if user is already a seller
            $existingSeller = Seller::where('user_id', $application->user_id)->first();
            if ($existingSeller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El usuario ya es un vendedor registrado',
                ], 400);
            }

            // Update application status
            $application->update([
                'status' => SellerApplication::STATUS_APPROVED,
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);

            // Create seller account
            $seller = Seller::create([
                'user_id' => $application->user_id,
                'store_name' => $application->store_name,
                'description' => $application->business_description ?? 'Tienda aprobada a través de solicitud',
                'status' => 'active',
                'verification_level' => 'none',
                'commission_rate' => 10.00, // Default commission rate
                'total_sales' => 0,
                'is_featured' => false,
            ]);

            // Create notification for user
            Notification::create([
                'user_id' => $application->user_id,
                'type' => Notification::TYPE_SELLER_APPLICATION_APPROVED,
                'title' => '¡Solicitud de Vendedor Aprobada!',
                'message' => "¡Felicitaciones! Tu solicitud para crear la tienda '{$application->store_name}' ha sido aprobada. Ya puedes comenzar a vender en Comersia.",
                'data' => [
                    'application_id' => $application->id,
                    'store_name' => $application->store_name,
                    'seller_id' => $seller->id,
                    'approved_at' => now()->toISOString(),
                ],
                'read' => false,
            ]);

            DB::commit();

            Log::info('Seller application approved and seller created', [
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'seller_id' => $seller->id,
                'store_name' => $application->store_name,
                'approved_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Solicitud aprobada y cuenta de vendedor creada exitosamente',
                'data' => [
                    'application' => $application->fresh(['user', 'reviewer']),
                    'seller' => $seller,
                ],
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Solicitud no encontrada',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving seller application: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar la solicitud',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject seller application with reason
     */
    public function reject(Request $request, $id)
    {
        try {
            $request->validate([
                'rejection_reason' => 'required|string|min:10|max:1000',
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            $application = SellerApplication::findOrFail($id);

            if ($application->status !== SellerApplication::STATUS_PENDING) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Esta solicitud ya ha sido procesada',
                ], 400);
            }

            $application->update([
                'status' => SellerApplication::STATUS_REJECTED,
                'rejection_reason' => $request->rejection_reason,
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);

            // Create notification for user
            Notification::create([
                'user_id' => $application->user_id,
                'type' => Notification::TYPE_SELLER_APPLICATION_REJECTED,
                'title' => 'Solicitud de Vendedor Rechazada',
                'message' => "Tu solicitud para crear la tienda '{$application->store_name}' ha sido rechazada. Revisa los motivos y puedes enviar una nueva solicitud si lo deseas.",
                'data' => [
                    'application_id' => $application->id,
                    'store_name' => $application->store_name,
                    'rejection_reason' => $request->rejection_reason,
                    'rejected_at' => now()->toISOString(),
                ],
                'read' => false,
            ]);

            Log::info('Seller application rejected', [
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'store_name' => $application->store_name,
                'rejected_by' => Auth::id(),
                'reason' => $request->rejection_reason,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Solicitud rechazada exitosamente',
                'data' => $application->fresh(['user', 'reviewer']),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Solicitud no encontrada',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error rejecting seller application: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al rechazar la solicitud',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get application statistics for admin dashboard
     */
    public function getStats()
    {
        try {
            $stats = [
                'total' => SellerApplication::count(),
                'pending' => SellerApplication::pending()->count(),
                'approved' => SellerApplication::approved()->count(),
                'rejected' => SellerApplication::rejected()->count(),
                'recent' => SellerApplication::where('created_at', '>=', now()->subDays(7))->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching application stats: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estadísticas',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
