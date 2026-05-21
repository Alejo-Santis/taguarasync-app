<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreFeResolutionRequest;
use App\Http\Requests\Settings\UpdateFeResolutionRequest;
use App\Models\FeResolution;
use Illuminate\Http\RedirectResponse;

class FeResolutionController extends Controller
{
    public function store(StoreFeResolutionRequest $request): RedirectResponse
    {
        FeResolution::create($request->validated());

        return back()->with('success', 'Resolución creada correctamente.');
    }

    public function update(FeResolution $feResolution, UpdateFeResolutionRequest $request): RedirectResponse
    {
        $feResolution->update($request->validated());

        return back()->with('success', 'Resolución actualizada correctamente.');
    }

    public function toggle(FeResolution $feResolution): RedirectResponse
    {
        $feResolution->update(['is_active' => ! $feResolution->is_active]);

        return back();
    }
}
