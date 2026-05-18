<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ListInventoryLots;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, ListInventoryLots $listInventoryLots): Response
    {
        return Inertia::render('Inventory/Index', $listInventoryLots->execute($request));
    }
}
