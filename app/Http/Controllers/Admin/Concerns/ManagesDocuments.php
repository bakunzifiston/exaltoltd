<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TransactionDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Supporting documents on a return or sample: stored privately, served after a check. */
trait ManagesDocuments
{
    protected function storeDocuments(Request $request, Model $record, bool $required = false): void
    {
        $request->validate([
            'documents' => [$required ? 'required' : 'nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        foreach ((array) $request->file('documents', []) as $file) {
            $folder = 'documents/'.Str::kebab(class_basename($record)).'/'.$record->getKey();
            $path = $file->storeAs($folder, Str::random(32).'.'.$file->extension(), TransactionDocument::DISK);
            $record->documents()->create([
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
            $record->logEvent('document_added', details: ['name' => $file->getClientOriginalName()]);
        }
    }

    protected function download(Model $record, TransactionDocument $document): StreamedResponse
    {
        $this->ownDocument($record, $document);

        return Storage::disk(TransactionDocument::DISK)->download($document->path, $document->original_name);
    }

    protected function removeDocument(Model $record, TransactionDocument $document): void
    {
        $this->ownDocument($record, $document);
        $record->logEvent('document_removed', details: ['name' => $document->original_name]);
        $document->delete();
    }

    private function ownDocument(Model $record, TransactionDocument $document): void
    {
        abort_unless($document->documentable_type === $record->getMorphClass() && (int) $document->documentable_id === (int) $record->getKey(), 404);
    }
}
