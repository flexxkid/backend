<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'EmployeeID' => ['nullable', 'integer', 'exists:Employee,EmployeeID'],
            'RoleID' => ['nullable', 'integer', 'exists:Role,RoleID'],
            'Username' => ['required', 'string', 'max:100', 'unique:UserAccount,Username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'account_status' => ['nullable', 'string', 'max:50'],
        ]);

        $user = UserAccount::create([
            'EmployeeID' => $data['EmployeeID'] ?? null,
            'Username' => $data['Username'],
            'PasswordHash' => Hash::make($data['password']),
            'RoleID' => $data['RoleID'] ?? null,
            'AccountStatus' => $data['account_status'] ?? 'active',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->auditLogService->record(
            'register',
            $user->getTable(),
            $user->getKey(),
            null,
            $user->toArray(),
            $user,
            $request->ip(),
        );

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load(['employee', 'role']),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'Username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = UserAccount::query()
            ->where('Username', $credentials['Username'])
            ->where('AccountStatus', 'active')
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->PasswordHash)) {
            throw ValidationException::withMessages([
                'Username' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->forceFill([
            'LastLogin' => Carbon::now(),
        ])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->auditLogService->record(
            'login',
            $user->getTable(),
            $user->getKey(),
            null,
            ['LastLogin' => $user->LastLogin],
            $user,
            $request->ip(),
        );

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load(['employee', 'role']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()?->load(['employee', 'role', 'notifications']));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var UserAccount $user */
        $user = $request->user();
        $user?->currentAccessToken()?->delete();

        if ($user) {
            $this->auditLogService->record(
                'logout',
                $user->getTable(),
                $user->getKey(),
                null,
                null,
                $user,
                $request->ip(),
            );
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
