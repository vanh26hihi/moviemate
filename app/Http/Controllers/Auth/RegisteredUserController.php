<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $hasPhone = Schema::hasColumn('users', 'phone');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ];

        if ($hasPhone) {
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        $user = new User;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = Hash::make($validated['password']);

        if ($hasPhone) {
            $user->phone = filled($validated['phone'] ?? null)
                ? trim($validated['phone'])
                : null;
        }

        if (Schema::hasColumn('users', 'role_id') && Schema::hasTable('roles')) {
            $user->role_id = DB::table('roles')
                ->whereRaw('LOWER(name) = ?', ['user'])
                ->value('id');
        }

        if (Schema::hasColumn('users', 'status')) {
            $user->status = 'active';
        }

        $user->save();

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
