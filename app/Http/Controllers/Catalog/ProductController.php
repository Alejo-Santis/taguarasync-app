<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Products\ListProducts;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, ListProducts $listProducts): Response
    {
        return Inertia::render('Products/Index', $listProducts->execute($request));
    }
}
