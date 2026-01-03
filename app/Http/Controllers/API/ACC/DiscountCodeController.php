<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class DiscountCodeController extends Controller
{
    #[OA\Get(
        path: "/acc/discount-codes",
        summary: "List discount codes",
        description: "Get all discount codes created by the authenticated ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Discount codes retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "discount_codes", type: "array", items: new OA\Items(type: "object"))
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

        $discountCodes = DiscountCode::where('acc_id', $acc->id)->get();
        return response()->json(['discount_codes' => $discountCodes]);
    }

    #[OA\Get(
        path: "/acc/{id}/discount-codes",
        summary: "Get discount codes by ACC ID",
        description: "Get all active discount codes for a specific ACC. Can be used by training centers or admins to view available discount codes for an ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 6)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Discount codes retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "discount_codes", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function getByAccId($id)
    {
        $acc = ACC::find($id);

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Only return active discount codes for public viewing
        $discountCodes = DiscountCode::where('acc_id', $acc->id)
            ->where('status', 'active')
            ->get();

        return response()->json(['discount_codes' => $discountCodes]);
    }

    #[OA\Post(
        path: "/acc/discount-codes",
        summary: "Create discount code",
        description: "Create a new discount code. Supports time-limited or quantity-based discount types.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["code", "discount_type", "discount_percentage", "status"],
                properties: [
                    new OA\Property(property: "code", type: "string", example: "DISCOUNT10"),
                    new OA\Property(property: "discount_type", type: "string", enum: ["time_limited", "quantity_based"], example: "time_limited"),
                    new OA\Property(property: "discount_percentage", type: "number", format: "float", example: 10.0, minimum: 0, maximum: 100),
                    new OA\Property(property: "applicable_course_ids", type: "array", nullable: true, items: new OA\Items(type: "integer"), example: [1, 2]),
                    new OA\Property(property: "start_date", type: "string", format: "date", nullable: true, example: "2024-01-01", description: "Required for time_limited type"),
                    new OA\Property(property: "end_date", type: "string", format: "date", nullable: true, example: "2024-12-31", description: "Required for time_limited type, must be after start_date"),
                    new OA\Property(property: "total_quantity", type: "integer", nullable: true, example: 100, minimum: 1, description: "Required for quantity_based type"),
                    new OA\Property(property: "status", type: "string", enum: ["active", "expired", "depleted", "inactive"], example: "active")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Discount code created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "discount_code", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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

    #[OA\Get(
        path: "/acc/discount-codes/{id}",
        summary: "Get discount code details",
        description: "Get detailed information about a specific discount code.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Discount code retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "discount_code", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Discount code not found")
        ]
    )]
    public function show($id)
    {
        $discountCode = DiscountCode::findOrFail($id);
        return response()->json(['discount_code' => $discountCode]);
    }

    #[OA\Put(
        path: "/acc/discount-codes/{id}",
        summary: "Update discount code",
        description: "Update discount code information. Only codes created by the ACC can be updated.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "code", type: "string", nullable: true),
                    new OA\Property(property: "discount_type", type: "string", enum: ["time_limited", "quantity_based"], nullable: true),
                    new OA\Property(property: "discount_percentage", type: "number", format: "float", nullable: true, minimum: 0, maximum: 100),
                    new OA\Property(property: "applicable_course_ids", type: "array", nullable: true, items: new OA\Items(type: "integer")),
                    new OA\Property(property: "start_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "end_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "total_quantity", type: "integer", nullable: true, minimum: 1),
                    new OA\Property(property: "status", type: "string", enum: ["active", "expired", "depleted", "inactive"], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Discount code updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Discount code updated successfully"),
                        new OA\Property(property: "discount_code", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Discount code not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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

    #[OA\Delete(
        path: "/acc/discount-codes/{id}",
        summary: "Delete discount code",
        description: "Delete a discount code. Only codes created by the ACC can be deleted.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Discount code deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Discount code deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Discount code not found")
        ]
    )]
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

    #[OA\Post(
        path: "/acc/discount-codes/validate",
        summary: "Validate discount code",
        description: "Validate a discount code for a specific course. Checks if code is active, valid dates, and applicable to the course.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["code", "course_id"],
                properties: [
                    new OA\Property(property: "code", type: "string", example: "DISCOUNT10"),
                    new OA\Property(property: "course_id", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Discount code is valid",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "valid", type: "boolean", example: true),
                        new OA\Property(property: "discount_code", type: "object"),
                        new OA\Property(property: "discount_percentage", type: "number", example: 10.0)
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Discount code is invalid",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "valid", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Discount code is not active")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Discount code not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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

