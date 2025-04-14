<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TravelRequestFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Qualquer usuário autenticado pode enviar parâmetros de filtro.
        return true;
    }

     /**
     * Get the validation rules that apply to the request.
     * Valida os filtros se eles forem enviados, mas não os torna obrigatórios.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Status opcional, mas se enviado, deve ser um dos valores permitidos.
            'status' => 'nullable|string|in:requested,approved,canceled',
            // Data inicial opcional, mas se enviada, deve ser data válida no formato Y-m-d.
            'start_date' => 'nullable|date|date_format:Y-m-d',
            // Data final opcional, mas se enviada, deve ser data válida Y-m-d e posterior ou igual a start_date.
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            // Destino opcional, mas se enviado, deve ser string com no máximo 255 caracteres.
            'destination' => 'nullable|string|max:255',
        ];
    }

    // Nota: Não usamos failedValidation para filtros. Isso evita erros 422, permitindo listagens completas, e filtros inválidos são ignorados da validação.
    // Isso é intencional, pois não queremos que filtros inválidos impeçam a listagem.

}
