<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta webhook verification (GET). Returns hub.challenge when verified.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = config('services.whatsapp.webhook_verify_token', env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'));

        if ($mode === 'subscribe' && $token === $verifyToken && $challenge) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Meta webhook callback (POST). Updates message status from statuses payload.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();

        if (empty($payload['object']) || $payload['object'] !== 'whatsapp_business_account') {
            return response('', 200);
        }

        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $statusItem) {
                    $this->updateLogStatus($statusItem);
                }
            }
        }

        return response('', 200);
    }

    protected function updateLogStatus(array $statusItem): void
    {
        $messageId = $statusItem['id'] ?? null;
        $status = $statusItem['status'] ?? null;
        $timestamp = isset($statusItem['timestamp']) ? (int) $statusItem['timestamp'] : null;

        if (!$messageId || !$status) {
            return;
        }

        $log = WhatsAppMessageLog::where('message_id', $messageId)->first();
        if (!$log) {
            Log::debug('WhatsApp webhook: unknown message_id', ['message_id' => $messageId, 'status' => $status]);
            return;
        }

        $meta = (array) $log->meta_payload;
        $meta['last_status'] = $statusItem;
        $updates = [
            'status' => $status,
            'meta_payload' => $meta,
        ];

        if ($timestamp) {
            $date = \Carbon\Carbon::createFromTimestamp($timestamp);
            if ($status === 'delivered') {
                $updates['delivered_at'] = $date;
            } elseif ($status === 'read') {
                $updates['read_at'] = $date;
            } elseif (in_array($status, ['failed', 'deleted'], true)) {
                $updates['failed_at'] = $date;
            }
        }

        $log->update($updates);
    }
}
