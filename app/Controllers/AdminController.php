<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Services\SurpriseRepository;

final class AdminController
{
    public function index(Request $request): void
    {
        $repository = new SurpriseRepository();

        View::render('admin/index', [
            'surprises' => $repository->getAllSorted(),
            'admin' => Auth::user(),
            'surprisesLeft' => null,
        ]);
    }
}
