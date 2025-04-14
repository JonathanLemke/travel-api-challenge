<?php

namespace App\Notifications;

use App\Models\TravelRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

// Implementar ShouldQueue faz com que a notificação seja processada em background
class TravelRequestStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    // Propriedade para armazenar o pedido de viagem
    public TravelRequest $travelRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(TravelRequest $travelRequest)
    {
        $this->travelRequest = $travelRequest;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Determina o texto baseado no status atual do pedido
        $statusText = $this->travelRequest->status === 'approved' ? 'aprovado' : 'cancelado';

         // Gera a URL absoluta para a ação do botão
        $url = URL::route('travel-requests.show', ['id' => $this->travelRequest->id]);

        return (new MailMessage)
            ->subject("Seu pedido de viagem foi {$statusText}")
            ->line("Olá {$notifiable->name},")
            ->line("Seu pedido de viagem para {$this->travelRequest->destination} foi {$statusText}.")
            ->line("Data de ida: {$this->travelRequest->departure_date->format('d/m/Y')}")
            ->line("Data de volta: {$this->travelRequest->return_date->format('d/m/Y')}")
            ->action('Ver Detalhes', $url)
            ->line('Obrigado por utilizar nosso sistema!');
    }

}
