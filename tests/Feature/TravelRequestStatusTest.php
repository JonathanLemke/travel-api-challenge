<?php

namespace Tests\Feature;

use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\TravelRequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\Test;

class TravelRequestStatusTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $regularUser;
    protected $adminUser;
    protected $userToken;
    protected $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regularUser = User::factory()->create(['role' => 'user', 'email' => 'regular@example.com']);
        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);

        $this->userToken = JWTAuth::fromUser($this->regularUser);
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
    }

    #[Test]
    public function admin_can_approve_a_requested_travel_request(): void
    {
        Notification::fake(); // Impede o envio real de notificações
        $travelRequest = TravelRequest::factory()->for($this->regularUser)->requested()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patchJson("/api/travel-requests/{$travelRequest->id}/status", [
            'status' => 'approved'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.id', $travelRequest->id)
            ->assertJsonPath('data.approved_at', fn ($timestamp) => $timestamp !== null); // Verifica se approved_at foi setado

        $this->assertDatabaseHas('travel_requests', [
            'id' => $travelRequest->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseMissing('travel_requests', [ // Garante que não ficou como requested
            'id' => $travelRequest->id,
            'status' => 'requested',
        ]);

        // Verifica se a notificação foi enviada para o usuário correto
        Notification::assertSentTo(
            $this->regularUser,
            TravelRequestStatusChanged::class,
            function ($notification, $channels) use ($travelRequest) {
                return $notification->travelRequest->id === $travelRequest->id &&
                       $notification->travelRequest->status === 'approved';
            }
        );
    }

    #[Test]
    public function admin_can_cancel_a_requested_travel_request(): void
    {
        Notification::fake();
        $travelRequest = TravelRequest::factory()->for($this->regularUser)->requested()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patchJson("/api/travel-requests/{$travelRequest->id}/status", [
            'status' => 'canceled'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.id', $travelRequest->id)
            ->assertJsonPath('data.canceled_at', fn ($timestamp) => $timestamp !== null);

        $this->assertDatabaseHas('travel_requests', [
            'id' => $travelRequest->id,
            'status' => 'canceled',
        ]);

        Notification::assertSentTo(
            $this->regularUser,
            TravelRequestStatusChanged::class,
            function ($notification, $channels) use ($travelRequest) {
                return $notification->travelRequest->id === $travelRequest->id &&
                       $notification->travelRequest->status === 'canceled';
            }
        );
    }

    #[Test]
    public function admin_can_cancel_an_approved_request_with_distant_departure(): void
    {
        Notification::fake();
        // Cria um pedido aprovado com data de partida daqui a 10 dias
        $travelRequest = TravelRequest::factory()
            ->for($this->regularUser)
            ->approved()
            ->create(['departure_date' => now()->addDays(10)->format('Y-m-d')]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patchJson("/api/travel-requests/{$travelRequest->id}/status", [
            'status' => 'canceled'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.id', $travelRequest->id)
            ->assertJsonPath('data.canceled_at', fn ($timestamp) => $timestamp !== null);

        $this->assertDatabaseHas('travel_requests', [
            'id' => $travelRequest->id,
            'status' => 'canceled',
        ]);

        Notification::assertSentTo($this->regularUser, TravelRequestStatusChanged::class);
    }

    #[Test]
    public function admin_cannot_cancel_an_approved_request_close_to_departure(): void
    {
        Notification::fake();
        // Cria um pedido aprovado com data de partida para AMANHÃ (menos de 2 dias)
        $travelRequest = TravelRequest::factory()
            ->for($this->regularUser)
            ->approved()
            ->create(['departure_date' => now()->addDay()->format('Y-m-d')]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patchJson("/api/travel-requests/{$travelRequest->id}/status", [
            'status' => 'canceled'
        ]);

        $response->assertStatus(422) // Falha na regra de negócio
            ->assertJsonFragment([ // Verifica a mensagem de erro específica
                'message' => 'This request cannot be canceled. The departure date is too close.'
            ]);

        // Verifica que o status NÃO mudou no banco
        $this->assertDatabaseHas('travel_requests', [
            'id' => $travelRequest->id,
            'status' => 'approved',
        ]);

        // Verifica que NENHUMA notificação foi enviada neste caso
        Notification::assertNothingSent();
    }

    #[Test]
    public function status_update_requires_valid_status_value(): void
    {
        $travelRequest = TravelRequest::factory()->requested()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patchJson("/api/travel-requests/{$travelRequest->id}/status", [
            'status' => 'pending' // Valor inválido (não é 'approved' nem 'canceled')
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']); // Erro de validação no campo status
    }

    #[Test]
    public function updating_status_to_the_same_status_does_nothing(): void
    {
         Notification::fake();
         $travelRequest = TravelRequest::factory()->for($this->regularUser)->approved()->create();
         $originalUpdatedAt = $travelRequest->updated_at;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patchJson("/api/travel-requests/{$travelRequest->id}/status", [
            'status' => 'approved' // Tentando atualizar para o mesmo status
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved'); // Continua approved

        $this->assertDatabaseHas('travel_requests', [
            'id' => $travelRequest->id,
            'status' => 'approved',
            'updated_at' => $originalUpdatedAt // Verifica se updated_at não mudou
        ]);
         Notification::assertNothingSent(); // Nenhuma notificação deve ser enviada
    }

     #[Test]
    public function status_update_for_non_existent_request_returns_404(): void
    {
        $nonExistentId = 9999;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patchJson("/api/travel-requests/{$nonExistentId}/status", [
            'status' => 'approved'
        ]);

        $response->assertStatus(404); // Not Found
    }
}
