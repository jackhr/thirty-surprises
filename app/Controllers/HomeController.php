<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\UserRepository;

final class HomeController
{
    public function index(Request $request): void
    {
        View::render('index', [
            'surprisesLeft' => null,
            'user' => Auth::user(),
        ]);
    }

    public function showLogin(Request $request): void
    {
        View::render('login', [
            'surprisesLeft' => null,
        ]);
    }

    public function login(Request $request): void
    {
        $name = trim((string) $request->input('name', ''));
        $password = (string) $request->input('password', '');

        $users = new UserRepository();
        $user = $users->findByName($name);

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            if ($request->isAjax()) {
                Response::json(['error' => 'Invalid credentials'], 401);
            }

            Response::redirect('/');
        }

        Auth::login([
            'id' => (string) $user['id'],
            'name' => (string) $user['name'],
        ]);

        if ($request->isAjax()) {
            Response::json([
                'success' => true,
                'redirect' => '/admin',
            ]);
        }

        Response::redirect('/admin');
    }

    public function logout(Request $request): void
    {
        Auth::logout();

        if ($request->isAjax()) {
            Response::json([
                'success' => true,
                'redirect' => '/',
            ]);
        }

        Response::redirect('/');
    }
}
