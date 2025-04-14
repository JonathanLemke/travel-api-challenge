<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     * Aplica o middleware jwt.verify a todas as rotas deste controller,
     * exceto 'login' e 'register'.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.verify', ['except' => ['login', 'register']]);
    }

    /**
     * Registra um novo usuário.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Cria o usuário com role padrão 'user'
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Loga o usuário recém-criado e obtém o token JWT
        $token = Auth::login($user);

        return $this->respondWithToken($token);
    }

    /**
     * Autentica o usuário e retorna o token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $credentials = $request->only(['email', 'password']);

        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Retorna os dados do usuário autenticado.
     */
    public function me(): JsonResponse
    {
        return response()->json(Auth::user());
    }

    /**
     * Desloga o usuário
     */
    public function logout(): JsonResponse
    {
        Auth::logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Renova o token JWT. O token antigo é invalidado.
     */
    public function refresh(): JsonResponse
    {
        // Auth::refresh() gera um novo token e invalida o antigo
         try {
            return $this->respondWithToken(Auth::refresh());
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Token is invalid'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Could not refresh token', 'details' => $e->getMessage()], 401);
        }
    }

    /**
     * Formata a resposta JSON com o token JWT.
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        // Auth::factory()->getTTL() obtém o tempo de vida do token em minutos
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => Auth::user()
        ]);
    }

}
