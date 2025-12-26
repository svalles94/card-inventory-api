<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreUserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class StoreRegistrationController extends Controller
{
    /**
     * Show the registration form.
     */
    public function showRegistrationForm()
    {
        return view('auth.store-register');
    }

    /**
     * Handle a registration request.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'store_name' => ['required', 'string', 'max:255'],
            'store_address' => ['nullable', 'string', 'max:500'],
            'store_phone' => ['nullable', 'string', 'max:255'],
        ]);

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_platform_admin' => false,
        ]);

        // Create store
        $store = Store::create([
            'owner_id' => $user->id,
            'name' => $validated['store_name'],
            'address' => $validated['store_address'] ?? null,
            'phone' => $validated['store_phone'] ?? null,
            'email' => $validated['email'],
        ]);

        // Assign owner role
        StoreUserRole::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'role' => 'owner',
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Set current store in session
        session(['current_store_id' => $store->id]);

        return redirect()->route('filament.store.pages.dashboard');
    }
}

