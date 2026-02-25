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
            'assistantDisplayName' => 'nullable|string|max:64',
            'requireContactForGuests' => 'nullable|boolean',
            'widgetAllowedDomains' => 'nullable|string|max:3000',
            'widgetContactFields' => 'nullable|string|max:5000',
            'queryTranslationMap' => 'nullable|string|max:12000',
            'widgetCustomCss' => 'nullable|string|max:20000',
            'widgetCustomJs' => 'nullable|string|max:20000',
        ]);

        $settings = $org->settings ?? [];
        $settings['primary_color'] = $data['primaryColor'];
        $settings['widget_position'] = $data['chatPosition'];
    if (isset($data['offsetX'])) $settings['widget_offset_x'] = (int)$data['offsetX'];
    if (isset($data['offsetY'])) $settings['widget_offset_y'] = (int)$data['offsetY'];
        $settings['welcome_message'] = $data['welcomeMessage'];
        if (array_key_exists('assistantDisplayName', $data)) {
            $settings['assistant_display_name'] = trim((string)$data['assistantDisplayName']) !== ''
                ? trim((string)$data['assistantDisplayName'])
                : null;
        }
        $settings['require_contact_for_guests'] = (bool) ($data['requireContactForGuests'] ?? false);

        if (array_key_exists('widgetAllowedDomains', $data)) {
            $raw = trim((string) $data['widgetAllowedDomains']);
            if ($raw === '') {
                unset($settings['widget_allowed_domains']);
            } else {
                $domains = preg_split('/[\r\n,]+/', $raw) ?: [];
                $domains = array_values(array_filter(array_map(function ($domain) {
                    $domain = strtolower(trim((string) $domain));
                    if ($domain === '') {
                        return null;
                    }
                    if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
                        $domain = strtolower((string) parse_url($domain, PHP_URL_HOST));
                    }
                    return trim($domain, '.');
                }, $domains)));

                $settings['widget_allowed_domains'] = $domains;
            }
        }

        if (array_key_exists('widgetContactFields', $data)) {
            $raw = trim((string) $data['widgetContactFields']);
            if ($raw === '') {
                unset($settings['widget_contact_fields']);
            } else {
                $fields = [];
                $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
                foreach ($lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    // Format: key|Label|type|required
                    $parts = array_map('trim', explode('|', $line));
                    $key = strtolower((string) ($parts[0] ?? ''));
                    $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
                    $key = trim($key, '_');
                    if ($key === '') {
                        continue;
                    }

                    $label = (string) ($parts[1] ?? ucfirst(str_replace('_', ' ', $key)));
                    $type = strtolower((string) ($parts[2] ?? 'text'));
                    if (!in_array($type, ['text', 'email', 'phone', 'number', 'location'], true)) {
                        $type = 'text';
                    }

                    $requiredRaw = strtolower((string) ($parts[3] ?? 'false'));
                    $required = in_array($requiredRaw, ['1', 'true', 'yes', 'required', 'y'], true);

                    $fields[$key] = [
                        'key' => $key,
                        'label' => $label,
                        'type' => $type,
                        'required' => $required,
                        'placeholder' => 'Your ' . $label . ($required ? ' *' : ''),
                    ];
                }

                $settings['widget_contact_fields'] = array_values($fields);
            }
        }

        if (array_key_exists('queryTranslationMap', $data)) {
            $raw = trim((string) $data['queryTranslationMap']);
            if ($raw === '') {
                unset($settings['query_translation_map']);
            } else {
                $settings['query_translation_map'] = $raw;
            }
        }

        if (array_key_exists('widgetCustomCss', $data)) {
            $raw = trim((string) $data['widgetCustomCss']);
            if ($raw === '') {
                unset($settings['widget_custom_css']);
            } else {
                $settings['widget_custom_css'] = $raw;
            }
        }

        if (array_key_exists('widgetCustomJs', $data)) {
            $raw = trim((string) $data['widgetCustomJs']);
            if ($raw === '') {
                unset($settings['widget_custom_js']);
            } else {
                $settings['widget_custom_js'] = $raw;
            }
        }

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
