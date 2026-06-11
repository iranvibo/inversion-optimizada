<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Muestra la vista de login.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('login');
    }

    /**
     * Procesa el login.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'email' => 'Las credenciales no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    /**
     * Auto-login para desarrollo y testeo rápido con un solo clic.
     */
    public function autoLogin()
    {
        // Obtener o crear el usuario de prueba predeterminado
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Usuario Demo ViBo',
                'password' => Hash::make('password'),
            ]
        );

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Sesión iniciada con éxito (Modo Demo).');
    }

    /**
     * Cierra la sesión.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
