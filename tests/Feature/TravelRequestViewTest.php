<?php

namespace Tests\Feature;

use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\Test;

class TravelRequestViewTest extends TestCase
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
    public function user_can_view_only_own_travel_requests_index(): void
    {
        // Criar 3 pedidos para o usuário regular
        TravelRequest::factory(3)->for($this->regularUser)->create();
        // Criar 2 pedidos para o outro usuário
        TravelRequest::factory(2)->for($this->anotherUser)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson('/api/travel-requests');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data'); // Deve ver apenas os 3 seus
    }

    #[Test]
    public function admin_can_view_all_travel_requests_index(): void
    {
        // Criar pedidos para vários usuários
        TravelRequest::factory(3)->for($this->regularUser)->create();
        TravelRequest::factory(2)->for($this->anotherUser)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/travel-requests');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data'); // Admin deve ver todos
    }

    #[Test]
    public function user_can_view_own_travel_request_details(): void
    {
        $travelRequest = TravelRequest::factory()->for($this->regularUser)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson("/api/travel-requests/{$travelRequest->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $travelRequest->id);
    }

    #[Test]
    public function user_cannot_view_another_users_travel_request_details(): void
    {
        $travelRequest = TravelRequest::factory()->for($this->anotherUser)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->getJson("/api/travel-requests/{$travelRequest->id}");

        $response->assertStatus(403); // Forbidden
    }

    #[Test]
    public function admin_can_view_any_travel_request_details(): void
    {
        $travelRequest = TravelRequest::factory()->for($this->regularUser)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/travel-requests/{$travelRequest->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $travelRequest->id);
    }

     #[Test]
    public function viewing_non_existent_travel_request_returns_404(): void
    {
        $nonExistentId = 9999;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken, // Pode ser admin ou user
        ])->getJson("/api/travel-requests/{$nonExistentId}");

        $response->assertStatus(404); // Not Found
    }

     #[Test]
    public function unauthenticated_user_cannot_view_travel_requests_index(): void
    {
        $response = $this->getJson('/api/travel-requests');
        $response->assertStatus(401); // Unauthorized
    }

     #[Test]
    public function unauthenticated_user_cannot_view_travel_request_details(): void
    {
        $travelRequest = TravelRequest::factory()->create(); // Não importa o dono
        $response = $this->getJson("/api/travel-requests/{$travelRequest->id}");
        $response->assertStatus(401); // Unauthorized
    }

}
