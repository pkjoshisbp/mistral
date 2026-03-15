<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FaqImageController extends Controller
{
    /**
     * Accept a base64-encoded image, resize to max 1200 px, save as WebP,
     * and return the public URL.  The caller then inserts an <img src="URL">
     * tag — no blob is ever stored in the FAQ answer or sent to Qdrant.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|string',       // base64 data URL: data:image/...;base64,...
        ]);

        $dataUrl = $request->input('image');

        // ── Parse the data URL ────────────────────────────────────────────────
        if (!preg_match('/^data:(image\/[a-zA-Z+]+);base64,(.+)$/', $dataUrl, $m)) {
            return response()->json(['error' => 'Invalid image data'], 422);
        }
        $mimeType  = $m[1]; // e.g. image/png
        $base64    = $m[2];
        $raw       = base64_decode($base64, true);
        if ($raw === false || strlen($raw) === 0) {
            return response()->json(['error' => 'Failed to decode image'], 422);
        }

        // Safety: cap raw upload at 8 MB before decompression
        if (strlen($raw) > 8 * 1024 * 1024) {
            return response()->json(['error' => 'Image too large (max 8 MB)'], 422);
        }

        // ── Create GD image from raw bytes ────────────────────────────────────
        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return response()->json(['error' => 'Unsupported image format'], 422);
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        // ── Resize to max 1200 × 1200 ─────────────────────────────────────────
        $maxDim = 1200;
        if ($origW > $maxDim || $origH > $maxDim) {
            if ($origW >= $origH) {
                $newW = $maxDim;
                $newH = (int) round($origH * $maxDim / $origW);
            } else {
                $newH = $maxDim;
                $newW = (int) round($origW * $maxDim / $origH);
            }
            $dst = imagecreatetruecolor($newW, $newH);
            // Preserve transparency for PNG/webp
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($src);
            $src = $dst;
        }

        // ── Save as WebP ──────────────────────────────────────────────────────
        $dir      = storage_path('app/public/faq-images');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = Str::uuid() . '.webp';
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        // Quality 85 is a good balance of size vs clarity
        if (!imagewebp($src, $fullPath, 85)) {
            imagedestroy($src);
            return response()->json(['error' => 'Failed to save image'], 500);
        }
        imagedestroy($src);

        // ── Return public URL ─────────────────────────────────────────────────
        $url = asset('storage/faq-images/' . $filename);

        return response()->json(['url' => $url]);
    }
}
