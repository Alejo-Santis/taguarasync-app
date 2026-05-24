<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreFeResolutionRequest;
use App\Http\Requests\Settings\UpdateFeResolutionRequest;
use App\Models\FeResolution;
use App\Models\FeSend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FeResolutionController extends Controller
{
    public function store(StoreFeResolutionRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            $nextDocumentNumber = (int) ($data['next_document_number'] ?? $data['from_number']);

            $resolution = FeResolution::create(Arr::except($data, 'next_document_number'));

            FeSend::create([
                'tenant_id' => $resolution->tenant_id,
                'resolution_id' => $resolution->id,
                'next_consecutive' => $nextDocumentNumber - 1,
            ]);
        });

        return back()->with('success', 'Resolución creada correctamente.');
    }

    public function update(FeResolution $feResolution, UpdateFeResolutionRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($feResolution, $request): void {
            $data = $request->validated();
            $nextDocumentNumber = $data['next_document_number'] ?? null;

            $feResolution->update(Arr::except($data, 'next_document_number'));

            if ($nextDocumentNumber !== null) {
                $feResolution->send()->updateOrCreate(
                    ['resolution_id' => $feResolution->id],
                    [
                        'tenant_id' => $feResolution->tenant_id,
                        'next_consecutive' => ((int) $nextDocumentNumber) - 1,
                    ]
                );
            }
        });

        return back()->with('success', 'Resolución actualizada correctamente.');
    }

    public function toggle(FeResolution $feResolution): RedirectResponse
    {
        $feResolution->update(['is_active' => ! $feResolution->is_active]);

        return back();
    }
}
