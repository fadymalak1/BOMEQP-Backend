<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use OpenApi\Attributes as OA;

class FileController extends Controller
{
    #[OA\Get(
        path: "/storage/instructors/cv/{filename}",
        summary: "Get instructor CV file",
        description: "Serve an instructor CV file. This is a public endpoint.",
        tags: ["Files"],
        parameters: [
            new OA\Parameter(name: "filename", in: "path", required: true, schema: new OA\Schema(type: "string"), example: "cv_1234567890.pdf")
        ],
        responses: [
            new OA\Response(response: 200, description: "File retrieved successfully"),
            new OA\Response(response: 404, description: "File not found")
        ]
    )]
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

    #[OA\Get(
        path: "/storage/instructors/certificates/{filename}",
        summary: "Get instructor certificate file",
        description: "Serve an instructor certificate file. This is a public endpoint.",
        tags: ["Files"],
        parameters: [
            new OA\Parameter(name: "filename", in: "path", required: true, schema: new OA\Schema(type: "string"), example: "cert_1234567890.pdf")
        ],
        responses: [
            new OA\Response(response: 200, description: "File retrieved successfully"),
            new OA\Response(response: 404, description: "File not found")
        ]
    )]
    public function instructorCertificate(Request $request, string $filename)
    {
        $filePath = 'instructors/certificates/' . $filename;
        
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

    #[OA\Get(
        path: "/storage/instructors/photo/{filename}",
        summary: "Get instructor profile image",
        description: "Serve an instructor profile image file. This is a public endpoint.",
        tags: ["Files"],
        parameters: [
            new OA\Parameter(name: "filename", in: "path", required: true, schema: new OA\Schema(type: "string"), example: "photo_1234567890.jpg")
        ],
        responses: [
            new OA\Response(response: 200, description: "File retrieved successfully"),
            new OA\Response(response: 404, description: "File not found")
        ]
    )]
    public function instructorPhoto(Request $request, string $filename)
    {
        $filePath = 'instructors/photo/' . $filename;
        
        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'message' => 'File not found'
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($filePath);
        $mimeType = Storage::disk('public')->mimeType($filePath) ?? 'image/jpeg';
        
        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    #[OA\Get(
        path: "/storage/{path}",
        summary: "Get file from storage",
        description: "Serve a file from public storage. Only authorized paths are allowed.",
        tags: ["Files"],
        parameters: [
            new OA\Parameter(name: "path", in: "path", required: true, schema: new OA\Schema(type: "string"), example: "authorization/document.pdf")
        ],
        responses: [
            new OA\Response(response: 200, description: "File retrieved successfully"),
            new OA\Response(response: 400, description: "Invalid path"),
            new OA\Response(response: 403, description: "Access denied"),
            new OA\Response(response: 404, description: "File not found")
        ]
    )]
    public function serveFile(Request $request, string $path)
    {
        // Security: Only allow certain paths
        $allowedPaths = ['authorization', 'documents', 'accs'];
        $pathParts = explode('/', $path);
        
        // Don't allow instructors/cv, instructors/certificates, or instructors/photo through this route (use specific routes instead)
        if (strpos($path, 'instructors/cv') === 0) {
            return response()->json([
                'message' => 'Use /api/storage/instructors/cv/{filename} endpoint'
            ], 400);
        }
        
        if (strpos($path, 'instructors/certificates') === 0) {
            return response()->json([
                'message' => 'Use /api/storage/instructors/certificates/{filename} endpoint'
            ], 400);
        }
        
        if (strpos($path, 'instructors/photo') === 0) {
            return response()->json([
                'message' => 'Use /api/storage/instructors/photo/{filename} endpoint'
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

