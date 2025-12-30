<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CountryController extends Controller
{
    #[OA\Get(
        path: "/countries",
        summary: "Get list of countries",
        description: "Get a list of all countries with their ISO codes and names.",
        tags: ["Countries & Cities"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Countries retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "countries",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "code", type: "string", example: "EG"),
                                    new OA\Property(property: "name", type: "string", example: "Egypt")
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
        $countries = $this->getCountries();
        
        return response()->json([
            'countries' => $countries
        ]);
    }

    /**
     * Get list of countries
     * Using ISO 3166-1 alpha-2 country codes
     */
    private function getCountries(): array
    {
        return [
            ['code' => 'AF', 'name' => 'Afghanistan'],
            ['code' => 'AL', 'name' => 'Albania'],
            ['code' => 'DZ', 'name' => 'Algeria'],
            ['code' => 'AR', 'name' => 'Argentina'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'AT', 'name' => 'Austria'],
            ['code' => 'BH', 'name' => 'Bahrain'],
            ['code' => 'BD', 'name' => 'Bangladesh'],
            ['code' => 'BE', 'name' => 'Belgium'],
            ['code' => 'BR', 'name' => 'Brazil'],
            ['code' => 'BG', 'name' => 'Bulgaria'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'CO', 'name' => 'Colombia'],
            ['code' => 'HR', 'name' => 'Croatia'],
            ['code' => 'CZ', 'name' => 'Czech Republic'],
            ['code' => 'DK', 'name' => 'Denmark'],
            ['code' => 'EG', 'name' => 'Egypt'],
            ['code' => 'FI', 'name' => 'Finland'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'GR', 'name' => 'Greece'],
            ['code' => 'HK', 'name' => 'Hong Kong'],
            ['code' => 'HU', 'name' => 'Hungary'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'ID', 'name' => 'Indonesia'],
            ['code' => 'IE', 'name' => 'Ireland'],
            ['code' => 'IT', 'name' => 'Italy'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'JO', 'name' => 'Jordan'],
            ['code' => 'KW', 'name' => 'Kuwait'],
            ['code' => 'LB', 'name' => 'Lebanon'],
            ['code' => 'MY', 'name' => 'Malaysia'],
            ['code' => 'MX', 'name' => 'Mexico'],
            ['code' => 'MA', 'name' => 'Morocco'],
            ['code' => 'NL', 'name' => 'Netherlands'],
            ['code' => 'NZ', 'name' => 'New Zealand'],
            ['code' => 'NG', 'name' => 'Nigeria'],
            ['code' => 'NO', 'name' => 'Norway'],
            ['code' => 'OM', 'name' => 'Oman'],
            ['code' => 'PK', 'name' => 'Pakistan'],
            ['code' => 'PH', 'name' => 'Philippines'],
            ['code' => 'PL', 'name' => 'Poland'],
            ['code' => 'PT', 'name' => 'Portugal'],
            ['code' => 'QA', 'name' => 'Qatar'],
            ['code' => 'RO', 'name' => 'Romania'],
            ['code' => 'RU', 'name' => 'Russia'],
            ['code' => 'SA', 'name' => 'Saudi Arabia'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'ZA', 'name' => 'South Africa'],
            ['code' => 'KR', 'name' => 'South Korea'],
            ['code' => 'ES', 'name' => 'Spain'],
            ['code' => 'SE', 'name' => 'Sweden'],
            ['code' => 'CH', 'name' => 'Switzerland'],
            ['code' => 'TW', 'name' => 'Taiwan'],
            ['code' => 'TH', 'name' => 'Thailand'],
            ['code' => 'TR', 'name' => 'Turkey'],
            ['code' => 'AE', 'name' => 'United Arab Emirates'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'VN', 'name' => 'Vietnam'],
        ];
    }
}

