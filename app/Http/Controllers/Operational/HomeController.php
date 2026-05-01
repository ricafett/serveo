<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Redirect the user to their role-appropriate default landing page.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('SERVER')) {
            return redirect()->route('floor');
        }

        if ($user->hasRole('CASHIER')) {
            return redirect()->route('lookup');
        }

        if ($user->hasRole('ADMIN')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Fallback for users with no recognized role
        abort(403, __('No valid role assigned'));
    }
}
