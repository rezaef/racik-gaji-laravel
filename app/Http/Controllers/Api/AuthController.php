<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Akun demo untuk presentasi (hanya aktif bila APP_DEMO_SWITCHER=true). */
    private const DEMO_ACCOUNTS = [
        User::ROLE_HR => 'dewi.lestari@racik.test',
        User::ROLE_MANAGER => 'syaiful.akbar@racik.test',
        User::ROLE_EMPLOYEE => 'reza.eka@racik.test',
    ];

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }

        return $this->tokenResponse($user);
    }

    public function demoAccounts(): JsonResponse
    {
        $this->ensureDemoEnabled();

        $users = User::with('employee')->whereIn('email', self::DEMO_ACCOUNTS)->get()->keyBy('role');

        return response()->json(['data' => collect(self::DEMO_ACCOUNTS)->map(fn ($email, $role) => [
            'role' => $role,
            'name' => $users[$role]->name ?? null,
            'position' => $users[$role]->employee->position ?? null,
        ])->values()]);
    }

    public function demoLogin(Request $request): JsonResponse
    {
        $this->ensureDemoEnabled();

        $role = $request->validate(['role' => ['required', 'in:'.implode(',', array_keys(self::DEMO_ACCOUNTS))]])['role'];

        return $this->tokenResponse(User::where('email', self::DEMO_ACCOUNTS[$role])->firstOrFail());
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->load('employee.manager')]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    private function tokenResponse(User $user): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user->load('employee'),
        ]);
    }

    private function ensureDemoEnabled(): void
    {
        abort_unless(config('app.demo_switcher'), 404);
    }
}
