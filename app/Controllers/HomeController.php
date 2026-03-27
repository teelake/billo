<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;

final class HomeController extends Controller
{
    public function index(): void
    {
        View::render('home/index');
    }
}
