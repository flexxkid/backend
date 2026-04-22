<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
