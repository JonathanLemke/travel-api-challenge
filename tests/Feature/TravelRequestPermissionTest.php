<?php

namespace Tests\Feature;

use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TravelRequestStatusChanged;

class TravelRequestPermissionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $regularUser;
    protected $anotherUser;
    protected $adminUser;
    protected $userToken;
    protected $anotherUserToken;
    protected $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regularUser = User::factory()->create(['role' => 'user', 'email' => 'regular@example.com']);
        $this->anotherUser = User::factory()->create(['role' => 'user', 'email' => 'other@example.com']);
        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);

        $this->userToken = JWTAuth::fromUser($this->regularUser);
        $this->anotherUserToken = JWTAuth::fromUser($this->anotherUser);
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_any_travel_request_routes(): void
    {
        $travelRequest = TravelRequest::factory()->create(); // Cria um pedido qualquer

        // Testar GET (index)
        $this->getJson('/api/travel-requests')->assertStatus(401);
        // Testar POST (store)
        $this->postJson('/api/travel-requests', [])->assertStatus(401);
        // Testar GET (show)
        $this->getJson("/api/travel-requests/{$travelRequest->id}")->assertStatus(401);
        // Testar PATCH (updateStatus)
        $this->patchJson("/api/travel-requests/{$travelRequest->id}/status", [])->assertStatus(401);
    }

    #[Test]
    public function regular_user_cannot_view_details_of_another_users_request(): void
    {
        // Cria pedido para o "outro" usuário
        $otherRequest = TravelRequest::factory()->for($this->anotherUser)->create();

        // Usuário regular tenta acessar
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                         ->getJson("/api/travel-requests/{$otherRequest->id}");

        $response->assertStatus(403); // Forbidden
    }

     #[Test]
    public function regular_user_cannot_update_status_of_own_request(): void
    {
        // Cria pedido para o usuário regular
        $ownRequest = TravelRequest::factory()->for($this->regularUser)->requested()->create();

        // Usuário regular tenta atualizar o status
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                         ->patchJson("/api/travel-requests/{$ownRequest->id}/status", ['status' => 'approved']);

        // Espera 403 baseado na nossa lógica de controller (só admin pode mudar status)
        $response->assertStatus(403);

    }


    #[Test]
    public function regular_user_cannot_update_status_of_another_users_request(): void
    {
        // Cria pedido para o "outro" usuário
        $otherRequest = TravelRequest::factory()->for($this->anotherUser)->requested()->create();

        // Usuário regular tenta atualizar
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                         ->patchJson("/api/travel-requests/{$otherRequest->id}/status", ['status' => 'approved']);

        $response->assertStatus(403); // Forbidden

    }


    #[Test]
    public function admin_can_view_details_of_any_request(): void
    {
        // Cria pedido para usuário regular
         $userRequest = TravelRequest::factory()->for($this->regularUser)->create();

         // Admin tenta acessar
         $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
                          ->getJson("/api/travel-requests/{$userRequest->id}");

         $response->assertStatus(200)
                  ->assertJsonPath('data.id', $userRequest->id);
    }

     #[Test]
    public function admin_can_update_status_of_any_users_request(): void
    {
         Notification::fake();
         // Cria pedido para usuário regular
         $userRequest = TravelRequest::factory()->for($this->regularUser)->requested()->create();

         // Admin tenta aprovar
         $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
                          ->patchJson("/api/travel-requests/{$userRequest->id}/status", ['status' => 'approved']);

         $response->assertStatus(200)
                  ->assertJsonPath('data.status', 'approved');

         $this->assertDatabaseHas('travel_requests', [
             'id' => $userRequest->id,
             'status' => 'approved',
         ]);

         Notification::assertSentTo($this->regularUser, TravelRequestStatusChanged::class);
    }

    
}
