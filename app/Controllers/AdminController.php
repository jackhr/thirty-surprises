<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;

final class AdminController
{
    public function index(Request $request): void
    {
        View::render('admin/index', [
            'admin' => Auth::user(),
            'surprisesLeft' => null,
        ]);
    }
}
