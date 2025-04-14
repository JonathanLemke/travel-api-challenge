<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TravelRequestCreationTest extends TestCase
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

    /**
     * 
     * Teste para verificar se um usuário autenticado pode criar um pedido de viagem.
     */
    #[Test]
    public function user_can_create_travel_request(): void
    {
        $data = [
            'destination' => 'São Paulo',
            'departure_date' => now()->addDays(5)->format('Y-m-d'),
            'return_date' => now()->addDays(10)->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->postJson('/api/travel-requests', $data);

        $response->assertStatus(201) 
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'destination',
                    'departure_date',
                    'return_date',
                    'status',
                    'user' => ['id', 'name', 'email'],
                    'created_at',
                    'updated_at',
                    'can_be_canceled'
                ]
            ])
            ->assertJsonFragment([
                'destination' => 'São Paulo',
                'status' => 'requested',
                 'user' => [
                     'id' => $this->regularUser->id,
                     'name' => $this->regularUser->name,
                     'email' => $this->regularUser->email,
                 ]
            ]);

        // Verificar se foi salvo corretamente no banco de dados
        $this->assertDatabaseHas('travel_requests', [
            'user_id' => $this->regularUser->id,
            'destination' => 'São Paulo',
            'status' => 'requested',
            'departure_date' => $data['departure_date'],
            'return_date' => $data['return_date'],
        ]);
    }

    /**
     * 
     * Teste para validar campos obrigatórios e regras na criação.
     */
    #[Test]
    public function travel_request_creation_requires_valid_data(): void
    {
        // Teste 1: Dados incompletos (faltando datas)
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->postJson('/api/travel-requests', ['destination' => 'São Paulo']);

        $response1->assertStatus(422) // HTTP 422 Unprocessable Entity
            ->assertJsonValidationErrors(['departure_date', 'return_date']);

        // Teste 2: Data de retorno antes da data de partida
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->postJson('/api/travel-requests', [
            'destination' => 'Rio de Janeiro',
            'departure_date' => now()->addDays(10)->format('Y-m-d'),
            'return_date' => now()->addDays(5)->format('Y-m-d'), // Data inválida
        ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['return_date']);

        // Teste 3: Data de partida no passado
        $response3 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->postJson('/api/travel-requests', [
            'destination' => 'Belo Horizonte',
            'departure_date' => now()->subDay()->format('Y-m-d'), // Data inválida
            'return_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response3->assertStatus(422)
            ->assertJsonValidationErrors(['departure_date']);

         // Teste 4: Formato de data inválido
        $response4 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken,
        ])->postJson('/api/travel-requests', [
            'destination' => 'Curitiba',
            'departure_date' => '10/05/2025', // Formato dd/mm/yyyy inválido para validação date_format:Y-m-d
            'return_date' => '20/05/2025', // Formato dd/mm/yyyy inválido
        ]);

        $response4->assertStatus(422)
            ->assertJsonValidationErrors(['departure_date', 'return_date']);
    }

    /**
     * 
     * Teste para verificar se usuário não autenticado não pode criar pedido.
     */
    #[Test]
    public function unauthenticated_user_cannot_create_travel_request(): void
    {
        $data = [
            'destination' => 'São Paulo',
            'departure_date' => now()->addDays(5)->format('Y-m-d'),
            'return_date' => now()->addDays(10)->format('Y-m-d'),
        ];

        // Tenta criar sem o header de Authorization
        $response = $this->postJson('/api/travel-requests', $data);

        // Espera um 401 Unauthorized
        $response->assertStatus(401);
    }
}
