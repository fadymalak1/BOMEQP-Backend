<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingCenter;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TrainingCenterController extends Controller
{
    #[OA\Get(
        path: "/admin/training-centers",
        summary: "Get all training centers",
        description: "Get a list of all training centers with optional filters.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "active", "suspended", "inactive"])),
            new OA\Parameter(name: "country", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Training centers retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "training_centers", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "statistics", type: "object", properties: [
                            new OA\Property(property: "total", type: "integer", example: 100),
                            new OA\Property(property: "pending", type: "integer", example: 10),
                            new OA\Property(property: "active", type: "integer", example: 70),
                            new OA\Property(property: "suspended", type: "integer", example: 10),
                            new OA\Property(property: "inactive", type: "integer", example: 10)
                        ]),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = TrainingCenter::with(['wallet', 'instructors']);

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('legal_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        $trainingCenters = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        // Get statistics (total counts regardless of filters)
        $statistics = [
            'total' => TrainingCenter::count(),
            'pending' => TrainingCenter::where('status', 'pending')->count(),
            'active' => TrainingCenter::where('status', 'active')->count(),
            'suspended' => TrainingCenter::where('status', 'suspended')->count(),
            'inactive' => TrainingCenter::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'training_centers' => $trainingCenters->items(),
            'statistics' => $statistics,
            'pagination' => [
                'current_page' => $trainingCenters->currentPage(),
                'last_page' => $trainingCenters->lastPage(),
                'per_page' => $trainingCenters->perPage(),
                'total' => $trainingCenters->total(),
            ]
        ]);
    }

    /**
     * Maps every country name (as stored in the DB) to its region and default coordinates.
     * Coordinates are country-level centroids used only as a fallback when the TC has no
     * explicit latitude/longitude set.
     */
    private function getCountryData(): array
    {
        return [
            // USA / CANADA
            'United States'              => ['region' => 'USA/CANADA',          'lat' => 37.0902,   'lng' => -95.7129],
            'USA'                        => ['region' => 'USA/CANADA',          'lat' => 37.0902,   'lng' => -95.7129],
            'US'                         => ['region' => 'USA/CANADA',          'lat' => 37.0902,   'lng' => -95.7129],
            'Canada'                     => ['region' => 'USA/CANADA',          'lat' => 56.1304,   'lng' => -106.3468],

            // LATIN AMERICA
            'Mexico'                     => ['region' => 'LATIN AMERICA',       'lat' => 23.6345,   'lng' => -102.5528],
            'Brazil'                     => ['region' => 'LATIN AMERICA',       'lat' => -14.2350,  'lng' => -51.9253],
            'Argentina'                  => ['region' => 'LATIN AMERICA',       'lat' => -38.4161,  'lng' => -63.6167],
            'Colombia'                   => ['region' => 'LATIN AMERICA',       'lat' => 4.5709,    'lng' => -74.2973],
            'Chile'                      => ['region' => 'LATIN AMERICA',       'lat' => -35.6751,  'lng' => -71.5430],
            'Peru'                       => ['region' => 'LATIN AMERICA',       'lat' => -9.1900,   'lng' => -75.0152],
            'Venezuela'                  => ['region' => 'LATIN AMERICA',       'lat' => 6.4238,    'lng' => -66.5897],
            'Ecuador'                    => ['region' => 'LATIN AMERICA',       'lat' => -1.8312,   'lng' => -78.1834],
            'Bolivia'                    => ['region' => 'LATIN AMERICA',       'lat' => -16.2902,  'lng' => -63.5887],
            'Paraguay'                   => ['region' => 'LATIN AMERICA',       'lat' => -23.4425,  'lng' => -58.4438],
            'Uruguay'                    => ['region' => 'LATIN AMERICA',       'lat' => -32.5228,  'lng' => -55.7658],
            'Guatemala'                  => ['region' => 'LATIN AMERICA',       'lat' => 15.7835,   'lng' => -90.2308],
            'Honduras'                   => ['region' => 'LATIN AMERICA',       'lat' => 15.2000,   'lng' => -86.2419],
            'El Salvador'                => ['region' => 'LATIN AMERICA',       'lat' => 13.7942,   'lng' => -88.8965],
            'Nicaragua'                  => ['region' => 'LATIN AMERICA',       'lat' => 12.8654,   'lng' => -85.2072],
            'Costa Rica'                 => ['region' => 'LATIN AMERICA',       'lat' => 9.7489,    'lng' => -83.7534],
            'Panama'                     => ['region' => 'LATIN AMERICA',       'lat' => 8.5380,    'lng' => -80.7821],
            'Cuba'                       => ['region' => 'LATIN AMERICA',       'lat' => 21.5218,   'lng' => -77.7812],
            'Dominican Republic'         => ['region' => 'LATIN AMERICA',       'lat' => 18.7357,   'lng' => -70.1627],
            'Haiti'                      => ['region' => 'LATIN AMERICA',       'lat' => 18.9712,   'lng' => -72.2852],
            'Jamaica'                    => ['region' => 'LATIN AMERICA',       'lat' => 18.1096,   'lng' => -77.2975],
            'Trinidad and Tobago'        => ['region' => 'LATIN AMERICA',       'lat' => 10.6918,   'lng' => -61.2225],

            // EUROPE
            'United Kingdom'             => ['region' => 'EUROPE',              'lat' => 55.3781,   'lng' => -3.4360],
            'UK'                         => ['region' => 'EUROPE',              'lat' => 55.3781,   'lng' => -3.4360],
            'Germany'                    => ['region' => 'EUROPE',              'lat' => 51.1657,   'lng' => 10.4515],
            'France'                     => ['region' => 'EUROPE',              'lat' => 46.2276,   'lng' => 2.2137],
            'Italy'                      => ['region' => 'EUROPE',              'lat' => 41.8719,   'lng' => 12.5674],
            'Spain'                      => ['region' => 'EUROPE',              'lat' => 40.4637,   'lng' => -3.7492],
            'Netherlands'                => ['region' => 'EUROPE',              'lat' => 52.1326,   'lng' => 5.2913],
            'Belgium'                    => ['region' => 'EUROPE',              'lat' => 50.5039,   'lng' => 4.4699],
            'Switzerland'                => ['region' => 'EUROPE',              'lat' => 46.8182,   'lng' => 8.2275],
            'Austria'                    => ['region' => 'EUROPE',              'lat' => 47.5162,   'lng' => 14.5501],
            'Sweden'                     => ['region' => 'EUROPE',              'lat' => 60.1282,   'lng' => 18.6435],
            'Norway'                     => ['region' => 'EUROPE',              'lat' => 60.4720,   'lng' => 8.4689],
            'Denmark'                    => ['region' => 'EUROPE',              'lat' => 56.2639,   'lng' => 9.5018],
            'Finland'                    => ['region' => 'EUROPE',              'lat' => 61.9241,   'lng' => 25.7482],
            'Poland'                     => ['region' => 'EUROPE',              'lat' => 51.9194,   'lng' => 19.1451],
            'Czech Republic'             => ['region' => 'EUROPE',              'lat' => 49.8175,   'lng' => 15.4730],
            'Hungary'                    => ['region' => 'EUROPE',              'lat' => 47.1625,   'lng' => 19.5033],
            'Romania'                    => ['region' => 'EUROPE',              'lat' => 45.9432,   'lng' => 24.9668],
            'Bulgaria'                   => ['region' => 'EUROPE',              'lat' => 42.7339,   'lng' => 25.4858],
            'Greece'                     => ['region' => 'EUROPE',              'lat' => 39.0742,   'lng' => 21.8243],
            'Portugal'                   => ['region' => 'EUROPE',              'lat' => 39.3999,   'lng' => -8.2245],
            'Ireland'                    => ['region' => 'EUROPE',              'lat' => 53.1424,   'lng' => -7.6921],
            'Ukraine'                    => ['region' => 'EUROPE',              'lat' => 48.3794,   'lng' => 31.1656],
            'Russia'                     => ['region' => 'EUROPE',              'lat' => 61.5240,   'lng' => 105.3188],
            'Turkey'                     => ['region' => 'EUROPE',              'lat' => 38.9637,   'lng' => 35.2433],
            'Croatia'                    => ['region' => 'EUROPE',              'lat' => 45.1000,   'lng' => 15.2000],
            'Serbia'                     => ['region' => 'EUROPE',              'lat' => 44.0165,   'lng' => 21.0059],
            'Slovakia'                   => ['region' => 'EUROPE',              'lat' => 48.6690,   'lng' => 19.6990],
            'Luxembourg'                 => ['region' => 'EUROPE',              'lat' => 49.8153,   'lng' => 6.1296],
            'Iceland'                    => ['region' => 'EUROPE',              'lat' => 64.9631,   'lng' => -19.0208],
            'Malta'                      => ['region' => 'EUROPE',              'lat' => 35.9375,   'lng' => 14.3754],
            'Cyprus'                     => ['region' => 'EUROPE',              'lat' => 35.1264,   'lng' => 33.4299],

            // ASIA
            'China'                      => ['region' => 'ASIA',                'lat' => 35.8617,   'lng' => 104.1954],
            'Japan'                      => ['region' => 'ASIA',                'lat' => 36.2048,   'lng' => 138.2529],
            'India'                      => ['region' => 'ASIA',                'lat' => 20.5937,   'lng' => 78.9629],
            'South Korea'                => ['region' => 'ASIA',                'lat' => 35.9078,   'lng' => 127.7669],
            'Korea'                      => ['region' => 'ASIA',                'lat' => 35.9078,   'lng' => 127.7669],
            'Indonesia'                  => ['region' => 'ASIA',                'lat' => -0.7893,   'lng' => 113.9213],
            'Malaysia'                   => ['region' => 'ASIA',                'lat' => 4.2105,    'lng' => 101.9758],
            'Philippines'                => ['region' => 'ASIA',                'lat' => 12.8797,   'lng' => 121.7740],
            'Vietnam'                    => ['region' => 'ASIA',                'lat' => 14.0583,   'lng' => 108.2772],
            'Thailand'                   => ['region' => 'ASIA',                'lat' => 15.8700,   'lng' => 100.9925],
            'Singapore'                  => ['region' => 'ASIA',                'lat' => 1.3521,    'lng' => 103.8198],
            'Bangladesh'                 => ['region' => 'ASIA',                'lat' => 23.6850,   'lng' => 90.3563],
            'Pakistan'                   => ['region' => 'ASIA',                'lat' => 30.3753,   'lng' => 69.3451],
            'Sri Lanka'                  => ['region' => 'ASIA',                'lat' => 7.8731,    'lng' => 80.7718],
            'Nepal'                      => ['region' => 'ASIA',                'lat' => 28.3949,   'lng' => 84.1240],
            'Myanmar'                    => ['region' => 'ASIA',                'lat' => 21.9162,   'lng' => 95.9560],
            'Cambodia'                   => ['region' => 'ASIA',                'lat' => 12.5657,   'lng' => 104.9910],
            'Laos'                       => ['region' => 'ASIA',                'lat' => 19.8563,   'lng' => 102.4955],
            'Mongolia'                   => ['region' => 'ASIA',                'lat' => 46.8625,   'lng' => 103.8467],
            'Kazakhstan'                 => ['region' => 'ASIA',                'lat' => 48.0196,   'lng' => 66.9237],
            'Uzbekistan'                 => ['region' => 'ASIA',                'lat' => 41.3775,   'lng' => 64.5853],
            'Azerbaijan'                 => ['region' => 'ASIA',                'lat' => 40.1431,   'lng' => 47.5769],
            'Georgia'                    => ['region' => 'ASIA',                'lat' => 42.3154,   'lng' => 43.3569],
            'Armenia'                    => ['region' => 'ASIA',                'lat' => 40.0691,   'lng' => 45.0382],
            'Taiwan'                     => ['region' => 'ASIA',                'lat' => 23.6978,   'lng' => 120.9605],
            'Hong Kong'                  => ['region' => 'ASIA',                'lat' => 22.3193,   'lng' => 114.1694],
            'Macau'                      => ['region' => 'ASIA',                'lat' => 22.1987,   'lng' => 113.5439],
            'Brunei'                     => ['region' => 'ASIA',                'lat' => 4.5353,    'lng' => 114.7277],
            'Maldives'                   => ['region' => 'ASIA',                'lat' => 3.2028,    'lng' => 73.2207],
            'Bhutan'                     => ['region' => 'ASIA',                'lat' => 27.5142,   'lng' => 90.4336],
            'Timor-Leste'                => ['region' => 'ASIA',                'lat' => -8.8742,   'lng' => 125.7275],

            // MIDDLE EAST / AFRICA
            'Saudi Arabia'               => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 23.8859,   'lng' => 45.0792],
            'United Arab Emirates'       => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 23.4241,   'lng' => 53.8478],
            'UAE'                        => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 23.4241,   'lng' => 53.8478],
            'Qatar'                      => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 25.3548,   'lng' => 51.1839],
            'Kuwait'                     => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 29.3117,   'lng' => 47.4818],
            'Bahrain'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 26.0667,   'lng' => 50.5577],
            'Oman'                       => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 21.4735,   'lng' => 55.9754],
            'Jordan'                     => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 30.5852,   'lng' => 36.2384],
            'Lebanon'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 33.8547,   'lng' => 35.8623],
            'Iraq'                       => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 33.2232,   'lng' => 43.6793],
            'Iran'                       => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 32.4279,   'lng' => 53.6880],
            'Israel'                     => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 31.0461,   'lng' => 34.8516],
            'Palestine'                  => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 31.9522,   'lng' => 35.2332],
            'Syria'                      => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 34.8021,   'lng' => 38.9968],
            'Yemen'                      => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 15.5527,   'lng' => 48.5164],
            'Egypt'                      => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 26.8206,   'lng' => 30.8025],
            'Nigeria'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 9.0820,    'lng' => 8.6753],
            'South Africa'               => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -30.5595,  'lng' => 22.9375],
            'Kenya'                      => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -0.0236,   'lng' => 37.9062],
            'Ethiopia'                   => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 9.1450,    'lng' => 40.4897],
            'Ghana'                      => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 7.9465,    'lng' => -1.0232],
            'Tanzania'                   => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -6.3690,   'lng' => 34.8888],
            'Uganda'                     => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 1.3733,    'lng' => 32.2903],
            'Algeria'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 28.0339,   'lng' => 1.6596],
            'Morocco'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 31.7917,   'lng' => -7.0926],
            'Tunisia'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 33.8869,   'lng' => 9.5375],
            'Libya'                      => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 26.3351,   'lng' => 17.2283],
            'Sudan'                      => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 12.8628,   'lng' => 30.2176],
            'Angola'                     => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -11.2027,  'lng' => 17.8739],
            'Mozambique'                 => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -18.6657,  'lng' => 35.5296],
            'Zimbabwe'                   => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -19.0154,  'lng' => 29.1549],
            'Zambia'                     => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -13.1339,  'lng' => 27.8493],
            'Cameroon'                   => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 3.8480,    'lng' => 11.5021],
            'Ivory Coast'                => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 7.5400,    'lng' => -5.5471],
            'Senegal'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 14.4974,   'lng' => -14.4524],
            'Somalia'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => 5.1521,    'lng' => 46.1996],
            'Rwanda'                     => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -1.9403,   'lng' => 29.8739],
            'Mauritius'                  => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -20.3484,  'lng' => 57.5522],
            'Namibia'                    => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -22.9576,  'lng' => 18.4904],
            'Botswana'                   => ['region' => 'MIDDLE EAST/AFRICA',  'lat' => -22.3285,  'lng' => 24.6849],
        ];
    }

    private function resolveRegionAndCoords(TrainingCenter $tc): array
    {
        $countryData = $this->getCountryData();
        $country = $tc->country ?? '';

        $region = 'OTHER';
        $lat = $tc->latitude;
        $lng = $tc->longitude;

        // Case-insensitive lookup
        foreach ($countryData as $key => $data) {
            if (strcasecmp($key, $country) === 0) {
                $region = $data['region'];
                if ($lat === null) {
                    $lat = $data['lat'];
                }
                if ($lng === null) {
                    $lng = $data['lng'];
                }
                break;
            }
        }

        return compact('region', 'lat', 'lng');
    }

    #[OA\Get(
        path: "/admin/training-centers/map",
        summary: "Get training centers map data",
        description: "Returns all active training centers with their location data (country, region, lat/lng) for rendering a world map in the Group Admin dashboard. Each entry includes a region classification: USA/CANADA, LATIN AMERICA, EUROPE, ASIA, or MIDDLE EAST/AFRICA.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "active", "suspended", "inactive"]), description: "Filter by status. Defaults to 'active'."),
            new OA\Parameter(name: "region", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["USA/CANADA", "LATIN AMERICA", "EUROPE", "ASIA", "MIDDLE EAST/AFRICA"]), description: "Filter by region")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Map data retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "training_centers",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "name", type: "string", example: "QHSE Academy"),
                                    new OA\Property(property: "country", type: "string", example: "Saudi Arabia"),
                                    new OA\Property(property: "city", type: "string", example: "Riyadh"),
                                    new OA\Property(property: "region", type: "string", example: "MIDDLE EAST/AFRICA"),
                                    new OA\Property(property: "latitude", type: "number", format: "float", example: 23.8859),
                                    new OA\Property(property: "longitude", type: "number", format: "float", example: 45.0792),
                                    new OA\Property(property: "status", type: "string", example: "active"),
                                    new OA\Property(property: "logo_url", type: "string", nullable: true),
                                    new OA\Property(property: "email", type: "string", example: "info@center.com"),
                                    new OA\Property(property: "phone", type: "string", nullable: true),
                                    new OA\Property(property: "training_provider_type", type: "string", nullable: true),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: "summary",
                            type: "object",
                            properties: [
                                new OA\Property(property: "total", type: "integer", example: 42),
                                new OA\Property(
                                    property: "by_region",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "USA/CANADA", type: "integer", example: 8),
                                        new OA\Property(property: "LATIN AMERICA", type: "integer", example: 5),
                                        new OA\Property(property: "EUROPE", type: "integer", example: 12),
                                        new OA\Property(property: "ASIA", type: "integer", example: 10),
                                        new OA\Property(property: "MIDDLE EAST/AFRICA", type: "integer", example: 7),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function mapData(Request $request)
    {
        $status = $request->get('status', 'active');
        $regionFilter = $request->get('region');

        $trainingCenters = TrainingCenter::select([
                'id', 'name', 'country', 'city',
                'latitude', 'longitude',
                'status', 'logo_url', 'email', 'phone',
                'training_provider_type',
            ])
            ->where('status', $status)
            ->get();

        $regions = [
            'USA/CANADA'         => 0,
            'LATIN AMERICA'      => 0,
            'EUROPE'             => 0,
            'ASIA'               => 0,
            'MIDDLE EAST/AFRICA' => 0,
            'OTHER'              => 0,
        ];

        $mapped = $trainingCenters->map(function (TrainingCenter $tc) use (&$regions) {
            $resolved = $this->resolveRegionAndCoords($tc);

            if (isset($regions[$resolved['region']])) {
                $regions[$resolved['region']]++;
            }

            return [
                'id'                     => $tc->id,
                'name'                   => $tc->name,
                'country'                => $tc->country,
                'city'                   => $tc->city,
                'region'                 => $resolved['region'],
                'latitude'               => $resolved['lat'],
                'longitude'              => $resolved['lng'],
                'status'                 => $tc->status,
                'logo_url'               => $tc->logo_url,
                'email'                  => $tc->email,
                'phone'                  => $tc->phone,
                'training_provider_type' => $tc->training_provider_type,
            ];
        });

        // Apply optional region filter after mapping
        if ($regionFilter) {
            $mapped = $mapped->filter(fn($item) => $item['region'] === $regionFilter)->values();
        }

        // Remove OTHER from summary if empty
        if ($regions['OTHER'] === 0) {
            unset($regions['OTHER']);
        }

        return response()->json([
            'training_centers' => $mapped,
            'summary' => [
                'total'     => $mapped->count(),
                'by_region' => $regions,
            ],
        ]);
    }

    #[OA\Get(
        path: "/admin/training-centers/{id}",
        summary: "Get training center details",
        description: "Get detailed information about a specific training center including wallet, instructors, authorizations, certificates, and classes.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Training center retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "training_center", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function show($id)
    {
        $trainingCenter = TrainingCenter::with([
            'wallet',
            'instructors',
            'authorizations.acc',
            'certificates',
            'trainingClasses'
        ])->findOrFail($id);

        return response()->json(['training_center' => $trainingCenter]);
    }

    #[OA\Put(
        path: "/admin/training-centers/{id}",
        summary: "Update training center",
        description: "Update training center information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "legal_name", type: "string", nullable: true),
                    new OA\Property(property: "registration_number", type: "string", nullable: true),
                    new OA\Property(property: "country", type: "string", nullable: true),
                    new OA\Property(property: "city", type: "string", nullable: true),
                    new OA\Property(property: "address", type: "string", nullable: true),
                    new OA\Property(property: "phone", type: "string", nullable: true),
                    new OA\Property(property: "email", type: "string", format: "email", nullable: true),
                    new OA\Property(property: "website", type: "string", nullable: true),
                    new OA\Property(property: "logo_url", type: "string", nullable: true),
                    new OA\Property(property: "referred_by_group", type: "boolean", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["pending", "active", "suspended", "inactive"], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Training center updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Training center updated successfully"),
                        new OA\Property(property: "training_center", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $trainingCenter = TrainingCenter::findOrFail($id);

        $request->validate([
            // Company Information
            'name' => 'sometimes|string|max:255',
            'website' => 'nullable|string|url|max:255',
            'email' => 'sometimes|email|max:255|unique:training_centers,email,' . $id,
            'phone' => 'sometimes|string|max:255',
            'fax' => 'nullable|string|max:255',
            'training_provider_type' => 'sometimes|in:Training Center,Institute,University',
            // Physical Address
            'address' => 'sometimes|string',
            'city' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
            'physical_postal_code' => 'sometimes|string|max:255',
            // Mailing Address
            'mailing_same_as_physical' => 'sometimes|boolean',
            'mailing_address' => 'nullable|string|required_if:mailing_same_as_physical,false',
            'mailing_city' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
            'mailing_country' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
            'mailing_postal_code' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
            // Primary Contact
            'primary_contact_title' => 'sometimes|in:Mr.,Mrs.,Eng.,Prof.',
            'primary_contact_first_name' => 'sometimes|string|max:255',
            'primary_contact_last_name' => 'sometimes|string|max:255',
            'primary_contact_email' => 'sometimes|email|max:255',
            'primary_contact_country' => 'sometimes|string|max:255',
            'primary_contact_mobile' => 'sometimes|string|max:255',
            // Secondary Contact
            'has_secondary_contact' => 'sometimes|boolean',
            'secondary_contact_title' => 'nullable|in:Mr.,Mrs.,Eng.,Prof.|required_if:has_secondary_contact,true',
            'secondary_contact_first_name' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
            'secondary_contact_last_name' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
            'secondary_contact_email' => 'nullable|email|max:255|required_if:has_secondary_contact,true',
            'secondary_contact_country' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
            'secondary_contact_mobile' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
            // Additional Information
            'company_gov_registry_number' => 'sometimes|string|max:255',
            'company_registration_certificate_url' => 'nullable|string|url|max:500',
            'facility_floorplan_url' => 'nullable|string|url|max:500',
            'interested_fields' => 'nullable|array',
            'interested_fields.*' => 'string|in:QHSE,Food Safety,Management',
            'how_did_you_hear_about_us' => 'nullable|string',
            // Legacy fields
            'legal_name' => 'sometimes|string|max:255',
            'registration_number' => 'sometimes|string|max:255|unique:training_centers,registration_number,' . $id,
            'logo_url' => 'nullable|string|url|max:500',
            'referred_by_group' => 'sometimes|boolean',
            'status' => 'sometimes|in:pending,active,suspended,inactive',
        ]);

        $oldStatus = $trainingCenter->status;
        
        // Handle mailing address - if same as physical, copy physical address fields
        $updateData = [];
        if ($request->has('mailing_same_as_physical') && $request->mailing_same_as_physical) {
            $updateData['mailing_same_as_physical'] = true;
            $updateData['mailing_address'] = $request->input('address', $trainingCenter->address);
            $updateData['mailing_city'] = $request->input('city', $trainingCenter->city);
            $updateData['mailing_country'] = $request->input('country', $trainingCenter->country);
            $updateData['mailing_postal_code'] = $request->input('physical_postal_code', $trainingCenter->physical_postal_code);
        }

        // Get all fillable fields from request
        $fillableFields = [
            'name', 'legal_name', 'registration_number', 'country', 'city', 'address',
            'phone', 'email', 'website', 'fax', 'training_provider_type',
            'physical_postal_code',
            'mailing_same_as_physical', 'mailing_address', 'mailing_city', 'mailing_country', 'mailing_postal_code',
            'primary_contact_title', 'primary_contact_first_name', 'primary_contact_last_name',
            'primary_contact_email', 'primary_contact_country', 'primary_contact_mobile',
            'has_secondary_contact', 'secondary_contact_title', 'secondary_contact_first_name',
            'secondary_contact_last_name', 'secondary_contact_email', 'secondary_contact_country', 'secondary_contact_mobile',
            'company_gov_registry_number', 'company_registration_certificate_url', 'facility_floorplan_url',
            'interested_fields', 'how_did_you_hear_about_us',
            'logo_url', 'referred_by_group', 'status',
        ];

        foreach ($fillableFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        $trainingCenter->update($updateData);
        $newStatus = $trainingCenter->status;

        // Notify Training Center admin if status changed
        if ($oldStatus !== $newStatus && in_array($newStatus, ['suspended', 'active', 'inactive'])) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->where('role', 'training_center_admin')->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $notificationService->notifyTrainingCenterStatusChanged(
                    $trainingCenterUser->id,
                    $trainingCenter->id,
                    $trainingCenter->name,
                    $oldStatus,
                    $newStatus,
                    $request->status_change_reason ?? null
                );
            }
        }

        return response()->json([
            'message' => 'Training center updated successfully',
            'training_center' => $trainingCenter->fresh()
        ], 200);
    }

    #[OA\Get(
        path: "/admin/training-centers/applications",
        summary: "Get training center applications",
        description: "Get all pending training center applications for review with pagination and search.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by training center name, legal name, email, registration number, country, or city"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 10), example: 10),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Applications retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "per_page", type: "integer"),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "last_page", type: "integer"),
                        new OA\Property(property: "from", type: "integer", nullable: true),
                        new OA\Property(property: "to", type: "integer", nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function applications(Request $request)
    {
        $query = TrainingCenter::where('status', 'pending');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('legal_name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%")
                    ->orWhere('registration_number', 'like', "%{$searchTerm}%")
                    ->orWhere('country', 'like', "%{$searchTerm}%")
                    ->orWhere('city', 'like', "%{$searchTerm}%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $applications = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($applications);
    }

    #[OA\Put(
        path: "/admin/training-centers/applications/{id}/approve",
        summary: "Approve training center application",
        description: "Approve a training center application.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Application approved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Training center application approved"),
                        new OA\Property(property: "training_center", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Application not found")
        ]
    )]
    public function approve(Request $request, $id)
    {
        $trainingCenter = TrainingCenter::findOrFail($id);
        
        if ($trainingCenter->status !== 'pending') {
            return response()->json([
                'message' => 'Training center application is not pending',
            ], 400);
        }

        $trainingCenter->update([
            'status' => 'active',
        ]);

        // Also activate the user account associated with this training center
        $user = User::where('email', $trainingCenter->email)->first();
        if ($user && $user->role === 'training_center_admin') {
            $user->update(['status' => 'active']);
            
            // Send notification to training center admin
            $notificationService = new NotificationService();
            $notificationService->notifyTrainingCenterApproved($user->id, $trainingCenter->id, $trainingCenter->name);
        }

        return response()->json([
            'message' => 'Training center application approved',
            'training_center' => $trainingCenter->fresh()
        ]);
    }

    #[OA\Put(
        path: "/admin/training-centers/applications/{id}/reject",
        summary: "Reject training center application",
        description: "Reject a training center application with a reason.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["rejection_reason"],
                properties: [
                    new OA\Property(property: "rejection_reason", type: "string", example: "Incomplete documentation")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Application rejected successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Training center application rejected"),
                        new OA\Property(property: "training_center", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Application not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function reject(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string']);

        $trainingCenter = TrainingCenter::findOrFail($id);
        
        if ($trainingCenter->status !== 'pending') {
            return response()->json([
                'message' => 'Training center application is not pending',
            ], 400);
        }

        $trainingCenter->update([
            'status' => 'inactive',
        ]);

        // Send notification to training center admin
        $user = User::where('email', $trainingCenter->email)->first();
        if ($user && $user->role === 'training_center_admin') {
            $notificationService = new NotificationService();
            $notificationService->notifyTrainingCenterRejected($user->id, $trainingCenter->id, $trainingCenter->name, $request->rejection_reason);
        }

        return response()->json([
            'message' => 'Training center application rejected',
            'training_center' => $trainingCenter->fresh(),
        ]);
    }
}

