<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get instructor profile
     * 
     * Get the authenticated instructor's profile information including personal details and training center.
     * 
     * @group Instructor Profile
     * @authenticated
     * 
     * @response 200 {
     *   "profile": {
     *     "id": 1,
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email": "john@example.com",
     *     "phone": "+1234567890",
     *     "id_number": "ID123456",
     *     "cv_url": "/api/storage/instructors/cv/cv.pdf",
     *     "certificates": [...],
     *     "specializations": ["Fire Safety", "First Aid"],
     *     "status": "active",
     *     "training_center": {
     *       "id": 1,
     *       "name": "ABC Training Center"
     *     },
     *     "user": {
     *       "id": 5,
     *       "name": "John Doe",
     *       "email": "john@example.com",
     *       "role": "instructor",
     *       "status": "active"
     *     }
     *   }
     * }
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)
            ->with(['trainingCenter:id,name,email,phone,country,city'])
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $userAccount = User::where('email', $user->email)->first();

        return response()->json([
            'profile' => [
                'id' => $instructor->id,
                'first_name' => $instructor->first_name,
                'last_name' => $instructor->last_name,
                'full_name' => $instructor->first_name . ' ' . $instructor->last_name,
                'email' => $instructor->email,
                'phone' => $instructor->phone,
                'id_number' => $instructor->id_number,
                'cv_url' => $instructor->cv_url,
                'certificates' => $instructor->certificates_json ?? [],
                'specializations' => $instructor->specializations ?? [],
                'status' => $instructor->status,
                'training_center' => $instructor->trainingCenter,
                'user' => $userAccount ? [
                    'id' => $userAccount->id,
                    'name' => $userAccount->name,
                    'email' => $userAccount->email,
                    'role' => $userAccount->role,
                    'status' => $userAccount->status,
                ] : null,
            ]
        ]);
    }

    /**
     * Update instructor profile
     * 
     * Update the authenticated instructor's profile information.
     * 
     * @group Instructor Profile
     * @authenticated
     * 
     * @bodyParam first_name string optional Instructor's first name. Example: John
     * @bodyParam last_name string optional Instructor's last name. Example: Doe
     * @bodyParam phone string optional Phone number. Example: +1234567890
     * @bodyParam cv file optional CV file (PDF, max 10MB). Example: (file)
     * @bodyParam certificates_json array optional Certificates array. Example: [{"name": "Fire Safety", "issuer": "ABC", "expiry": "2025-12-31"}]
     * @bodyParam specializations array optional Specializations array. Example: ["Fire Safety", "First Aid"]
     * 
     * @response 200 {
     *   "message": "Profile updated successfully",
     *   "profile": {...}
     * }
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string',
            'cv' => 'nullable|file|mimes:pdf|max:10240',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $updateData = $request->only([
            'first_name', 'last_name', 'phone', 'specializations'
        ]);

        // Handle CV file upload
        if ($request->hasFile('cv')) {
            // Delete old CV file if exists
            if ($instructor->cv_url) {
                $urlParts = parse_url($instructor->cv_url);
                $path = ltrim($urlParts['path'] ?? '', '/');
                if (preg_match('#instructors/cv/(.+)$#', $path, $matches)) {
                    $oldFileName = $matches[1];
                    $oldFilePath = 'instructors/cv/' . $oldFileName;
                    if (Storage::disk('public')->exists($oldFilePath)) {
                        Storage::disk('public')->delete($oldFilePath);
                    }
                }
            }

            // Upload new CV file
            $cvFile = $request->file('cv');
            $fileName = time() . '_' . $instructor->training_center_id . '_' . $cvFile->getClientOriginalName();
            $cvPath = $cvFile->storeAs('instructors/cv', $fileName, 'public');
            $updateData['cv_url'] = url('/api/storage/instructors/cv/' . $fileName);
        }

        if ($request->has('certificates_json') || $request->has('certificates')) {
            $updateData['certificates_json'] = $request->certificates_json ?? $request->certificates;
        }

        $instructor->update($updateData);

        // Update user account name if first_name or last_name changed
        if ($request->has('first_name') || $request->has('last_name')) {
            $userAccount = User::where('email', $user->email)->first();
            if ($userAccount) {
                $fullName = ($request->first_name ?? $instructor->first_name) . ' ' . ($request->last_name ?? $instructor->last_name);
                $userAccount->update(['name' => $fullName]);
            }
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $instructor->fresh()->load('trainingCenter:id,name,email,phone,country,city')
        ]);
    }
}

