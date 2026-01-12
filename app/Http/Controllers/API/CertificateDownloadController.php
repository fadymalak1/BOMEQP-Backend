<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateDownloadController extends Controller
{
    /**
     * Download certificate PDF by filename
     * This handles direct links like /certificates/7Fw4OM3V8TVDrNboGQu1.pdf
     */
    public function download($filename)
    {
        // Extract verification code or certificate number from filename
        // Filename format: {random}.pdf
        $filePath = 'certificates/' . $filename;
        
        // Check if file exists
        if (!Storage::disk('public')->exists($filePath)) {
            // Try to find certificate by verification code or filename
            $code = str_replace('.pdf', '', $filename);
            
            // Try to find by verification code
            $certificate = Certificate::where('verification_code', $code)->first();
            
            if (!$certificate) {
                // Try to find by certificate_pdf_url
                $certificate = Certificate::where('certificate_pdf_url', 'like', '%' . $filename)->first();
            }
            
            if ($certificate && $certificate->certificate_pdf_url) {
                // Extract path from URL
                $url = $certificate->certificate_pdf_url;
                $path = str_replace(Storage::disk('public')->url(''), '', $url);
                
                if (Storage::disk('public')->exists($path)) {
                    return Storage::disk('public')->response($path, basename($path), [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="certificate-' . $certificate->certificate_number . '.pdf"',
                    ]);
                }
            }
            
            return response()->json(['message' => 'Certificate PDF not found'], 404);
        }
        
        // Return file
        return Storage::disk('public')->response($filePath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}

