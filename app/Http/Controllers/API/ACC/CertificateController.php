<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
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

        $perPage = $request->get('per_page', 15);
        $certificates = $query->paginate($perPage);

        return response()->json($certificates);
    }
}

