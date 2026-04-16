<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::post('/admin/login', function (Request $request) {
    $email = $request->input('email', $request->input('data.email'));
    $password = $request->input('password', $request->input('data.password'));
    $remember = (bool) $request->boolean('remember', $request->boolean('data.remember'));

    if (blank($email) || blank($password)) {
        return redirect()
            ->to('/admin/login')
            ->withErrors(['email' => 'Please provide your email and password.']);
    }

    $credentials = [
        'email' => $email,
        'password' => $password,
        'is_active' => true,
    ];

    if (! Auth::guard('web')->attempt($credentials, $remember)) {
        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    $request->session()->regenerate();

    return redirect()->intended('/admin');
})
    ->middleware(['guest', 'throttle:admin-login'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('admin.login.post');
