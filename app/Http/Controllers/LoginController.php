<?php

namespace App\Http\Controllers;
use App\Jobs\FetchAdviceLocalReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
// use Illuminate\Foundation\Auth\ResetsPasswords;

class LoginController extends Controller
{
    // use ResetsPasswords;
    /**
     * Handle the incoming request.
     */

     public function __invoke(Request $request)
     {
         $user = User::where('email', $request->email)->first();
     
         if (! $user || ! Hash::check($request->password, $user->password)) {
             // Throw the validation exception if the credentials are incorrect
             throw ValidationException::withMessages([
                 'email' => ['The provided credentials are incorrect.'],
             ]);
         }
     
         // Proceed if authentication is successful
         $token = $user->createToken('auth-token')->plainTextToken;
         $user->load('roles.permissions');
         $saleRepUsername = $user->saleRep ? $user->saleRep->username : null;
         $Client_id = $user->Customer ? $user->Customer->client_id : null;
     
         // Dispatch the job for fetching the Advice Local report asynchronously on login
         if ($Client_id) {
             FetchAdviceLocalReport::dispatch($user->customer->id);
         }
     
         // Return success response with user data and access token
         return response()->json([
             'access_token' => $token,
             'token_type' => 'Bearer',
             'user' => [
                 'id' => $user->id,
                 'name' => $user->name,
                 'email' => $user->email,
                 'roles' => $user->roles->pluck('name'),
                 'permissions' => $user->getAllPermissions()->pluck('name'),
                 'status' => $user->status,
                 'created_at' => $user->created_at,
                 'updated_at' => $user->updated_at,
                 'username' => $saleRepUsername,
                 'client_id' => $Client_id
             ]
         ], 200);
     }
     


    public function getAuthUserDetails(Request $request)
    {
        // Get the currently authenticated user
        $user = Auth::user();

        if ($user) {
            // Load user's roles and permissions
            $user->load('roles.permissions');

            // Check if $user->saleRep is set, if not, set it to null
            $saleRepUsername = $user->saleRep ? $user->saleRep->username : null;

            // Check if $user->Customer is set, if not, set it to null
            $Client_id = $user->Customer ? $user->Customer->client_id : null;

            // Return user details, roles, and permissions in the response
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'), // Get role names
                    'permissions' => $user->getAllPermissions()->pluck('name'), // Get permission names
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'username' => $saleRepUsername,
                    'client_id' => $Client_id
                ]
            ]);
        } else {
            // If no user is authenticated, return an error response
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
    }

    public function logout(Request $request)
    {
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
    }

    // public function sendResetLinkEmail(Request $request)
    // {
    //     // Your logic to send the password reset link email
    //     // You can customize this method according to your needs

    //     $this->validateEmail($request);

    //     $response = $this->broker()->sendResetLink(
    //         $this->credentials($request)
    //     );

    //     return $response == \Password::RESET_LINK_SENT
    //                 ? response()->json(['message' => trans($response)], 200)
    //                 : response()->json(['error' => trans($response)], 400);
    // }

 
    // public function resetPassword(Request $request)
    // {
    //     $request->validate([
    //         'token' => 'required',
    //         'email' => 'required|email',
    //         'password' => 'required|confirmed',
    //     ]);

    //     $response = $this->broker()->reset(
    //         $this->credentials($request),
    //         function ($user, $password) {
    //             $this->resetPassword($user, $password);
    //         }
    //     );

    //     return $response == Password::PASSWORD_RESET
    //         ? response()->json(['message' => trans($response)], 200)
    //         : response()->json(['error' => trans($response)], 400);
    // }

}
