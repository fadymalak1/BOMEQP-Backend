<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\ACCMaterial;
use App\Models\TrainingCenterPurchase;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class MarketplaceController extends Controller
{
    #[OA\Get(
        path: "/training-center/marketplace/materials",
        summary: "List marketplace materials",
        description: "Get all active materials available in the marketplace with optional filtering.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "acc_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "material_type", in: "query", schema: new OA\Schema(type: "string"), example: "book"),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"), example: "fire safety")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Materials retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "materials", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function materials(Request $request)
    {
        $query = ACCMaterial::where('status', 'active')
            ->with('acc');

        if ($request->has('acc_id')) {
            $query->where('acc_id', $request->acc_id);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('material_type')) {
            $query->where('material_type', $request->material_type);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $materials = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['materials' => $materials]);
    }

    #[OA\Get(
        path: "/training-center/marketplace/materials/{id}",
        summary: "Get material details",
        description: "Get detailed information about a specific marketplace material.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Material retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "material", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Material not found")
        ]
    )]
    public function showMaterial($id)
    {
        $material = ACCMaterial::with('acc', 'course')->findOrFail($id);
        return response()->json(['material' => $material]);
    }

    #[OA\Post(
        path: "/training-center/marketplace/purchase",
        summary: "Purchase marketplace item",
        description: "Purchase a material, course, or package from the marketplace.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["purchase_type", "item_id", "acc_id", "payment_method"],
                properties: [
                    new OA\Property(property: "purchase_type", type: "string", enum: ["material", "course", "package"], example: "material"),
                    new OA\Property(property: "item_id", type: "integer", example: 1),
                    new OA\Property(property: "acc_id", type: "integer", example: 1),
                    new OA\Property(property: "payment_method", type: "string", enum: ["credit_card"], example: "credit_card")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Purchase completed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Purchase completed successfully"),
                        new OA\Property(property: "purchase", type: "object"),
                        new OA\Property(property: "transaction", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Purchase type not implemented or invalid request"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center or item not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function purchase(Request $request)
    {
        $request->validate([
            'purchase_type' => 'required|in:material,course,package',
            'item_id' => 'required|integer',
            'acc_id' => 'required|exists:accs,id',
            'payment_method' => 'required|in:credit_card',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Get item based on purchase type
        if ($request->purchase_type === 'material') {
            $item = ACCMaterial::findOrFail($request->item_id);
            $amount = $item->price;
        } else {
            return response()->json(['message' => 'Purchase type not implemented yet'], 400);
        }

        DB::beginTransaction();
        try {
            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'material_purchase',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $request->acc_id,
                'amount' => $amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Create purchase record
            $purchase = TrainingCenterPurchase::create([
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $request->acc_id,
                'purchase_type' => $request->purchase_type,
                'item_id' => $request->item_id,
                'amount' => $amount,
                'group_commission_percentage' => 0, // TODO: Calculate based on referral
                'group_commission_amount' => 0,
                'transaction_id' => $transaction->id,
                'purchased_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Purchase successful',
                'purchase' => $purchase,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Purchase failed: ' . $e->getMessage()], 500);
        }
    }

    #[OA\Get(
        path: "/training-center/marketplace/library",
        summary: "Get purchased items library",
        description: "Get all items purchased by the training center from the marketplace.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "purchase_type", in: "query", schema: new OA\Schema(type: "string", enum: ["material", "course", "package"]), example: "material"),
            new OA\Parameter(name: "acc_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Library items retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "library", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function library(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $purchases = TrainingCenterPurchase::where('training_center_id', $trainingCenter->id)
            ->with(['acc'])
            ->get()
            ->map(function ($purchase) {
                $item = null;
                if ($purchase->purchase_type === 'material') {
                    $item = ACCMaterial::find($purchase->item_id);
                }

                return [
                    'id' => $purchase->id,
                    'name' => $item?->name ?? 'Unknown',
                    'purchase_type' => $purchase->purchase_type,
                    'purchased_at' => $purchase->purchased_at,
                    'file_url' => $item?->file_url,
                ];
            });

        return response()->json(['library' => $purchases]);
    }
}

