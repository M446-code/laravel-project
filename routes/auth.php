<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Auth;

Route::post('login', LoginController::class);
Route::get('logout', function(){
    // Get the currently authenticated user by authorization token
    $user = Auth::user();

    if ($user) {
        // Revoke all tokens for the user
        $user->tokens()->delete();

        // Return a success response
        return response()->json(['message' => 'loggedOut'], 200);
    } else {
        // If no user is authenticated, return an error response
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
});

Route::post('/password/reset-link', [LoginController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [LoginController::class, 'resetPassword']);