<?php

namespace App\Http\Controllers;

use App\Models\AdditionalDocuments;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function uploadDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'EmployeeID' => ['required', 'integer', 'exists:Employee,EmployeeID'],
            'DocumentTypeID' => ['required', 'integer', 'exists:DocumentType,DocumentTypeID'],
            'file' => ['required', 'file', 'max:10240'],
            'Description' => ['nullable', 'string'],
            'ExpiryDate' => ['nullable', 'date'],
        ]);

        $file = $request->file('file');
        $path = $file->storeAs(
            'hrms-documents',
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            config('filesystems.default', 'local'),
        );

        $document = AdditionalDocuments::create([
            'EmployeeID' => $data['EmployeeID'],
            'DocumentTypeID' => $data['DocumentTypeID'],
            'Document' => $path,
            'Description' => $data['Description'] ?? $file->getClientOriginalName(),
            'ExpiryDate' => $data['ExpiryDate'] ?? null,
            'UploadDate' => Carbon::now(),
            'UploadedBy' => $request->user()?->EmployeeID,
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document' => $document,
            'url' => Storage::disk(config('filesystems.default', 'local'))->url($path),
        ], 201);
    }
}
