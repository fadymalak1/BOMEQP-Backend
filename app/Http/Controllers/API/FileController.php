<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    /**
     * Serve instructor CV file
     * GET /api/storage/instructors/cv/{filename}
     */
    public function instructorCv(Request $request, string $filename)
    {
        $filePath = 'instructors/cv/' . $filename;
        
        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'message' => 'File not found'
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($filePath);
        
        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Serve any file from public storage
     * GET /api/storage/{path}
     */
    public function serveFile(Request $request, string $path)
    {
        // Security: Only allow certain paths
        $allowedPaths = ['authorization', 'documents'];
        $pathParts = explode('/', $path);
        
        // Don't allow instructors/cv through this route (use specific route instead)
        if (strpos($path, 'instructors/cv') === 0) {
            return response()->json([
                'message' => 'Use /api/storage/instructors/cv/{filename} endpoint'
            ], 400);
        }
        
        if (empty($pathParts) || !in_array($pathParts[0], $allowedPaths)) {
            return response()->json([
                'message' => 'Access denied'
            ], 403);
        }

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'message' => 'File not found'
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($path);
        $mimeType = Storage::disk('public')->mimeType($path) ?? 'application/octet-stream';
        
        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
        ]);
    }
}

