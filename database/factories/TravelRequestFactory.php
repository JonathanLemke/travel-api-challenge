<?php

namespace Database\Factories;

use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TravelRequest>
 */
class TravelRequestFactory extends Factory
{

    protected $model = TravelRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Cria um User automaticamente se não for passado um user_id
            'user_id' => User::factory(),
            'destination' => $this->faker->city,
            // Gera datas futuras realistas
            'departure_date' => $this->faker->dateTimeBetween('+1 week', '+2 weeks')->format('Y-m-d'),
            'return_date' => $this->faker->dateTimeBetween('+3 weeks', '+4 weeks')->format('Y-m-d'),
            // Define um status aleatório por padrão
            'status' => $this->faker->randomElement(['requested', 'approved', 'canceled']),
            // Timestamps `approved_at` e `canceled_at` são null por padrão,
            // mas podem ser definidos nos states abaixo.
            'approved_at' => null,
            'canceled_at' => null,
        ];
    }

    /**
     * Indicate that the travel request is requested.
     */
    public function requested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'requested',
            'approved_at' => null,
            'canceled_at' => null,
        ]);
    }

    /**
     * Indicate that the travel request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
            'canceled_at' => null,
        ]);
    }

    /**
     * Indicate that the travel request is canceled.
     */
    public function canceled(): static
    {
         // Garante que um pedido cancelado possa ter vindo de 'requested' ou 'approved'
         $isApprovedOriginally = $this->faker->boolean();
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            // Se foi aprovado antes, mantém a data de aprovação (opcional)
            'approved_at' => $isApprovedOriginally ? ($attributes['approved_at'] ?? $this->faker->dateTimeThisMonth()) : null,
            'canceled_at' => now(), // Define a data de cancelamento
        ]);
    }
}
