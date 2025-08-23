<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        // Check if the hash matches the email
        if (! hash_equals((string) $request->route('hash'), sha1($user->email))) {
            return response()->json(['error' => 'Invalid verification link'], 400);
        }

        // Check if email is already verified
        if ($user->hasVerifiedEmail()) {
            return Redirect::to(config('app.frontend_url').'/email-verification-success?status=already_verified');
        }

        // Mark email as verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // Redirect to frontend
        return Redirect::to(config('app.frontend_url').'/email-verification-success?status=success');
    }
}
