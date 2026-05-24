<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalePayment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalePaymentAttachmentController extends Controller
{
    public function show(SalePayment $salePayment): StreamedResponse
    {
        abort_unless($salePayment->attachment_path, 404);
        abort_unless(Storage::exists($salePayment->attachment_path), 404);

        return Storage::download($salePayment->attachment_path);
    }
}
