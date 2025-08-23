<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SimplifiedFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $admin;

    protected $userToken;

    protected $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Verificar que la migración se ha ejecutado correctamente
        if (! Schema::hasTable('feedback')) {
            $this->markTestSkipped('La tabla feedback no existe. Asegúrate de ejecutar las migraciones.');
        }

        // Crear un usuario normal
        $this->user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
        ]);

        // Crear un admin
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        Admin::factory()->create([
            'user_id' => $this->admin->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        // Generar tokens JWT
        $this->userToken = JWTAuth::fromUser($this->user);
        $this->adminToken = JWTAuth::fromUser($this->admin);
    }

    /**
     * Prueba simplificada del flujo de feedback, sin generar código de descuento
     */
    public function test_simplified_feedback_flow()
    {
        Log::info('Creando un feedback con user_id: '.$this->user->id);

        // 1. Crear un feedback manualmente
        $feedback = new Feedback;
        $feedback->user_id = $this->user->id;
        $feedback->title = 'Test Feedback';
        $feedback->description = 'This is a test feedback';
        $feedback->type = 'improvement';
        $feedback->status = 'pending';
        $feedback->save();

        Log::info('Feedback creado con ID: '.$feedback->id);

        // 2. Mostrar los detalles del feedback antes de revisarlo
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
        ])->getJson("/api/feedback/{$feedback->id}");

        Log::info('Respuesta del get:', ['status' => $response->status(), 'content' => $response->content()]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Intentar aprobar el feedback SIN generar código de descuento
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->json('POST', "/api/admin/feedback/{$feedback->id}/review", [
            'status' => 'approved',
            'generate_discount' => false, // Importante: no generar código de descuento
        ]);

        // Registrar la respuesta completa para diagnóstico
        Log::info('Review response', [
            'status' => $response->status(),
            'content' => $response->content(),
        ]);

        // 4. Verificar si el feedback se actualizó correctamente
        $updatedFeedback = Feedback::find($feedback->id);
        Log::info('Updated feedback', [
            'id' => $updatedFeedback->id ?? 'null',
            'status' => $updatedFeedback->status ?? 'null',
            'reviewed_by' => $updatedFeedback->reviewed_by ?? 'null',
        ]);

        // 5. Verificar el resultado
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}
