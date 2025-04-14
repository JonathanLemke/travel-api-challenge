<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TravelRequestStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Permitir que qualquer usuário autenticado tente criar um pedido.
        // A associação ao usuário é feita no controller.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [    
            'destination' => 'required|string|max:255',
            // Garante que a data seja hoje ou no futuro, e no formato Y-m-d
            'departure_date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
            // Garante que a data de retorno seja igual ou depois da data de partida, e no formato Y-m-d
            'return_date' => 'required|date|date_format:Y-m-d|after_or_equal:departure_date',
        ];
    }

    /**
     * Handle a failed validation attempt.
     * Retorna uma resposta JSON padronizada com erros 422.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422)); // HTTP status 422 Unprocessable Entity
    }
}
