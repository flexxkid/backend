<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            UserAccount::with(['role', 'employee'])->paginate($request->integer('per_page', 15))
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            UserAccount::with(['role', 'employee'])->findOrFail($id)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'EmployeeID' => 'nullable|exists:Employee,EmployeeID|unique:UserAccount,EmployeeID',
            'Username' => 'required|string|max:100|unique:UserAccount,Username',
            'RoleID' => 'required|exists:Role,RoleID',
            'password' => 'nullable|string|min:8|confirmed',
            'PasswordHash' => 'nullable|string|min:8',
        ]);

        $password = $validated['password'] ?? $validated['PasswordHash'] ?? null;

        abort_if($password === null, 422, 'A password is required.');

        $account = UserAccount::create([
            'EmployeeID' => $validated['EmployeeID'] ?? null,
            'Username' => $validated['Username'],
            'PasswordHash' => Hash::make($password),
            'RoleID' => $validated['RoleID'],
            'AccountStatus' => 'active',
        ]);

        return response()->json($account->load(['role', 'employee']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $account = UserAccount::findOrFail($id);

        $validated = $request->validate([
            'EmployeeID' => 'nullable|exists:Employee,EmployeeID|unique:UserAccount,EmployeeID,'.$account->UserID.',UserID',
            'Username' => 'required|string|max:100|unique:UserAccount,Username,'.$account->UserID.',UserID',
            'RoleID' => 'required|exists:Role,RoleID',
            'AccountStatus' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8|confirmed',
            'PasswordHash' => 'nullable|string|min:8',
        ]);

        if (array_key_exists('password', $validated) || array_key_exists('PasswordHash', $validated)) {
            $validated['PasswordHash'] = Hash::make($validated['password'] ?? $validated['PasswordHash']);
        }

        unset($validated['password']);

        $account->update($validated);

        return response()->json($account->load(['role', 'employee']));
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $account = UserAccount::findOrFail($id);
        $account->update(['PasswordHash' => Hash::make($request->string('password')->toString())]);

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function activate(int $id): JsonResponse
    {
        $account = UserAccount::findOrFail($id);
        $account->update(['AccountStatus' => 'active']);

        return response()->json($account->load(['role', 'employee']));
    }

    public function deactivate(int $id): JsonResponse
    {
        $account = UserAccount::findOrFail($id);
        $account->update(['AccountStatus' => 'inactive']);

        return response()->json(['message' => 'Account deactivated']);
    }

    public function destroy(int $id): JsonResponse
    {
        $account = UserAccount::findOrFail($id);
        $account->update(['AccountStatus' => 'inactive']);

        return response()->json(['message' => 'Account deactivated']);
    }
}
