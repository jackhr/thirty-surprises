<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\SurpriseRepository;
use App\Services\UserRepository;

final class HomeController
{
    public function index(Request $request): void
    {
        $repository = new SurpriseRepository();
        $allSurprises = $repository->getLiveSorted();
        $completedSurprises = array_values(array_filter($allSurprises, static fn (array $surprise): bool => !empty($surprise['viewed'])));

        View::render('index', [
            'allSurprises' => $allSurprises,
            'completedSurprises' => $completedSurprises,
            'surprisesLeft' => 30 - count($completedSurprises),
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
            Response::redirect('/');
        }

        Auth::login([
            'id' => (string) $user['id'],
            'name' => (string) $user['name'],
        ]);

        Response::redirect('/admin');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Response::redirect('/');
    }
}
