<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalePaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proof files carry customers' phone and account numbers, so they live on the private disk
 * and are only handed to people involved with the payment.
 */
class PaymentProofController extends Controller
{
    public function show(Request $request, SalePaymentProof $proof): StreamedResponse
    {
        $submission = $proof->submission()->with('sale:id,created_by')->firstOrFail();
        $user = $request->user();

        abort_unless(
            $user->can('approve-payments')
                || $user->can('manage-all-sales')
                || $submission->submitted_by === $user->id
                || $submission->sale?->created_by === $user->id,
            403,
        );

        $disk = Storage::disk($proof->disk);
        abort_unless($disk->exists($proof->path), 404);

        $inline = $proof->isImage() || $proof->mime === 'application/pdf';

        return $disk->response($proof->path, $proof->original_name, [
            'Content-Type' => $proof->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }
}
