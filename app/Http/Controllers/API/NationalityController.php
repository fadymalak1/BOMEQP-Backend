<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Nationality;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NationalityController extends Controller
{
    #[OA\Get(
        path: "/nationalities",
        summary: "Get all nationalities",
        description: "Get a list of all active nationalities ordered by sort order and name. This endpoint is public and can be used anywhere nationality selection is needed.",
        tags: ["Nationalities"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Nationalities retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "nationalities",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "name", type: "string", example: "Egyptian"),
                                    new OA\Property(property: "code", type: "string", nullable: true, example: "EGY"),
                                    new OA\Property(property: "sort_order", type: "integer", example: 1)
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $nationalities = Nationality::active()
            ->ordered()
            ->get(['id', 'name', 'code', 'sort_order']);

        return response()->json([
            'nationalities' => $nationalities
        ]);
    }
}

