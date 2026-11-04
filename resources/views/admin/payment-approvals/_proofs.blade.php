{{-- Proof thumbnails. Expects $proofs (collection of SalePaymentProof); optional $large. --}}
@php($size = ($large ?? false) ? 'h-40 w-40' : 'h-16 w-16')
@if ($proofs->isEmpty())
    <span class="text-xs text-slate-400">No proof attached</span>
@else
    <div class="flex flex-wrap gap-2">
        @foreach ($proofs as $proof)
            <a href="{{ route('admin.payment-proofs.show', $proof) }}" target="_blank" rel="noopener"
               title="{{ $proof->original_name }}"
               class="group block overflow-hidden rounded-lg border border-slate-200 bg-slate-50 hover:border-teal-400">
                @if ($proof->isImage())
                    <img src="{{ route('admin.payment-proofs.show', $proof) }}" alt="Payment proof {{ $loop->iteration }}" loading="lazy" class="{{ $size }} object-cover">
                @else
                    <span class="flex {{ $size }} flex-col items-center justify-center gap-1 p-1 text-center text-[11px] font-medium text-slate-600">
                        <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.6" aria-hidden="true"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg>
                        PDF
                    </span>
                @endif
            </a>
        @endforeach
    </div>
@endif
