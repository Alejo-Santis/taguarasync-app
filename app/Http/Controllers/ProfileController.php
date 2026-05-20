<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Profile/Index', [
            'user' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }
}
