<?php

namespace App\Http\Controllers;

use App\Support\PersonName;
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

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var UserAccount $user */
        $user = $request->user();
        abort_if(! $user?->employee, 422, 'This account is not linked to an employee profile.');

        $validated = $request->validate([
            'FirstName' => ['required', 'string', 'max:100'],
            'LastName' => ['required', 'string', 'max:100'],
            'Email' => ['required', 'email', 'max:150', 'unique:Employee,Email,'.$user->employee->EmployeeID.',EmployeeID'],
            'PhoneNumber' => ['nullable', 'string', 'max:20'],
            'PostalAddress' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = $user->employee;
        $before = $employee->toArray();
        $payload = PersonName::normalizePayload([
            'FirstName' => $validated['FirstName'],
            'LastName' => $validated['LastName'],
            'Email' => $validated['Email'],
            'PhoneNumber' => $validated['PhoneNumber'] ?? $employee->PhoneNumber,
            'PostalAddress' => $validated['PostalAddress'] ?? $employee->PostalAddress,
        ], false);

        $employee->update($payload);

        $this->auditLogService->record(
            'profile_update',
            $employee->getTable(),
            $employee->getKey(),
            $before,
            $employee->fresh()->toArray(),
            $user,
            $request->ip(),
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh()->load(['employee', 'role', 'notifications']),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var UserAccount $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        abort_if(! Hash::check($validated['current_password'], $user->PasswordHash), 422, 'The current password is incorrect.');

        $user->update([
            'PasswordHash' => Hash::make($validated['new_password']),
        ]);

        $this->auditLogService->record(
            'password_change',
            $user->getTable(),
            $user->getKey(),
            null,
            ['PasswordHash' => 'updated'],
            $user,
            $request->ip(),
        );

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
