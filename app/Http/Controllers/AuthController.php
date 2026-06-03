<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LdapAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('glpi-ai.dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, LdapAuthService $ldap): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'login' => 'Muitas tentativas. Tente novamente em alguns minutos.',
            ])->onlyInput('login');
        }

        if ((bool) config('ldap.enabled')) {
            try {
                Log::info('GLPI AI login LDAP iniciado.', [
                    'login' => $data['login'],
                    'ip' => $request->ip(),
                ]);

                $result = $ldap->authenticate($data['login'], $data['password']);
                Auth::login($result['user'], true);
                RateLimiter::clear($key);
                $request->session()->regenerate();

                Log::info('GLPI AI login LDAP concluido.', [
                    'user_id' => $result['user']->id,
                    'login' => $data['login'],
                    'redirect' => route('glpi-ai.dashboard'),
                ]);

                return redirect()->route('glpi-ai.dashboard');
            } catch (\Throwable $throwable) {
                RateLimiter::hit($key, 300);
                Log::warning('GLPI AI login LDAP falhou.', [
                    'login' => $data['login'],
                    'ip' => $request->ip(),
                    'error' => $throwable->getMessage(),
                ]);

                return back()->withErrors([
                    'login' => 'Nao foi possivel autenticar no LDAP. Confira usuario, senha e permissao de acesso ao DTI.',
                ])->onlyInput('login');
            }
        }

        $isEmailLogin = filter_var($data['login'], FILTER_VALIDATE_EMAIL);

        if ($isEmailLogin) {
            $credentials = ['email' => $data['login'], 'password' => $data['password']];
        } elseif (Schema::hasColumn('users', 'ldap_username')) {
            $credentials = ['ldap_username' => $data['login'], 'password' => $data['password']];
        } else {
            return back()->withErrors([
                'login' => 'Banco ainda nao foi atualizado para login LDAP. Rode php artisan migrate.',
            ])->onlyInput('login');
        }

        if (! Auth::attempt($credentials, true)) {
            RateLimiter::hit($key, 300);

            return back()->withErrors([
                'login' => $isEmailLogin
                    ? 'Credenciais invalidas.'
                    : 'Login de rede nao autenticado. O LDAP esta desligado no .env; use um e-mail local ou ative LDAP_ENABLED=true.',
            ])->onlyInput('login');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->route('glpi-ai.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
