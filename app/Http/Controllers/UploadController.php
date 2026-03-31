<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Log;

class UploadController extends Controller
{
    /**
     * Upload an employee document to Backblaze B2.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadDocument(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'employee_id' => 'required',
                'document_type' => 'required|in:national_id,academic_certificate,good_conduct,employment_contract,security_licence',
                'file' => 'required|file|max:10240', // Max 10MB
                'expiry_date' => 'nullable|date',
            ]);

            // Get the file
            $file = $request->file('file');

            // Generate a unique filename and path
            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $filePath = 'employee_documents/' . $fileName;

            // Upload to Backblaze B2
            $uploaded = Storage::disk('b2')->put($filePath, file_get_contents($file));

            if (!$uploaded) {
                Log::error('Failed to upload file to Backblaze B2.', ['file' => $fileName]);
                return response()->json(['error' => 'Failed to upload file.'], 500);
            }

            // Return success response
            return response()->json([
                'message' => 'Document uploaded successfully!',
                'file' => $fileName,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Document upload failed: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred during upload.'], 500);
        }
    }
}