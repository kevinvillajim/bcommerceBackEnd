<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Feedback;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class FeedbackSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $seller;

    protected $admin;

    protected $product;

    protected $userToken;

    protected $sellerToken;

    protected $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuarios de prueba
        $this->user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
        ]);

        $this->seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);

        // Crear perfil de vendedor
        Seller::factory()->create([
            'user_id' => $this->seller->id,
            'store_name' => 'Test Store',
            'status' => 'active',
        ]);

        // Crear admin
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        Admin::factory()->create([
            'user_id' => $this->admin->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        // Crear producto para pruebas
        $this->product = Product::factory()->create([
            'price' => 100.00,
            'user_id' => $this->seller->id,
        ]);

        // Generar tokens JWT
        $this->userToken = JWTAuth::fromUser($this->user);
        $this->sellerToken = JWTAuth::fromUser($this->seller);
        $this->adminToken = JWTAuth::fromUser($this->admin);
    }

    public function test_complete_user_feedback_and_discount_flow()
    {
        // 1. Usuario normal envía feedback
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->userToken,
            'Accept' => 'application/json',
        ])->postJson('/api/feedback', [
            'title' => 'Suggestion for improvement',
            'description' => 'I think you should add a dark mode to the application. It would make it easier to use at night.',
            'type' => 'improvement',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        // Obtener el ID del feedback
        $feedbackId = $response->json('data.id');

        // Verificar que el feedback se haya creado
        $this->assertDatabaseHas('feedback', [
            'id' => $feedbackId,
            'user_id' => $this->user->id,
            'title' => 'Suggestion for improvement',
            'status' => 'pending',
        ]);

        // 2. Admin revisa y aprueba el feedback
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->postJson("/api/admin/feedback/{$feedbackId}/review", [
            'status' => 'approved',
            'admin_notes' => 'Great idea! We will implement this soon.',
            'generate_discount' => true,
            'validity_days' => 30,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.feedback.status', 'approved');

        // Verificar que se ha generado un código de descuento
        $this->assertNotNull($response->json('data.discount_code.code'));
        $discountCode = $response->json('data.discount_code.code');

        // 3. Usuario intenta usar el código de descuento en un producto
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->userToken,
            'Accept' => 'application/json',
        ])->postJson('/api/discounts/validate', [
            'code' => $discountCode,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.discount_percentage', 5)
            ->assertJsonPath('data.original_price', 100)
            ->assertJsonPath('data.final_price', 95);

        // 4. Usuario aplica el código de descuento
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->userToken,
            'Accept' => 'application/json',
        ])->postJson('/api/discounts/apply', [
            'code' => $discountCode,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.final_price', 95);

        // 5. Verificar que el código ya está usado y no puede usarse nuevamente
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->userToken,
            'Accept' => 'application/json',
        ])->postJson('/api/discounts/validate', [
            'code' => $discountCode,
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'This discount code has already been used');
    }

    public function test_seller_feedback_flow()
    {
        // 1. Vendedor envía feedback
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->postJson('/api/feedback', [
            'title' => 'Bug in seller dashboard',
            'description' => 'There is an issue with the sales statistics chart in the seller dashboard. It does not show correct data for the last month.',
            'type' => 'bug',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        // Obtener el ID del feedback
        $feedbackId = $response->json('data.id');

        // Verificar que el feedback se ha creado correctamente con seller_id
        $this->assertDatabaseHas('feedback', [
            'id' => $feedbackId,
            'user_id' => $this->seller->id,
            'title' => 'Bug in seller dashboard',
            'status' => 'pending',
        ]);

        // También verificar que seller_id no es null (no podemos usar assertDatabaseHas con whereNotNull)
        $feedback = Feedback::find($feedbackId);
        $this->assertNotNull($feedback->seller_id);

        // 2. Admin rechaza este feedback
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->postJson("/api/admin/feedback/{$feedbackId}/review", [
            'status' => 'rejected',
            'admin_notes' => 'We could not reproduce this issue. Please provide more details.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'rejected');

        // Verificar que no se genera código de descuento para feedback rechazado
        $this->assertEquals(0, \App\Models\DiscountCode::where('feedback_id', $feedbackId)->count());
    }

    public function test_admin_can_view_pending_feedbacks()
    {
        // Crear algunos feedbacks pendientes manualmente
        for ($i = 0; $i < 5; $i++) {
            $feedback = new Feedback;
            $feedback->user_id = $this->user->id;
            $feedback->title = "Pending Feedback $i";
            $feedback->description = "This is a pending feedback $i";
            $feedback->type = 'improvement';
            $feedback->status = 'pending';
            $feedback->save();
        }

        // Admin obtiene la lista de feedbacks pendientes
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/feedback/pending');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 5);
    }

    public function test_user_cannot_review_feedback()
    {
        // Crear un feedback manualmente
        $feedback = new Feedback;
        $feedback->user_id = $this->user->id;
        $feedback->title = 'Test Feedback';
        $feedback->description = 'This is a test feedback';
        $feedback->type = 'improvement';
        $feedback->status = 'pending';
        $feedback->save();

        // Usuario normal intenta revisar el feedback (debe fallar)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->userToken,
            'Accept' => 'application/json',
        ])->postJson("/api/admin/feedback/{$feedback->id}/review", [
            'status' => 'approved',
        ]);

        $response->assertStatus(403); // Forbidden
    }
}
