<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_account_can_register_login_fetch_profile_and_logout(): void
    {
        $role = Role::create([
            'RoleName' => 'Administrator',
            'RoleDescription' => 'System administrator',
        ]);

        $registerResponse = $this->postJson('/api/auth/register', [
            'Username' => 'admin.user',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'RoleID' => $role->RoleID,
            'account_status' => 'active',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('user.Username', 'admin.user');

        $loginResponse = $this->postJson('/api/auth/login', [
            'Username' => 'admin.user',
            'password' => 'secret123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'user']);

        $token = $loginResponse->json('access_token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('Username', 'admin.user');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');
    }

    public function test_inactive_user_account_cannot_log_in(): void
    {
        $role = Role::create([
            'RoleName' => 'HR Administrator',
            'RoleDescription' => 'System administrator',
        ]);

        $this->postJson('/api/auth/register', [
            'Username' => 'inactive.user',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'RoleID' => $role->RoleID,
            'account_status' => 'inactive',
        ])->assertCreated();

        $this->postJson('/api/auth/login', [
            'Username' => 'inactive.user',
            'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_deactivate_endpoint_marks_user_inactive_and_blocks_future_login(): void
    {
        $role = Role::create([
            'RoleName' => 'HR Administrator',
            'RoleDescription' => 'System administrator',
        ]);

        $employee = Employee::create([
            'FullName' => 'Inactive Later',
            'DateOfBirth' => '1992-01-01',
            'Email' => 'inactive.later@example.com',
            'NationalID' => 'EMP-900',
            'HireDate' => '2025-01-01',
            'EmploymentStatus' => 'Active',
        ]);

        $admin = UserAccount::create([
            'Username' => 'admin.account',
            'PasswordHash' => Hash::make('secret123'),
            'RoleID' => $role->RoleID,
            'AccountStatus' => 'active',
        ]);

        $user = UserAccount::create([
            'EmployeeID' => $employee->EmployeeID,
            'Username' => 'inactive.later',
            'PasswordHash' => Hash::make('secret123'),
            'RoleID' => $role->RoleID,
            'AccountStatus' => 'active',
        ]);

        $adminToken = $admin->createToken('admin-session')->plainTextToken;
        $userToken = $user->createToken('user-session')->plainTextToken;

        $this->withToken($adminToken)
            ->patchJson("/api/user-accounts/{$user->UserID}/deactivate")
            ->assertOk()
            ->assertJsonPath('UserID', $user->UserID)
            ->assertJsonPath('AccountStatus', 'inactive');

        $this->assertDatabaseHas('UserAccount', [
            'UserID' => $user->UserID,
            'AccountStatus' => 'inactive',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/auth/login', [
            'Username' => 'inactive.later',
            'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_inactive_user_with_token_cannot_access_authenticated_profile_route(): void
    {
        $role = Role::create([
            'RoleName' => 'HR Administrator',
            'RoleDescription' => 'System administrator',
        ]);

        $user = UserAccount::create([
            'Username' => 'inactive.token.user',
            'PasswordHash' => Hash::make('secret123'),
            'RoleID' => $role->RoleID,
            'AccountStatus' => 'inactive',
        ]);

        $token = $user->createToken('inactive-session')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
