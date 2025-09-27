<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WidgetSettingsController extends Controller
{
    public function save(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $org = $user->organizations()->first();
        if (!$org) {
            return response()->json(['error' => 'No organization found for user'], 404);
        }

        $data = $request->validate([
            'primaryColor' => 'required|string|max:32',
            'chatPosition' => 'required|string|in:bottom-right,bottom-left,top-right,top-left',
            'offsetX' => 'nullable|integer|min:0|max:200',
            'offsetY' => 'nullable|integer|min:0|max:200',
            'welcomeMessage' => 'required|string|max:255',
        ]);

        $settings = $org->settings ?? [];
        $settings['primary_color'] = $data['primaryColor'];
        $settings['widget_position'] = $data['chatPosition'];
    if (isset($data['offsetX'])) $settings['widget_offset_x'] = (int)$data['offsetX'];
    if (isset($data['offsetY'])) $settings['widget_offset_y'] = (int)$data['offsetY'];
        $settings['welcome_message'] = $data['welcomeMessage'];

        // Optional future flag to allow SEO follow links in widget branding
        if ($request->has('brandingFollow')) {
            $settings['branding_follow'] = (bool)$request->input('brandingFollow');
        }

        $org->settings = $settings;
        $org->save();

        Log::info('Customer widget settings saved', [
            'user_id' => $user->id,
            'org_id' => $org->id,
            'settings' => $settings,
        ]);

        return response()->json(['success' => true]);
    }
}
