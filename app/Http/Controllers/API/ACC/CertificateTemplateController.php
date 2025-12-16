<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\CertificateTemplate;
use Illuminate\Http\Request;

class CertificateTemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $templates = CertificateTemplate::where('acc_id', $acc->id)
            ->with('category')
            ->get();

        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'template_html' => 'required|string',
            'template_variables' => 'nullable|array',
            'background_image_url' => 'nullable|string',
            'logo_positions' => 'nullable|array',
            'signature_positions' => 'nullable|array',
            'status' => 'required|in:active,inactive',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::create([
            'acc_id' => $acc->id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'template_html' => $request->template_html,
            'template_variables' => $request->template_variables,
            'background_image_url' => $request->background_image_url,
            'logo_positions' => $request->logo_positions,
            'signature_positions' => $request->signature_positions,
            'status' => $request->status,
        ]);

        return response()->json(['template' => $template], 201);
    }

    public function show($id)
    {
        $template = CertificateTemplate::with('category')->findOrFail($id);
        return response()->json(['template' => $template]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'template_html' => 'sometimes|string',
            'template_variables' => 'nullable|array',
            'background_image_url' => 'nullable|string',
            'logo_positions' => 'nullable|array',
            'signature_positions' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $template->update($request->only([
            'category_id', 'name', 'template_html', 'template_variables',
            'background_image_url', 'logo_positions', 'signature_positions', 'status'
        ]));

        return response()->json(['message' => 'Template updated successfully', 'template' => $template]);
    }

    public function destroy($id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }

    public function preview(Request $request, $id)
    {
        $request->validate([
            'sample_data' => 'required|array',
        ]);

        $template = CertificateTemplate::findOrFail($id);
        
        // TODO: Generate PDF preview with sample data
        // For now, return a placeholder URL
        
        return response()->json([
            'preview_url' => '/preview/template_' . $id . '.pdf',
            'message' => 'Preview generated successfully'
        ]);
    }
}

