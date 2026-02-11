<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StripeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StripeSettingController extends Controller
{
    /**
     * Get Stripe settings (admin only)
     */
    public function index()
    {
        $settings = StripeSetting::all();

        return response()->json([
            'success' => true,
            'data' => $settings->map(function ($setting) {
                return [
                    'id' => $setting->id,
                    'environment' => $setting->environment,
                    'publishable_key' => $setting->publishable_key,
                    'secret_key' => $setting->secret_key ? '***' . substr($setting->secret_key, -4) : null,
                    'webhook_secret' => $setting->webhook_secret ? '***' . substr($setting->webhook_secret, -4) : null,
                    'is_active' => $setting->is_active,
                    'description' => $setting->description,
                    'created_at' => $setting->created_at,
                    'updated_at' => $setting->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Get active Stripe settings
     */
    public function getActive()
    {
        $setting = StripeSetting::getActive();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'No active Stripe settings found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $setting->id,
                'environment' => $setting->environment,
                'publishable_key' => $setting->publishable_key,
                'is_active' => $setting->is_active,
                'description' => $setting->description,
            ],
        ]);
    }

    /**
     * Update Stripe settings
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'publishable_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $setting = StripeSetting::find($id);
        
        if (!$setting) {
            // Get available IDs to help user
            $availableIds = StripeSetting::pluck('id')->toArray();
            return response()->json([
                'success' => false,
                'message' => 'Stripe setting not found',
                'error' => "No Stripe setting found with ID {$id}.",
                'available_ids' => $availableIds,
                'hint' => empty($availableIds) 
                    ? 'No Stripe settings exist. Please create one first using POST /admin/stripe-settings'
                    : 'Available Stripe setting IDs: ' . implode(', ', $availableIds),
            ], 404);
        }

        // If activating this setting, deactivate others in the same environment
        if ($request->has('is_active') && $request->is_active) {
            StripeSetting::where('environment', $setting->environment)
                ->where('id', '!=', $id)
                ->update(['is_active' => false]);
        }

        $setting->update($request->only([
            'publishable_key',
            'secret_key',
            'webhook_secret',
            'is_active',
            'description',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Stripe settings updated successfully',
            'data' => [
                'id' => $setting->id,
                'environment' => $setting->environment,
                'publishable_key' => $setting->publishable_key,
                'secret_key' => $setting->secret_key ? '***' . substr($setting->secret_key, -4) : null,
                'webhook_secret' => $setting->webhook_secret ? '***' . substr($setting->webhook_secret, -4) : null,
                'is_active' => $setting->is_active,
                'description' => $setting->description,
            ],
        ]);
    }

    /**
     * Create new Stripe settings
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'environment' => 'nullable|string|in:sandbox,live',
            'publishable_key' => 'required|string',
            'secret_key' => 'required|string',
            'webhook_secret' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Auto-detect environment from keys if not provided
        $environment = $request->environment;
        if (!$environment) {
            // Detect from secret key (test keys start with sk_test_, live keys start with sk_live_)
            if (str_starts_with($request->secret_key, 'sk_test_')) {
                $environment = 'sandbox';
            } elseif (str_starts_with($request->secret_key, 'sk_live_')) {
                $environment = 'live';
            } else {
                // Default to live if cannot be determined
                $environment = 'live';
            }
        }

        // Prepare data for creation
        $data = $request->all();
        $data['environment'] = $environment;

        // If activating, deactivate others in the same environment
        if ($request->is_active) {
            StripeSetting::where('environment', $environment)
                ->update(['is_active' => false]);
        }

        $setting = StripeSetting::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Stripe settings created successfully',
            'data' => [
                'id' => $setting->id,
                'environment' => $setting->environment,
                'publishable_key' => $setting->publishable_key,
                'secret_key' => $setting->secret_key ? '***' . substr($setting->secret_key, -4) : null,
                'webhook_secret' => $setting->webhook_secret ? '***' . substr($setting->webhook_secret, -4) : null,
                'is_active' => $setting->is_active,
                'description' => $setting->description,
            ],
        ], 201);
    }

    /**
     * Delete Stripe settings
     */
    public function destroy($id)
    {
        $setting = StripeSetting::find($id);
        
        if (!$setting) {
            // Get available IDs to help user
            $availableIds = StripeSetting::pluck('id')->toArray();
            return response()->json([
                'success' => false,
                'message' => 'Stripe setting not found',
                'error' => "No Stripe setting found with ID {$id}.",
                'available_ids' => $availableIds,
                'hint' => empty($availableIds) 
                    ? 'No Stripe settings exist.'
                    : 'Available Stripe setting IDs: ' . implode(', ', $availableIds),
            ], 404);
        }
        
        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stripe settings deleted successfully',
        ]);
    }
}

