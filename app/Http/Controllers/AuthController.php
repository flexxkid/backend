<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

public function register(Request $request)
{
    // 1. Validate the input
    $request->validate([
        'name' => 'required|string|max:255',
        'username' => 'required|string|unique:users,username|max:255',
        'email' => 'required|string|email|unique:users,email|max:255',
        'password' => 'required|string|min:8|confirmed', // looks for password_confirmation
    ]);

    // 2. Create the User record
    // Laravel's Eloquent will handle the mass assignment if $fillable is set
    $user = User::create([
        'name' => $request->name,
        'username' => $request->username,
        'email' => $request->email,
        'password' => Hash::make($request->password), // Always hash the password!
    ]);

    // 3. Issue the Sanctum token immediately
    $token = $user->createToken($request->device_name)->plainTextToken;

    return response()->json([
        'user' => $user,
        'access_token' => $token,
        'token_type' => 'Bearer',
    ], 201);
}


    public function login(Request $request)
    {
        // 1. Validate the incoming request
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // 2. Find the matching UserAccount (assuming your model is 'User')
        $user = User::where('username', $request->username)->first();

        // 3. Verify password using Hash::check()
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // 4. Create the token and return the plain-text string
        // Sanctum hashes it before saving to 'personal_access_tokens'
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}