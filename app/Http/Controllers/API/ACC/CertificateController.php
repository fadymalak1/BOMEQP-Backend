<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CertificateController extends Controller
{
    #[OA\Get(
        path: "/acc/certificates",
        summary: "List ACC certificates",
        description: "Get all certificates issued for courses belonging to the authenticated ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by trainee name, certificate number, verification code, course name, or training center name"),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["valid", "expired", "revoked"]), example: "valid"),
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["instructor", "trainee"]), example: "trainee", description: "Filter by certificate type: instructor or trainee"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Certificates retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 50)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $query = Certificate::whereHas('course', function ($q) use ($acc) {
            $q->where('acc_id', $acc->id);
        })->with(['course', 'trainingCenter', 'instructor']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by type (instructor or trainee)
        if ($request->has('type') && in_array($request->type, ['instructor', 'trainee'])) {
            $type = $request->type;
            if ($type === 'instructor') {
                // Instructor certificates: instructor_id is set AND trainee_name matches instructor's name
                $query->whereNotNull('instructor_id')
                    ->whereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('instructors')
                            ->whereColumn('instructors.id', 'certificates.instructor_id')
                            ->whereRaw("LOWER(TRIM(CONCAT(COALESCE(instructors.first_name, ''), ' ', COALESCE(instructors.last_name, '')))) = LOWER(TRIM(certificates.trainee_name))");
                    });
            } else {
                // Trainee certificates: trainee_name doesn't match instructor's name OR instructor_id is null
                $query->where(function ($q) {
                    $q->whereNull('instructor_id')
                        ->orWhereDoesntHave('instructor')
                        ->orWhereNotExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('instructors')
                                ->whereColumn('instructors.id', 'certificates.instructor_id')
                                ->whereRaw("LOWER(TRIM(CONCAT(COALESCE(instructors.first_name, ''), ' ', COALESCE(instructors.last_name, '')))) = LOWER(TRIM(certificates.trainee_name))");
                        });
                });
            }
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('trainee_name', 'like', "%{$searchTerm}%")
                    ->orWhere('certificate_number', 'like', "%{$searchTerm}%")
                    ->orWhere('verification_code', 'like', "%{$searchTerm}%")
                    ->orWhereHas('course', function ($courseQuery) use ($searchTerm) {
                        $courseQuery->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
                        $tcQuery->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $certificates = $query->paginate($perPage);

        // Transform certificates data
        $transformedCertificates = $certificates->getCollection()->map(function ($certificate) {
            $data = $certificate->toArray();
            
            // Change trainee_name to name
            if (isset($data['trainee_name'])) {
                $data['name'] = $data['trainee_name'];
                unset($data['trainee_name']);
            }
            
            // Determine type: instructor or trainee
            // Instructor certificates: instructor_id is set AND trainee_name matches instructor's name
            // Trainee certificates: instructor_id might be set (teacher) but trainee_name doesn't match instructor's name
            $isInstructorCertificate = false;
            if ($certificate->instructor_id) {
                // Ensure instructor relationship is loaded
                if (!$certificate->relationLoaded('instructor')) {
                    $certificate->load('instructor');
                }
                
                if ($certificate->instructor) {
                    $instructorFullName = trim(($certificate->instructor->first_name ?? '') . ' ' . ($certificate->instructor->last_name ?? ''));
                    $traineeName = trim($certificate->trainee_name ?? '');
                    
                    // Normalize both names for comparison (remove extra spaces, lowercase)
                    $normalizedInstructorName = preg_replace('/\s+/', ' ', strtolower(trim($instructorFullName)));
                    $normalizedTraineeName = preg_replace('/\s+/', ' ', strtolower(trim($traineeName)));
                    
                    // Check if trainee_name matches instructor's name (certificate is FOR the instructor)
                    if (!empty($normalizedInstructorName) && $normalizedTraineeName === $normalizedInstructorName) {
                        $isInstructorCertificate = true;
                    }
                }
            }
            
            $data['type'] = $isInstructorCertificate ? 'instructor' : 'trainee';
            
            return $data;
        });

        // Replace the collection in paginator
        $certificates->setCollection($transformedCertificates);

        return response()->json($certificates);
    }
}

