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
    /**
     * Maps country identifiers (full names AND ISO-2 codes) to region + centroid coordinates.
     * Keys are stored uppercased so lookups are O(1) via array_key_exists after strtoupper().
     */
    private function getCountryData(): array
    {
        $entries = [
            // ── USA / CANADA ─────────────────────────────────────────────────────────
            ['keys' => ['United States', 'USA', 'US'],  'region' => 'USA/CANADA',         'lat' => 37.0902,  'lng' => -95.7129],
            ['keys' => ['Canada', 'CA'],                'region' => 'USA/CANADA',         'lat' => 56.1304,  'lng' => -106.3468],

            // ── LATIN AMERICA ─────────────────────────────────────────────────────────
            ['keys' => ['Mexico', 'MX'],                'region' => 'LATIN AMERICA',      'lat' => 23.6345,  'lng' => -102.5528],
            ['keys' => ['Brazil', 'BR'],                'region' => 'LATIN AMERICA',      'lat' => -14.2350, 'lng' => -51.9253],
            ['keys' => ['Argentina', 'AR'],             'region' => 'LATIN AMERICA',      'lat' => -38.4161, 'lng' => -63.6167],
            ['keys' => ['Colombia', 'CO'],              'region' => 'LATIN AMERICA',      'lat' => 4.5709,   'lng' => -74.2973],
            ['keys' => ['Chile', 'CL'],                 'region' => 'LATIN AMERICA',      'lat' => -35.6751, 'lng' => -71.5430],
            ['keys' => ['Peru', 'PE'],                  'region' => 'LATIN AMERICA',      'lat' => -9.1900,  'lng' => -75.0152],
            ['keys' => ['Venezuela', 'VE'],             'region' => 'LATIN AMERICA',      'lat' => 6.4238,   'lng' => -66.5897],
            ['keys' => ['Ecuador', 'EC'],               'region' => 'LATIN AMERICA',      'lat' => -1.8312,  'lng' => -78.1834],
            ['keys' => ['Bolivia', 'BO'],               'region' => 'LATIN AMERICA',      'lat' => -16.2902, 'lng' => -63.5887],
            ['keys' => ['Paraguay', 'PY'],              'region' => 'LATIN AMERICA',      'lat' => -23.4425, 'lng' => -58.4438],
            ['keys' => ['Uruguay', 'UY'],               'region' => 'LATIN AMERICA',      'lat' => -32.5228, 'lng' => -55.7658],
            ['keys' => ['Guatemala', 'GT'],             'region' => 'LATIN AMERICA',      'lat' => 15.7835,  'lng' => -90.2308],
            ['keys' => ['Honduras', 'HN'],              'region' => 'LATIN AMERICA',      'lat' => 15.2000,  'lng' => -86.2419],
            ['keys' => ['El Salvador', 'SV'],           'region' => 'LATIN AMERICA',      'lat' => 13.7942,  'lng' => -88.8965],
            ['keys' => ['Nicaragua', 'NI'],             'region' => 'LATIN AMERICA',      'lat' => 12.8654,  'lng' => -85.2072],
            ['keys' => ['Costa Rica', 'CR'],            'region' => 'LATIN AMERICA',      'lat' => 9.7489,   'lng' => -83.7534],
            ['keys' => ['Panama', 'PA'],                'region' => 'LATIN AMERICA',      'lat' => 8.5380,   'lng' => -80.7821],
            ['keys' => ['Cuba', 'CU'],                  'region' => 'LATIN AMERICA',      'lat' => 21.5218,  'lng' => -77.7812],
            ['keys' => ['Dominican Republic', 'DO'],    'region' => 'LATIN AMERICA',      'lat' => 18.7357,  'lng' => -70.1627],
            ['keys' => ['Haiti', 'HT'],                 'region' => 'LATIN AMERICA',      'lat' => 18.9712,  'lng' => -72.2852],
            ['keys' => ['Jamaica', 'JM'],               'region' => 'LATIN AMERICA',      'lat' => 18.1096,  'lng' => -77.2975],
            ['keys' => ['Trinidad and Tobago', 'TT'],   'region' => 'LATIN AMERICA',      'lat' => 10.6918,  'lng' => -61.2225],

            // ── EUROPE ────────────────────────────────────────────────────────────────
            ['keys' => ['United Kingdom', 'UK', 'GB'],  'region' => 'EUROPE',             'lat' => 55.3781,  'lng' => -3.4360],
            ['keys' => ['Germany', 'DE'],               'region' => 'EUROPE',             'lat' => 51.1657,  'lng' => 10.4515],
            ['keys' => ['France', 'FR'],                'region' => 'EUROPE',             'lat' => 46.2276,  'lng' => 2.2137],
            ['keys' => ['Italy', 'IT'],                 'region' => 'EUROPE',             'lat' => 41.8719,  'lng' => 12.5674],
            ['keys' => ['Spain', 'ES'],                 'region' => 'EUROPE',             'lat' => 40.4637,  'lng' => -3.7492],
            ['keys' => ['Netherlands', 'NL'],           'region' => 'EUROPE',             'lat' => 52.1326,  'lng' => 5.2913],
            ['keys' => ['Belgium', 'BE'],               'region' => 'EUROPE',             'lat' => 50.5039,  'lng' => 4.4699],
            ['keys' => ['Switzerland', 'CH'],           'region' => 'EUROPE',             'lat' => 46.8182,  'lng' => 8.2275],
            ['keys' => ['Austria', 'AT'],               'region' => 'EUROPE',             'lat' => 47.5162,  'lng' => 14.5501],
            ['keys' => ['Sweden', 'SE'],                'region' => 'EUROPE',             'lat' => 60.1282,  'lng' => 18.6435],
            ['keys' => ['Norway', 'NO'],                'region' => 'EUROPE',             'lat' => 60.4720,  'lng' => 8.4689],
            ['keys' => ['Denmark', 'DK'],               'region' => 'EUROPE',             'lat' => 56.2639,  'lng' => 9.5018],
            ['keys' => ['Finland', 'FI'],               'region' => 'EUROPE',             'lat' => 61.9241,  'lng' => 25.7482],
            ['keys' => ['Poland', 'PL'],                'region' => 'EUROPE',             'lat' => 51.9194,  'lng' => 19.1451],
            ['keys' => ['Czech Republic', 'CZ'],        'region' => 'EUROPE',             'lat' => 49.8175,  'lng' => 15.4730],
            ['keys' => ['Hungary', 'HU'],               'region' => 'EUROPE',             'lat' => 47.1625,  'lng' => 19.5033],
            ['keys' => ['Romania', 'RO'],               'region' => 'EUROPE',             'lat' => 45.9432,  'lng' => 24.9668],
            ['keys' => ['Bulgaria', 'BG'],              'region' => 'EUROPE',             'lat' => 42.7339,  'lng' => 25.4858],
            ['keys' => ['Greece', 'GR'],                'region' => 'EUROPE',             'lat' => 39.0742,  'lng' => 21.8243],
            ['keys' => ['Portugal', 'PT'],              'region' => 'EUROPE',             'lat' => 39.3999,  'lng' => -8.2245],
            ['keys' => ['Ireland', 'IE'],               'region' => 'EUROPE',             'lat' => 53.1424,  'lng' => -7.6921],
            ['keys' => ['Ukraine', 'UA'],               'region' => 'EUROPE',             'lat' => 48.3794,  'lng' => 31.1656],
            ['keys' => ['Russia', 'RU'],                'region' => 'EUROPE',             'lat' => 61.5240,  'lng' => 105.3188],
            ['keys' => ['Turkey', 'TR'],                'region' => 'EUROPE',             'lat' => 38.9637,  'lng' => 35.2433],
            ['keys' => ['Croatia', 'HR'],               'region' => 'EUROPE',             'lat' => 45.1000,  'lng' => 15.2000],
            ['keys' => ['Serbia', 'RS'],                'region' => 'EUROPE',             'lat' => 44.0165,  'lng' => 21.0059],
            ['keys' => ['Slovakia', 'SK'],              'region' => 'EUROPE',             'lat' => 48.6690,  'lng' => 19.6990],
            ['keys' => ['Luxembourg', 'LU'],            'region' => 'EUROPE',             'lat' => 49.8153,  'lng' => 6.1296],
            ['keys' => ['Iceland', 'IS'],               'region' => 'EUROPE',             'lat' => 64.9631,  'lng' => -19.0208],
            ['keys' => ['Malta', 'MT'],                 'region' => 'EUROPE',             'lat' => 35.9375,  'lng' => 14.3754],
            ['keys' => ['Cyprus', 'CY'],                'region' => 'EUROPE',             'lat' => 35.1264,  'lng' => 33.4299],

            // ── ASIA ──────────────────────────────────────────────────────────────────
            ['keys' => ['China', 'CN'],                 'region' => 'ASIA',               'lat' => 35.8617,  'lng' => 104.1954],
            ['keys' => ['Japan', 'JP'],                 'region' => 'ASIA',               'lat' => 36.2048,  'lng' => 138.2529],
            ['keys' => ['India', 'IN'],                 'region' => 'ASIA',               'lat' => 20.5937,  'lng' => 78.9629],
            ['keys' => ['South Korea', 'Korea', 'KR'],  'region' => 'ASIA',               'lat' => 35.9078,  'lng' => 127.7669],
            ['keys' => ['Indonesia', 'ID'],             'region' => 'ASIA',               'lat' => -0.7893,  'lng' => 113.9213],
            ['keys' => ['Malaysia', 'MY'],              'region' => 'ASIA',               'lat' => 4.2105,   'lng' => 101.9758],
            ['keys' => ['Philippines', 'PH'],           'region' => 'ASIA',               'lat' => 12.8797,  'lng' => 121.7740],
            ['keys' => ['Vietnam', 'VN'],               'region' => 'ASIA',               'lat' => 14.0583,  'lng' => 108.2772],
            ['keys' => ['Thailand', 'TH'],              'region' => 'ASIA',               'lat' => 15.8700,  'lng' => 100.9925],
            ['keys' => ['Singapore', 'SG'],             'region' => 'ASIA',               'lat' => 1.3521,   'lng' => 103.8198],
            ['keys' => ['Bangladesh', 'BD'],            'region' => 'ASIA',               'lat' => 23.6850,  'lng' => 90.3563],
            ['keys' => ['Pakistan', 'PK'],              'region' => 'ASIA',               'lat' => 30.3753,  'lng' => 69.3451],
            ['keys' => ['Sri Lanka', 'LK'],             'region' => 'ASIA',               'lat' => 7.8731,   'lng' => 80.7718],
            ['keys' => ['Nepal', 'NP'],                 'region' => 'ASIA',               'lat' => 28.3949,  'lng' => 84.1240],
            ['keys' => ['Myanmar', 'MM'],               'region' => 'ASIA',               'lat' => 21.9162,  'lng' => 95.9560],
            ['keys' => ['Cambodia', 'KH'],              'region' => 'ASIA',               'lat' => 12.5657,  'lng' => 104.9910],
            ['keys' => ['Laos', 'LA'],                  'region' => 'ASIA',               'lat' => 19.8563,  'lng' => 102.4955],
            ['keys' => ['Mongolia', 'MN'],              'region' => 'ASIA',               'lat' => 46.8625,  'lng' => 103.8467],
            ['keys' => ['Kazakhstan', 'KZ'],            'region' => 'ASIA',               'lat' => 48.0196,  'lng' => 66.9237],
            ['keys' => ['Uzbekistan', 'UZ'],            'region' => 'ASIA',               'lat' => 41.3775,  'lng' => 64.5853],
            ['keys' => ['Azerbaijan', 'AZ'],            'region' => 'ASIA',               'lat' => 40.1431,  'lng' => 47.5769],
            ['keys' => ['Georgia', 'GE'],               'region' => 'ASIA',               'lat' => 42.3154,  'lng' => 43.3569],
            ['keys' => ['Armenia', 'AM'],               'region' => 'ASIA',               'lat' => 40.0691,  'lng' => 45.0382],
            ['keys' => ['Taiwan', 'TW'],                'region' => 'ASIA',               'lat' => 23.6978,  'lng' => 120.9605],
            ['keys' => ['Hong Kong', 'HK'],             'region' => 'ASIA',               'lat' => 22.3193,  'lng' => 114.1694],
            ['keys' => ['Macau', 'MO'],                 'region' => 'ASIA',               'lat' => 22.1987,  'lng' => 113.5439],
            ['keys' => ['Brunei', 'BN'],                'region' => 'ASIA',               'lat' => 4.5353,   'lng' => 114.7277],
            ['keys' => ['Maldives', 'MV'],              'region' => 'ASIA',               'lat' => 3.2028,   'lng' => 73.2207],
            ['keys' => ['Bhutan', 'BT'],                'region' => 'ASIA',               'lat' => 27.5142,  'lng' => 90.4336],
            ['keys' => ['Timor-Leste', 'TL'],           'region' => 'ASIA',               'lat' => -8.8742,  'lng' => 125.7275],

            // ── MIDDLE EAST / AFRICA ──────────────────────────────────────────────────
            ['keys' => ['Saudi Arabia', 'SA'],          'region' => 'MIDDLE EAST/AFRICA', 'lat' => 23.8859,  'lng' => 45.0792],
            ['keys' => ['United Arab Emirates', 'UAE', 'AE'], 'region' => 'MIDDLE EAST/AFRICA', 'lat' => 23.4241, 'lng' => 53.8478],
            ['keys' => ['Qatar', 'QA'],                 'region' => 'MIDDLE EAST/AFRICA', 'lat' => 25.3548,  'lng' => 51.1839],
            ['keys' => ['Kuwait', 'KW'],                'region' => 'MIDDLE EAST/AFRICA', 'lat' => 29.3117,  'lng' => 47.4818],
            ['keys' => ['Bahrain', 'BH'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => 26.0667,  'lng' => 50.5577],
            ['keys' => ['Oman', 'OM'],                  'region' => 'MIDDLE EAST/AFRICA', 'lat' => 21.4735,  'lng' => 55.9754],
            ['keys' => ['Jordan', 'JO'],                'region' => 'MIDDLE EAST/AFRICA', 'lat' => 30.5852,  'lng' => 36.2384],
            ['keys' => ['Lebanon', 'LB'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => 33.8547,  'lng' => 35.8623],
            ['keys' => ['Iraq', 'IQ'],                  'region' => 'MIDDLE EAST/AFRICA', 'lat' => 33.2232,  'lng' => 43.6793],
            ['keys' => ['Iran', 'IR'],                  'region' => 'MIDDLE EAST/AFRICA', 'lat' => 32.4279,  'lng' => 53.6880],
            ['keys' => ['Israel', 'IL'],                'region' => 'MIDDLE EAST/AFRICA', 'lat' => 31.0461,  'lng' => 34.8516],
            ['keys' => ['Palestine', 'PS'],             'region' => 'MIDDLE EAST/AFRICA', 'lat' => 31.9522,  'lng' => 35.2332],
            ['keys' => ['Syria', 'SY'],                 'region' => 'MIDDLE EAST/AFRICA', 'lat' => 34.8021,  'lng' => 38.9968],
            ['keys' => ['Yemen', 'YE'],                 'region' => 'MIDDLE EAST/AFRICA', 'lat' => 15.5527,  'lng' => 48.5164],
            ['keys' => ['Egypt', 'EG'],                 'region' => 'MIDDLE EAST/AFRICA', 'lat' => 26.8206,  'lng' => 30.8025],
            ['keys' => ['Nigeria', 'NG'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => 9.0820,   'lng' => 8.6753],
            ['keys' => ['South Africa', 'ZA'],          'region' => 'MIDDLE EAST/AFRICA', 'lat' => -30.5595, 'lng' => 22.9375],
            ['keys' => ['Kenya', 'KE'],                 'region' => 'MIDDLE EAST/AFRICA', 'lat' => -0.0236,  'lng' => 37.9062],
            ['keys' => ['Ethiopia', 'ET'],              'region' => 'MIDDLE EAST/AFRICA', 'lat' => 9.1450,   'lng' => 40.4897],
            ['keys' => ['Ghana', 'GH'],                 'region' => 'MIDDLE EAST/AFRICA', 'lat' => 7.9465,   'lng' => -1.0232],
            ['keys' => ['Tanzania', 'TZ'],              'region' => 'MIDDLE EAST/AFRICA', 'lat' => -6.3690,  'lng' => 34.8888],
            ['keys' => ['Uganda', 'UG'],                'region' => 'MIDDLE EAST/AFRICA', 'lat' => 1.3733,   'lng' => 32.2903],
            ['keys' => ['Algeria', 'DZ'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => 28.0339,  'lng' => 1.6596],
            ['keys' => ['Morocco', 'MA'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => 31.7917,  'lng' => -7.0926],
            ['keys' => ['Tunisia', 'TN'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => 33.8869,  'lng' => 9.5375],
            ['keys' => ['Libya', 'LY'],                 'region' => 'MIDDLE EAST/AFRICA', 'lat' => 26.3351,  'lng' => 17.2283],
            ['keys' => ['Sudan', 'SD'],                 'region' => 'MIDDLE EAST/AFRICA', 'lat' => 12.8628,  'lng' => 30.2176],
            ['keys' => ['Angola', 'AO'],                'region' => 'MIDDLE EAST/AFRICA', 'lat' => -11.2027, 'lng' => 17.8739],
            ['keys' => ['Mozambique', 'MZ'],            'region' => 'MIDDLE EAST/AFRICA', 'lat' => -18.6657, 'lng' => 35.5296],
            ['keys' => ['Zimbabwe', 'ZW'],              'region' => 'MIDDLE EAST/AFRICA', 'lat' => -19.0154, 'lng' => 29.1549],
            ['keys' => ['Zambia', 'ZM'],                'region' => 'MIDDLE EAST/AFRICA', 'lat' => -13.1339, 'lng' => 27.8493],
            ['keys' => ['Cameroon', 'CM'],              'region' => 'MIDDLE EAST/AFRICA', 'lat' => 3.8480,   'lng' => 11.5021],
            ['keys' => ['Ivory Coast', 'CI'],           'region' => 'MIDDLE EAST/AFRICA', 'lat' => 7.5400,   'lng' => -5.5471],
            ['keys' => ['Senegal', 'SN'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => 14.4974,  'lng' => -14.4524],
            ['keys' => ['Somalia', 'SO'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => 5.1521,   'lng' => 46.1996],
            ['keys' => ['Rwanda', 'RW'],                'region' => 'MIDDLE EAST/AFRICA', 'lat' => -1.9403,  'lng' => 29.8739],
            ['keys' => ['Mauritius', 'MU'],             'region' => 'MIDDLE EAST/AFRICA', 'lat' => -20.3484, 'lng' => 57.5522],
            ['keys' => ['Namibia', 'NA'],               'region' => 'MIDDLE EAST/AFRICA', 'lat' => -22.9576, 'lng' => 18.4904],
            ['keys' => ['Botswana', 'BW'],              'region' => 'MIDDLE EAST/AFRICA', 'lat' => -22.3285, 'lng' => 24.6849],
        ];

        // Flatten into a single uppercase-keyed map for O(1) lookup
        $map = [];
        foreach ($entries as $entry) {
            $payload = ['region' => $entry['region'], 'lat' => $entry['lat'], 'lng' => $entry['lng']];
            foreach ($entry['keys'] as $key) {
                $map[strtoupper($key)] = $payload;
            }
        }

        return $map;
    }

    private function resolveRegionAndCoords(TrainingCenter $tc): array
    {
        $countryData = $this->getCountryData();
        $key = strtoupper(trim($tc->country ?? ''));

        $region = 'OTHER';
        $lat    = $tc->latitude;
        $lng    = $tc->longitude;

        if ($key !== '' && isset($countryData[$key])) {
            $data   = $countryData[$key];
            $region = $data['region'];
            if ($lat === null) {
                $lat = $data['lat'];
            }
            if ($lng === null) {
                $lng = $data['lng'];
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
                                new OA\Property(
                                    property: "export_download_urls",
                                    type: "object",
                                    description: "Per-region download URL for Excel (CSV) export. One file per region.",
                                    additionalProperties: new OA\AdditionalProperties(type: "string", example: "https://app.example.com/api/admin/training-centers/map/export?region=EUROPE&status=active")
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

        $status = $request->get('status', 'active');
        $exportBase = url('/api/admin/training-centers/map/export');
        $exportDownloadUrls = [];
        foreach (array_keys($regions) as $r) {
            $exportDownloadUrls[$r] = $exportBase . '?region=' . rawurlencode($r) . '&status=' . rawurlencode($status);
        }

        return response()->json([
            'training_centers' => $mapped,
            'summary' => [
                'total'                  => $mapped->count(),
                'by_region'              => $regions,
                'export_download_urls'   => $exportDownloadUrls,
            ],
        ]);
    }

    #[OA\Get(
        path: "/admin/training-centers/map/export",
        summary: "Download training centers Excel (CSV) by region",
        description: "Returns a CSV file (Excel-compatible) of training centers for the given region. Use one request per region to get one Excel file per region. Requires region query parameter.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "region", in: "query", required: true, schema: new OA\Schema(type: "string", enum: ["USA/CANADA", "LATIN AMERICA", "EUROPE", "ASIA", "MIDDLE EAST/AFRICA", "OTHER"]), description: "Region to export"),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "active", "suspended", "inactive"]), description: "Filter by status. Defaults to 'active'.")
        ],
        responses: [
            new OA\Response(response: 200, description: "CSV file download"),
            new OA\Response(response: 400, description: "Missing region parameter"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function mapExport(Request $request)
    {
        $region = $request->get('region');
        if ($region === null || $region === '') {
            return response()->json(['message' => 'The region parameter is required.'], 400);
        }

        $status = $request->get('status', 'active');
        $trainingCenters = TrainingCenter::select([
                'id', 'name', 'country', 'city',
                'latitude', 'longitude',
                'status', 'logo_url', 'email', 'phone',
                'training_provider_type',
            ])
            ->where('status', $status)
            ->get();

        $rows = [];
        $rows[] = ['ID', 'Name', 'Country', 'City', 'Region', 'Status', 'Email', 'Phone', 'Training Provider Type', 'Logo URL'];

        foreach ($trainingCenters as $tc) {
            $resolved = $this->resolveRegionAndCoords($tc);
            if ($resolved['region'] !== $region) {
                continue;
            }
            $rows[] = [
                $tc->id,
                $tc->name ?? '',
                $tc->country ?? '',
                $tc->city ?? '',
                $resolved['region'],
                $tc->status ?? '',
                $tc->email ?? '',
                $tc->phone ?? '',
                $tc->training_provider_type ?? '',
                $tc->logo_url ?? '',
            ];
        }

        $filename = 'training-centers-' . preg_replace('/[^a-z0-9_-]/i', '-', $region) . '.csv';
        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

