<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CityController extends Controller
{
    #[OA\Get(
        path: "/cities",
        summary: "Get list of cities",
        description: "Get a list of cities, optionally filtered by country code.",
        tags: ["Countries & Cities"],
        parameters: [
            new OA\Parameter(
                name: "country",
                in: "query",
                required: false,
                description: "Filter cities by country code (ISO 3166-1 alpha-2)",
                schema: new OA\Schema(type: "string", example: "EG")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Cities retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "cities",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "name", type: "string", example: "Cairo"),
                                    new OA\Property(property: "country_code", type: "string", example: "EG")
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
        $countryCode = $request->query('country');
        $cities = $this->getCities($countryCode);
        
        return response()->json([
            'cities' => $cities
        ]);
    }

    /**
     * Get list of cities
     * Optionally filtered by country code
     */
    private function getCities(?string $countryCode = null): array
    {
        $allCities = [
            // Egypt
            ['name' => 'Cairo', 'country_code' => 'EG'],
            ['name' => 'Alexandria', 'country_code' => 'EG'],
            ['name' => 'Giza', 'country_code' => 'EG'],
            ['name' => 'Shubra El Kheima', 'country_code' => 'EG'],
            ['name' => 'Port Said', 'country_code' => 'EG'],
            ['name' => 'Suez', 'country_code' => 'EG'],
            ['name' => 'Luxor', 'country_code' => 'EG'],
            ['name' => 'Aswan', 'country_code' => 'EG'],
            ['name' => 'Mansoura', 'country_code' => 'EG'],
            ['name' => 'Tanta', 'country_code' => 'EG'],
            ['name' => 'Ismailia', 'country_code' => 'EG'],
            ['name' => 'Zagazig', 'country_code' => 'EG'],
            
            // Saudi Arabia
            ['name' => 'Riyadh', 'country_code' => 'SA'],
            ['name' => 'Jeddah', 'country_code' => 'SA'],
            ['name' => 'Mecca', 'country_code' => 'SA'],
            ['name' => 'Medina', 'country_code' => 'SA'],
            ['name' => 'Dammam', 'country_code' => 'SA'],
            ['name' => 'Khobar', 'country_code' => 'SA'],
            ['name' => 'Abha', 'country_code' => 'SA'],
            ['name' => 'Tabuk', 'country_code' => 'SA'],
            
            // UAE
            ['name' => 'Dubai', 'country_code' => 'AE'],
            ['name' => 'Abu Dhabi', 'country_code' => 'AE'],
            ['name' => 'Sharjah', 'country_code' => 'AE'],
            ['name' => 'Al Ain', 'country_code' => 'AE'],
            ['name' => 'Ajman', 'country_code' => 'AE'],
            ['name' => 'Ras Al Khaimah', 'country_code' => 'AE'],
            ['name' => 'Fujairah', 'country_code' => 'AE'],
            
            // Kuwait
            ['name' => 'Kuwait City', 'country_code' => 'KW'],
            ['name' => 'Al Ahmadi', 'country_code' => 'KW'],
            ['name' => 'Hawalli', 'country_code' => 'KW'],
            ['name' => 'Al Jahra', 'country_code' => 'KW'],
            
            // Qatar
            ['name' => 'Doha', 'country_code' => 'QA'],
            ['name' => 'Al Rayyan', 'country_code' => 'QA'],
            ['name' => 'Al Wakrah', 'country_code' => 'QA'],
            ['name' => 'Al Khor', 'country_code' => 'QA'],
            
            // Bahrain
            ['name' => 'Manama', 'country_code' => 'BH'],
            ['name' => 'Riffa', 'country_code' => 'BH'],
            ['name' => 'Muharraq', 'country_code' => 'BH'],
            ['name' => 'Hamad Town', 'country_code' => 'BH'],
            
            // Oman
            ['name' => 'Muscat', 'country_code' => 'OM'],
            ['name' => 'Salalah', 'country_code' => 'OM'],
            ['name' => 'Sohar', 'country_code' => 'OM'],
            ['name' => 'Nizwa', 'country_code' => 'OM'],
            
            // Jordan
            ['name' => 'Amman', 'country_code' => 'JO'],
            ['name' => 'Irbid', 'country_code' => 'JO'],
            ['name' => 'Zarqa', 'country_code' => 'JO'],
            ['name' => 'Aqaba', 'country_code' => 'JO'],
            
            // Lebanon
            ['name' => 'Beirut', 'country_code' => 'LB'],
            ['name' => 'Tripoli', 'country_code' => 'LB'],
            ['name' => 'Sidon', 'country_code' => 'LB'],
            ['name' => 'Tyre', 'country_code' => 'LB'],
            
            // United States
            ['name' => 'New York', 'country_code' => 'US'],
            ['name' => 'Los Angeles', 'country_code' => 'US'],
            ['name' => 'Chicago', 'country_code' => 'US'],
            ['name' => 'Houston', 'country_code' => 'US'],
            ['name' => 'Phoenix', 'country_code' => 'US'],
            ['name' => 'Philadelphia', 'country_code' => 'US'],
            ['name' => 'San Antonio', 'country_code' => 'US'],
            ['name' => 'San Diego', 'country_code' => 'US'],
            ['name' => 'Dallas', 'country_code' => 'US'],
            ['name' => 'San Jose', 'country_code' => 'US'],
            
            // United Kingdom
            ['name' => 'London', 'country_code' => 'GB'],
            ['name' => 'Manchester', 'country_code' => 'GB'],
            ['name' => 'Birmingham', 'country_code' => 'GB'],
            ['name' => 'Liverpool', 'country_code' => 'GB'],
            ['name' => 'Leeds', 'country_code' => 'GB'],
            ['name' => 'Glasgow', 'country_code' => 'GB'],
            ['name' => 'Edinburgh', 'country_code' => 'GB'],
            ['name' => 'Bristol', 'country_code' => 'GB'],
            
            // Canada
            ['name' => 'Toronto', 'country_code' => 'CA'],
            ['name' => 'Vancouver', 'country_code' => 'CA'],
            ['name' => 'Montreal', 'country_code' => 'CA'],
            ['name' => 'Calgary', 'country_code' => 'CA'],
            ['name' => 'Ottawa', 'country_code' => 'CA'],
            ['name' => 'Edmonton', 'country_code' => 'CA'],
            
            // France
            ['name' => 'Paris', 'country_code' => 'FR'],
            ['name' => 'Lyon', 'country_code' => 'FR'],
            ['name' => 'Marseille', 'country_code' => 'FR'],
            ['name' => 'Toulouse', 'country_code' => 'FR'],
            ['name' => 'Nice', 'country_code' => 'FR'],
            
            // Germany
            ['name' => 'Berlin', 'country_code' => 'DE'],
            ['name' => 'Munich', 'country_code' => 'DE'],
            ['name' => 'Hamburg', 'country_code' => 'DE'],
            ['name' => 'Frankfurt', 'country_code' => 'DE'],
            ['name' => 'Cologne', 'country_code' => 'DE'],
            
            // India
            ['name' => 'Mumbai', 'country_code' => 'IN'],
            ['name' => 'Delhi', 'country_code' => 'IN'],
            ['name' => 'Bangalore', 'country_code' => 'IN'],
            ['name' => 'Hyderabad', 'country_code' => 'IN'],
            ['name' => 'Chennai', 'country_code' => 'IN'],
            ['name' => 'Kolkata', 'country_code' => 'IN'],
            ['name' => 'Pune', 'country_code' => 'IN'],
            
            // China
            ['name' => 'Beijing', 'country_code' => 'CN'],
            ['name' => 'Shanghai', 'country_code' => 'CN'],
            ['name' => 'Guangzhou', 'country_code' => 'CN'],
            ['name' => 'Shenzhen', 'country_code' => 'CN'],
            ['name' => 'Chengdu', 'country_code' => 'CN'],
            
            // Australia
            ['name' => 'Sydney', 'country_code' => 'AU'],
            ['name' => 'Melbourne', 'country_code' => 'AU'],
            ['name' => 'Brisbane', 'country_code' => 'AU'],
            ['name' => 'Perth', 'country_code' => 'AU'],
            ['name' => 'Adelaide', 'country_code' => 'AU'],
        ];

        // Filter by country if provided
        if ($countryCode) {
            return array_filter($allCities, function($city) use ($countryCode) {
                return strtoupper($city['country_code']) === strtoupper($countryCode);
            });
        }

        return $allCities;
    }
}

