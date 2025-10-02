<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderStatusController extends Controller
{
    /**
     * Fetch latest order status by email or user_id or explicit order_id.
     * If identifiers are missing, respond with a prompt describing what is needed.
     * This is channel-agnostic and safe for public widget use; rate-limit via middleware in routes if desired.
     */
    public function latest(Request $request)
    {
        try {
            $orderId = trim((string) $request->input('order_id', ''));
            $email = trim((string) $request->input('email', ''));
            $userId = $request->input('user_id');

            if ($orderId === '' && $email === '' && empty($userId)) {
                return response()->json([
                    'needs_input' => true,
                    'message' => 'Please provide your order_id, or your account email/user_id to look up your latest order.',
                    'fields' => ['order_id', 'email', 'user_id']
                ], 422);
            }

            // Try explicit order first
            if ($orderId !== '') {
                $order = $this->findOrderById($orderId);
                if ($order) {
                    return response()->json(['found' => true, 'order' => $order]);
                }
            }

            // Fallback by email
            if ($email !== '') {
                $order = $this->findLatestOrderByEmail($email);
                if ($order) {
                    return response()->json(['found' => true, 'order' => $order]);
                }
            }

            // Fallback by user_id
            if (!empty($userId)) {
                $order = $this->findLatestOrderByUserId($userId);
                if ($order) {
                    return response()->json(['found' => true, 'order' => $order]);
                }
            }

            return response()->json(['found' => false, 'message' => 'No matching orders found. Please verify your details.'], 404);
        } catch (\Throwable $e) {
            Log::error('Order status lookup error', ['error' => $e->getMessage()]);
            return response()->json(['error' => true, 'message' => 'Unable to retrieve order status at the moment. Please try again later.'], 500);
        }
    }

    private function findOrderById(string $orderId): ?array
    {
        // Try common schemas; adapt if a proper Order model exists later
        $tables = [
            // Generic e-commerce style
            ['table' => 'orders', 'id' => 'id', 'status' => 'status', 'user_id' => 'user_id', 'email' => 'email', 'updated_at' => 'updated_at'],
            // PayPal credits/orders tracking (if present)
            ['table' => 'paypal_orders', 'id' => 'order_id', 'status' => 'status', 'user_id' => 'user_id', 'email' => 'payer_email', 'updated_at' => 'updated_at'],
        ];
        foreach ($tables as $t) {
            if (!DB::getSchemaBuilder()->hasTable($t['table'])) { continue; }
            $row = DB::table($t['table'])->where($t['id'], $orderId)->orderByDesc($t['updated_at'] ?? 'id')->first();
            if ($row) { return $this->normalizeOrderRow($row, $t); }
        }
        return null;
    }

    private function findLatestOrderByEmail(string $email): ?array
    {
        $tables = [
            ['table' => 'orders', 'email' => 'email', 'status' => 'status', 'id' => 'id', 'user_id' => 'user_id', 'updated_at' => 'updated_at'],
            ['table' => 'paypal_orders', 'email' => 'payer_email', 'status' => 'status', 'id' => 'order_id', 'user_id' => 'user_id', 'updated_at' => 'updated_at'],
        ];
        foreach ($tables as $t) {
            if (!DB::getSchemaBuilder()->hasTable($t['table'])) { continue; }
            $row = DB::table($t['table'])->where($t['email'], $email)->orderByDesc($t['updated_at'] ?? 'id')->first();
            if ($row) { return $this->normalizeOrderRow($row, $t); }
        }
        return null;
    }

    private function findLatestOrderByUserId($userId): ?array
    {
        $tables = [
            ['table' => 'orders', 'user_id' => 'user_id', 'status' => 'status', 'id' => 'id', 'email' => 'email', 'updated_at' => 'updated_at'],
            ['table' => 'paypal_orders', 'user_id' => 'user_id', 'status' => 'status', 'id' => 'order_id', 'email' => 'payer_email', 'updated_at' => 'updated_at'],
        ];
        foreach ($tables as $t) {
            if (!DB::getSchemaBuilder()->hasTable($t['table'])) { continue; }
            $row = DB::table($t['table'])->where($t['user_id'], $userId)->orderByDesc($t['updated_at'] ?? 'id')->first();
            if ($row) { return $this->normalizeOrderRow($row, $t); }
        }
        return null;
    }

    private function normalizeOrderRow($row, array $map): array
    {
        $arr = (array) $row;
        return [
            'order_id' => $arr[$map['id']] ?? null,
            'status' => $arr[$map['status']] ?? null,
            'user_id' => $arr[$map['user_id']] ?? null,
            'email' => $arr[$map['email']] ?? null,
            'updated_at' => $arr[$map['updated_at']] ?? null,
            'raw' => $arr,
        ];
    }
}
