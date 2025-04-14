<?php

namespace Tests\Feature;

use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\Test;

class TravelRequestFilterTest extends TestCase
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
    public function can_filter_travel_requests_by_status(): void
    {
        // Criar pedidos com diferentes status para o usuário regular
        TravelRequest::factory()->for($this->regularUser)->requested()->count(3)->create();
        TravelRequest::factory()->for($this->regularUser)->approved()->count(2)->create();
        TravelRequest::factory()->for($this->regularUser)->canceled()->count(1)->create();
        // Criar um pedido para outro usuário (não deve aparecer nos filtros do user regular)
        TravelRequest::factory()->for($this->anotherUser)->requested()->create();

        // Filtrar por 'approved'
        $responseApproved = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                               ->getJson('/api/travel-requests?status=approved');
        $responseApproved->assertStatus(200)->assertJsonCount(2, 'data');

        // Filtrar por 'requested'
        $responseRequested = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                                ->getJson('/api/travel-requests?status=requested');
        $responseRequested->assertStatus(200)->assertJsonCount(3, 'data');

        // Filtrar por 'canceled'
        $responseCanceled = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                              ->getJson('/api/travel-requests?status=canceled');
        $responseCanceled->assertStatus(200)->assertJsonCount(1, 'data');

         // Testar com status inválido (não deve retornar erro, mas sim lista completa ou vazia dependendo da lógica do controller)
         // Nossa lógica atual no controller ignora o filtro se o valor for inválido (não passa na validação do Request)
         $responseInvalid = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                               ->getJson('/api/travel-requests?status=invalid');
                               $responseInvalid->assertStatus(422) // Espera erro de validação
                               ->assertJsonValidationErrors(['status']);

        // Admin filtrando (deve considerar todos os usuários)
         TravelRequest::factory()->for($this->adminUser)->approved()->create(); // Pedido do admin
         $responseAdmin = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
                               ->getJson('/api/travel-requests?status=approved');
         // 2 do user regular + 1 do admin = 3
         $responseAdmin->assertStatus(200)->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_filter_travel_requests_by_date_range(): void
    {
        // Criar pedidos com datas específicas para o usuário regular
        $reqJan = TravelRequest::factory()->for($this->regularUser)->create([
            'departure_date' => '2024-01-10', 'return_date' => '2024-01-20',
        ]);
        $reqLateJan = TravelRequest::factory()->for($this->regularUser)->create([
            'departure_date' => '2024-01-25', 'return_date' => '2024-02-05', // Cruza o mês
        ]);
        $reqFeb = TravelRequest::factory()->for($this->regularUser)->create([
            'departure_date' => '2024-02-10', 'return_date' => '2024-02-20',
        ]);
        // Pedido para outro usuário (não deve ser incluído no filtro do user regular)
        TravelRequest::factory()->for($this->anotherUser)->create([
             'departure_date' => '2024-01-15', 'return_date' => '2024-01-25',
        ]);

        // Filtrar por Janeiro
        $responseJan = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                          ->getJson('/api/travel-requests?start_date=2024-01-01&end_date=2024-01-31');
        // Deve encontrar o $reqJan (começa e termina em Jan) e $reqLateJan (termina depois, mas começa em Jan)
        $responseJan->assertStatus(200)->assertJsonCount(2, 'data');
        $responseJan->assertJsonFragment(['id' => $reqJan->id]);
        $responseJan->assertJsonFragment(['id' => $reqLateJan->id]);


        // Filtrar por Fevereiro
         $responseFeb = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                           ->getJson('/api/travel-requests?start_date=2024-02-01&end_date=2024-02-29');
         // Deve encontrar o $reqLateJan (começa antes, mas termina em Feb) e $reqFeb (começa e termina em Feb)
         $responseFeb->assertStatus(200)->assertJsonCount(2, 'data');
         $responseFeb->assertJsonFragment(['id' => $reqLateJan->id]);
         $responseFeb->assertJsonFragment(['id' => $reqFeb->id]);


        // Filtrar por período que engloba apenas o primeiro pedido
        $responseOnlyFirst = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                                ->getJson('/api/travel-requests?start_date=2024-01-05&end_date=2024-01-22');
        $responseOnlyFirst->assertStatus(200)->assertJsonCount(1, 'data');
        $responseOnlyFirst->assertJsonFragment(['id' => $reqJan->id]);

         // Testar filtro inválido (end_date antes de start_date) - Request deve invalidar
         $responseInvalidDate = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                                    ->getJson('/api/travel-requests?start_date=2024-02-01&end_date=2024-01-15');
         // A validação 'after_or_equal' no Filter Request deve falhar
         // Como não temos failedValidation nesse request, ele simplesmente não aplicará o filtro de data
         // ou retornará todos os dados se não houver outros filtros. Vamos assumir que retorna todos do user.
         $responseInvalidDate->assertStatus(422)
                    ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function can_filter_travel_requests_by_destination(): void
    {
        // Criar pedidos com destinos específicos
        $reqSP = TravelRequest::factory()->for($this->regularUser)->create(['destination' => 'São Paulo']);
        $reqRJ = TravelRequest::factory()->for($this->regularUser)->create(['destination' => 'Rio de Janeiro']);
        $reqSL = TravelRequest::factory()->for($this->regularUser)->create(['destination' => 'São Luís']);
        TravelRequest::factory()->for($this->anotherUser)->create(['destination' => 'São Vicente']);

        // Filtrar por 'São' (parcial)
        $responseSao = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                          ->getJson('/api/travel-requests?destination=São');
        // Deve encontrar SP e SL
        $responseSao->assertStatus(200)->assertJsonCount(2, 'data');
        $responseSao->assertJsonFragment(['id' => $reqSP->id]);
        $responseSao->assertJsonFragment(['id' => $reqSL->id]);
        $responseSao->assertJsonMissing(['id' => $reqRJ->id]); // Garante que RJ não está

        // Filtrar por 'Rio' (parcial)
        $responseRio = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                          ->getJson('/api/travel-requests?destination=Rio');
        $responseRio->assertStatus(200)->assertJsonCount(1, 'data');
        $responseRio->assertJsonFragment(['id' => $reqRJ->id]);

        // Filtrar por destino completo exato
        $responseExact = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                            ->getJson('/api/travel-requests?destination=São Paulo');
        $responseExact->assertStatus(200)->assertJsonCount(1, 'data');
        $responseExact->assertJsonFragment(['id' => $reqSP->id]);

         // Filtrar por destino inexistente
        $responseNone = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                            ->getJson('/api/travel-requests?destination=Curitiba');
        $responseNone->assertStatus(200)->assertJsonCount(0, 'data');
    }

    #[Test]
    public function can_use_combined_filters(): void
    {
        // Setup mais complexo
        $req1 = TravelRequest::factory()->for($this->regularUser)->approved()->create([
            'destination' => 'Recife', 'departure_date' => '2024-03-10', 'return_date' => '2024-03-15',
        ]);
        $req2 = TravelRequest::factory()->for($this->regularUser)->requested()->create([
            'destination' => 'Salvador', 'departure_date' => '2024-03-20', 'return_date' => '2024-03-25',
        ]);
        $req3 = TravelRequest::factory()->for($this->regularUser)->approved()->create([
            'destination' => 'Salvador', 'departure_date' => '2024-04-01', 'return_date' => '2024-04-05',
        ]);
         $req4 = TravelRequest::factory()->for($this->anotherUser)->approved()->create([
            'destination' => 'Recife', 'departure_date' => '2024-03-12', 'return_date' => '2024-03-18',
        ]);


        // Filtro 1: status=approved E destination=Recife (User regular)
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                         ->getJson('/api/travel-requests?status=approved&destination=Recife');
        $response1->assertStatus(200)->assertJsonCount(1, 'data');
        $response1->assertJsonFragment(['id' => $req1->id]);

        // Filtro 2: status=approved E periodo=Março (User regular)
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                         ->getJson('/api/travel-requests?status=approved&start_date=2024-03-01&end_date=2024-03-31');
        // Apenas $req1 deve corresponder
        $response2->assertStatus(200)->assertJsonCount(1, 'data');
         $response2->assertJsonFragment(['id' => $req1->id]);

         // Filtro 3: destination=Salvador E periodo=Março (User regular)
         $response3 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
                          ->getJson('/api/travel-requests?destination=Salvador&start_date=2024-03-01&end_date=2024-03-31');
         // Apenas $req2 deve corresponder
         $response3->assertStatus(200)->assertJsonCount(1, 'data');
         $response3->assertJsonFragment(['id' => $req2->id]);

         // Filtro 4: status=approved (Admin)
          $response4 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
                            ->getJson('/api/travel-requests?status=approved');
          // Deve encontrar req1, req3 (do user regular) e req4 (do another user)
          $response4->assertStatus(200)->assertJsonCount(3, 'data');
    }
}
