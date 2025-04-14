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

class TravelRequestNotificationTest extends TestCase
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

        // Habilita o fake do Notification para todos os testes neste arquivo
        Notification::fake();
    }

    #[Test]
    public function notification_is_sent_to_requester_when_request_is_approved(): void
    {
        $travelRequest = TravelRequest::factory()
            ->for($this->regularUser) // Pertence ao usuário regular
            ->requested()             // Começa como 'requested'
            ->create();

        // Admin aprova o pedido
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
             ->patchJson("/api/travel-requests/{$travelRequest->id}/status", ['status' => 'approved']);

        // Verifica se a notificação foi enviada para o usuário correto
        Notification::assertSentTo(
            $this->regularUser, // O notifiable esperado
            TravelRequestStatusChanged::class, // A classe da notificação
            // Callback opcional para verificar o conteúdo da notificação
            function (TravelRequestStatusChanged $notification, array $channels) use ($travelRequest) {
                // Verifica se a notificação contém o ID correto e o status 'approved'
                return $notification->travelRequest->id === $travelRequest->id &&
                       $notification->travelRequest->status === 'approved' &&
                       in_array('mail', $channels); // Verifica se o canal 'mail' foi usado
            }
        );

        // Verifica se NÃO foi enviada para outros usuários
        Notification::assertNotSentTo($this->anotherUser, TravelRequestStatusChanged::class);
        Notification::assertNotSentTo($this->adminUser, TravelRequestStatusChanged::class);
    }

     #[Test]
    public function notification_is_sent_to_requester_when_request_is_canceled(): void
    {
        $travelRequest = TravelRequest::factory()
            ->for($this->regularUser)
             // Pode começar como requested ou approved, vamos testar com requested
            ->requested()
            ->create();

        // Admin cancela o pedido
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
             ->patchJson("/api/travel-requests/{$travelRequest->id}/status", ['status' => 'canceled']);

        // Verifica se a notificação foi enviada para o usuário correto
        Notification::assertSentTo(
            $this->regularUser,
            TravelRequestStatusChanged::class,
            function (TravelRequestStatusChanged $notification) use ($travelRequest) {
                // Verifica o status 'canceled'
                return $notification->travelRequest->id === $travelRequest->id &&
                       $notification->travelRequest->status === 'canceled';
            }
        );
         // Verifica se NÃO foi enviada para outros usuários
        Notification::assertNotSentTo($this->anotherUser, TravelRequestStatusChanged::class);
        Notification::assertNotSentTo($this->adminUser, TravelRequestStatusChanged::class);
    }

     #[Test]
    public function notification_is_not_sent_when_status_update_fails_validation(): void
    {
         $travelRequest = TravelRequest::factory()->requested()->create();

         // Tenta atualizar com status inválido
         $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
              ->patchJson("/api/travel-requests/{$travelRequest->id}/status", ['status' => 'invalid']);

         // Verifica que nenhuma notificação foi enviada
         Notification::assertNothingSent();
    }

     #[Test]
    public function notification_is_not_sent_when_status_update_fails_business_logic(): void
    {
         // Cria pedido aprovado perto da partida
         $travelRequest = TravelRequest::factory()
            ->for($this->regularUser)
            ->approved()
            ->create(['departure_date' => now()->addDay()->format('Y-m-d')]);

        // Tenta cancelar (o que deve falhar a regra de negócio)
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
             ->patchJson("/api/travel-requests/{$travelRequest->id}/status", ['status' => 'canceled']);

        // Verifica que nenhuma notificação foi enviada
         Notification::assertNothingSent();
    }

     #[Test]
    public function notification_is_not_sent_when_updating_to_same_status(): void
    {
         $travelRequest = TravelRequest::factory()->for($this->regularUser)->approved()->create();

         // Tenta 'aprovar' novamente
         $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
              ->patchJson("/api/travel-requests/{$travelRequest->id}/status", ['status' => 'approved']);

         // Verifica que nenhuma notificação foi enviada
         Notification::assertNothingSent();
    }
}
