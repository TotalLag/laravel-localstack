<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $attributes = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
        ]);

        $attributes['uuid'] = (string) Str::uuid(); // Generate UUID

        $user = User::create($attributes);

        return response()->json(['message' => 'User created successfully', 'user' => $user]);
    }

    public function show($uuid)
    {
        $user = User::find($uuid);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    public function index()
    {
        $users = User::all();

        return response()->json($users);
    }

    public function update(Request $request, $uuid)
    {
        $user = User::find($uuid);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $attributes = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email',
        ]);

        $user->update($attributes);

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    public function destroy($uuid)
    {
        $user = User::find($uuid);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}