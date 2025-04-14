<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

class TravelRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'destination',
        'departure_date',
        'return_date',
        'status',
        'approved_at',
        'canceled_at',
    ];

    // casts das colunas com os tipos certos
    protected $casts = [
        'departure_date' => 'date:Y-m-d',
        'return_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    /**
     * Define o relacionamento: um TravelRequest pertence a um User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define o relacionamento: um TravelRequest pertence a um User.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para filtrar por período.
     * Ex: TravelRequest::byPeriod('2024-01-01', '2024-01-31')->get();
     * Nota: Esta lógica considera viagens que começam OU terminam no período.
     */
    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('departure_date', [$startDate, $endDate])
                    ->orWhereBetween('return_date', [$startDate, $endDate]);
    }

     /**
     * Scope para filtrar por destino (busca parcial).
     * Ex: TravelRequest::byDestination('Paulo')->get();
     */
    public function scopeByDestination($query, $destination)
    {
        return $query->where('destination', 'like', "%{$destination}%");
    }

    /**
     * Verifica se o pedido pode ser cancelado baseado na regra de negócio.
     * @return bool
     */
    public function canBeCanceled(): bool
    {
        // Se não está 'approved', permite o cancelamento (ou alteração de status)
        if ($this->status !== 'approved') {
            return true;
        }

        // Se está 'approved', verifica se a data de partida está a >= 2 dias no futuro.
        $departureDate = Carbon::parse($this->departure_date);

        // Usa startOfDay() para garantir que comparações de dia sejam precisas
        // Verifica se a data de partida é estritamente DEPOIS de amanhã
        return $departureDate->startOfDay()->isAfter(now()->addDay()->startOfDay());
    }
}
