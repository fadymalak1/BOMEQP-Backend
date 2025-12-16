<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\TrainingClass;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $classes = TrainingClass::whereHas('course', function ($q) use ($acc) {
            $q->where('acc_id', $acc->id);
        })
        ->with(['course', 'trainingCenter', 'instructor'])
        ->get();

        return response()->json(['classes' => $classes]);
    }
}

