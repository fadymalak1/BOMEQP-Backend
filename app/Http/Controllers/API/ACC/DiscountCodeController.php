<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DiscountCodeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $discountCodes = DiscountCode::where('acc_id', $acc->id)->get();
        return response()->json(['discount_codes' => $discountCodes]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $request->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($acc) {
                    $exists = DiscountCode::where('acc_id', $acc->id)
                        ->where('code', $value)
                        ->exists();
                    if ($exists) {
                        $fail('The discount code already exists for this ACC.');
                    }
                },
            ],
            'discount_type' => 'required|in:time_limited,quantity_based',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'applicable_course_ids' => 'nullable|array',
            'start_date' => 'required_if:discount_type,time_limited|nullable|date',
            'end_date' => 'required_if:discount_type,time_limited|nullable|date|after:start_date',
            'total_quantity' => 'required_if:discount_type,quantity_based|nullable|integer|min:1',
            'status' => 'required|in:active,expired,depleted,inactive',
        ]);

        $discountCode = DiscountCode::create([
            'acc_id' => $acc->id,
            'code' => $request->code,
            'discount_type' => $request->discount_type,
            'discount_percentage' => $request->discount_percentage,
            'applicable_course_ids' => $request->applicable_course_ids,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_quantity' => $request->total_quantity,
            'status' => $request->status,
        ]);

        return response()->json(['discount_code' => $discountCode], 201);
    }

    public function show($id)
    {
        $discountCode = DiscountCode::findOrFail($id);
        return response()->json(['discount_code' => $discountCode]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $discountCode = DiscountCode::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'code' => [
                'sometimes',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($acc, $id) {
                    $exists = DiscountCode::where('acc_id', $acc->id)
                        ->where('code', $value)
                        ->where('id', '!=', $id)
                        ->exists();
                    if ($exists) {
                        $fail('The discount code already exists for this ACC.');
                    }
                },
            ],
            'discount_type' => 'sometimes|in:time_limited,quantity_based',
            'discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'applicable_course_ids' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'total_quantity' => 'nullable|integer|min:1',
            'status' => 'sometimes|in:active,expired,depleted,inactive',
        ]);

        $discountCode->update($request->only([
            'code', 'discount_type', 'discount_percentage', 'applicable_course_ids',
            'start_date', 'end_date', 'total_quantity', 'status'
        ]));

        return response()->json(['message' => 'Discount code updated successfully', 'discount_code' => $discountCode]);
    }

    public function destroy($id)
    {
        $user = request()->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $discountCode = DiscountCode::where('acc_id', $acc->id)->findOrFail($id);
        $discountCode->delete();

        return response()->json(['message' => 'Discount code deleted successfully']);
    }

    public function validate(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'course_id' => 'required|exists:courses,id',
        ]);

        $discountCode = DiscountCode::where('code', $request->code)->first();

        if (!$discountCode) {
            return response()->json([
                'valid' => false,
                'message' => 'Discount code not found'
            ], 404);
        }

        // Check if code is active
        if ($discountCode->status !== 'active') {
            return response()->json([
                'valid' => false,
                'message' => 'Discount code is not active'
            ]);
        }

        // Check time-limited codes
        if ($discountCode->discount_type === 'time_limited') {
            $now = Carbon::now();
            if ($discountCode->start_date && $now->lt($discountCode->start_date)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Discount code has not started yet'
                ]);
            }
            if ($discountCode->end_date && $now->gt($discountCode->end_date)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Discount code has expired'
                ]);
            }
        }

        // Check quantity-based codes
        if ($discountCode->discount_type === 'quantity_based') {
            if ($discountCode->used_quantity >= $discountCode->total_quantity) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Discount code has been depleted'
                ]);
            }
        }

        // Check if course is applicable
        if ($discountCode->applicable_course_ids && 
            !in_array($request->course_id, $discountCode->applicable_course_ids)) {
            return response()->json([
                'valid' => false,
                'message' => 'Discount code is not applicable to this course'
            ]);
        }

        return response()->json([
            'valid' => true,
            'discount_percentage' => $discountCode->discount_percentage,
            'message' => 'Discount code is valid'
        ]);
    }
}

