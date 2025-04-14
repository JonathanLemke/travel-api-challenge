<?php

namespace App\Http\Controllers;

// Imports dos Requests e Resources
use App\Http\Requests\TravelRequestFilterRequest;
use App\Http\Requests\TravelRequestStoreRequest;
use App\Http\Requests\TravelRequestUpdateStatusRequest;
use App\Http\Resources\TravelRequestResource;

// Import dos Models e Notifications
use App\Models\TravelRequest;
use App\Notifications\TravelRequestStatusChanged;

// Imports de Facades e Classes base
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TravelRequestController extends Controller
{

     /**
     * Listar todos os pedidos de viagem com filtros opcionais.
     * Usa injeção de dependência para o Form Request de filtro
     */
    public function index(TravelRequestFilterRequest $request): AnonymousResourceCollection
    {
        // Começa a query pelo modelo
        $query = TravelRequest::query();

        // Lógica de Autorização: Se não for admin, só vê os próprios pedidos
        if (!Auth::user()->isAdmin()) {
            $query->where('user_id', Auth::id());
        }

        // Aplica filtros validados pelo TravelRequestFilterRequest
        // Usar $request->validated() garante que apenas dados válidos sejam usados
        $validatedFilters = $request->validated();

        if (isset($validatedFilters['status'])) {
            $query->status($validatedFilters['status']);
        }

        if (isset($validatedFilters['start_date']) && isset($validatedFilters['end_date'])) {
            $query->byPeriod($validatedFilters['start_date'], $validatedFilters['end_date']);
        }

        if (isset($validatedFilters['destination'])) {
            $query->byDestination($validatedFilters['destination']);
        }

        $travelRequests = $query->with('user')->latest()->get();

        return TravelRequestResource::collection($travelRequests);
    }

    /**
     * Criar um novo pedido de viagem.
     * Usa injeção de dependência para o Form Request de criação/validação
     */
    public function store(TravelRequestStoreRequest $request): TravelRequestResource
    {
        // Cria uma nova instância com os dados validados
        $travelRequest = new TravelRequest($request->validated());
        // Associa ao usuário autenticado
        $travelRequest->user_id = Auth::id();
        // Define o status inicial
        $travelRequest->status = 'requested';
        $travelRequest->save();

        return new TravelRequestResource($travelRequest->load('user'));
    }

    /**
     * Consultar um pedido de viagem específico.
     */
    public function show(string $id): TravelRequestResource|JsonResponse
    {
        // Encontra o pedido ou falha com 404
        $travelRequest = TravelRequest::with('user')->find($id);

         if (!$travelRequest) {
             return response()->json(['message' => 'Travel request not found'], 404);
         }

        // Lógica de Autorização: Admin pode ver qualquer um, usuário normal só pode ver o seu
        if (!Auth::user()->isAdmin() && $travelRequest->user_id !== Auth::id()) {
             // Retorna 403 Forbidden se não autorizado
            return response()->json(['message' => 'Unauthorized to view this request'], 403);
        }

        return new TravelRequestResource($travelRequest);
    }

    /**
     * Atualizar o status de um pedido de viagem.
     * Usa injeção de dependência para o Form Request de atualização/validação
     */
    public function updateStatus(string $id, TravelRequestUpdateStatusRequest $request): TravelRequestResource|JsonResponse
    {
         // Encontra o pedido ou falha com 404, já carrega o usuário para notificação
        $travelRequest = TravelRequest::with('user')->find($id);

         if (!$travelRequest) {
             return response()->json(['message' => 'Travel request not found'], 404);
         }

         if (!Auth::user()->isAdmin()) {
              return response()->json(['message' => 'Unauthorized to update status'], 403);
          }

        $newStatus = $request->validated()['status'];

        // Verificar se pode cancelar
        // Só checa se a tentativa é de cancelar um pedido JÁ aprovado
        if ($travelRequest->status === 'approved' && $newStatus === 'canceled') {
            if (!$travelRequest->canBeCanceled()) {
                return response()->json([
                    'message' => 'This request cannot be canceled. The departure date is too close.'
                ], 422);
            }
        }

         // Evitar atualização se o status já for o desejado 
         if ($travelRequest->status === $newStatus) {
             return new TravelRequestResource($travelRequest); // Retorna o estado atual sem alterações
         }

        // Atualizar o status e timestamps relevantes
        $travelRequest->status = $newStatus;
        $travelRequest->approved_at = ($newStatus === 'approved') ? now() : null; // Seta se aprovado, limpa se não
        $travelRequest->canceled_at = ($newStatus === 'canceled') ? now() : null; // Seta se cancelado, limpa se não

        $travelRequest->save();

        // Enviar notificação para o usuário que CRIOU o pedido
        $travelRequest->user->notify(new TravelRequestStatusChanged($travelRequest));

        return new TravelRequestResource($travelRequest);
    }

}
