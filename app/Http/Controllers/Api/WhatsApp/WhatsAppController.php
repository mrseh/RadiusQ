<?php

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\WhatsApp\BroadcastRequest;
use App\Http\Requests\Api\WhatsApp\BroadcastAreaOptionsRequest;
use App\Http\Requests\Api\WhatsApp\DeleteManyMessagesRequest;
use App\Http\Requests\Api\WhatsApp\ReceiverListRequest;
use App\Http\Requests\Api\WhatsApp\ResendMessageRequest;
use App\Http\Requests\Api\WhatsApp\SaveSettingsRequest;
use App\Http\Requests\Api\WhatsApp\StoreTemplateRequest;
use App\Models\WhatsAppConfig;
use App\Models\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WhatsAppController extends BaseApiController
{
    /**
     * GET /whatsapp/ajax
     * DataTable list with device filter.
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = WhatsAppMessage::query()
            ->with(['device:id,device_name,device_number,device_status'])
            ->when($request->get('device_id'), fn($q) => $q->where('device_id', $request->get('device_id')))
            ->when($request->get('status'), fn($q) => $q->where('status', $request->get('status')))
            ->when($request->get('search'), fn($q) => $q->where('receiver', 'like', '%' . $request->get('search') . '%')
                ->orWhere('message', 'like', '%' . $request->get('search') . '%'))
            ->orderBy('created_at', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * GET /whatsapp/devices
     * List connected WhatsApp devices.
     */
    public function devices(): JsonResponse
    {
        $devices = WhatsAppConfig::query()
            ->orderByDesc('is_default')
            ->orderBy('device_name')
            ->get(['id', 'device_name', 'device_number', 'device_status', 'is_default', 'is_active']);

        return $this->ok($devices);
    }

    /**
     * POST /whatsapp/broadcast
     * Send bulk messages to selected recipients.
     */
    public function broadcast(BroadcastRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $device = WhatsAppConfig::find($validated['device_id']);

        if (!$device) {
            return $this->error('Device tidak ditemukan', 404);
        }

        $messages = [];
        $now = now();

        foreach ($validated['recipients'] as $recipient) {
            $messages[] = [
                'device_id' => $device->id,
                'receiver' => $recipient,
                'message' => $validated['message'],
                'subject' => $validated['subject'] ?? '',
                'status' => 'queued',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('whatsapp_messages')->insert($messages);

        Log::info('WhatsApp: Broadcast queued', [
            'device_id' => $device->id,
            'recipient_count' => count($validated['recipients']),
        ]);

        return $this->ok([
            'queued' => count($messages),
            'device' => $device->device_name,
        ], 'Pesan berhasil diqueue untuk ' . count($messages) . ' penerima');
    }

    /**
     * POST /whatsapp/resend
     * Resend a failed message.
     */
    public function resend(ResendMessageRequest $request): JsonResponse
    {
        $message = WhatsAppMessage::find($request->validated()['message_id']);

        if (!$message) {
            return $this->error('Pesan tidak ditemukan', 404);
        }

        $message->update(['status' => 'queued', 'sent_at' => null]);

        Log::info('WhatsApp: Message resend queued', ['id' => $message->id]);

        return $this->ok($message, 'Pesan berhasil diqueue ulang');
    }

    /**
     * DELETE /whatsapp/destroy-many
     * Delete selected messages.
     */
    public function destroyMany(DeleteManyMessagesRequest $request): JsonResponse
    {
        $deleted = WhatsAppMessage::whereIn('id', $request->validated()['ids'])->delete();

        Log::info('WhatsApp: Bulk deleted messages', ['ids' => $request->validated()['ids']]);

        return $this->ok(['deleted' => $deleted], "{$deleted} pesan berhasil dihapus");
    }

    /**
     * DELETE /whatsapp/clear-all
     * Clear all messages.
     */
    public function clearAll(): JsonResponse
    {
        $count = WhatsAppMessage::count();
        WhatsAppMessage::truncate();

        Log::info('WhatsApp: All messages cleared', ['count' => $count]);

        return $this->ok(['cleared' => $count], "{$count} pesan berhasil dihapus");
    }

    /**
     * POST /whatsapp/frwa/status
     * Check FRWA connection status.
     */
    public function frwaStatus(Request $request): JsonResponse
    {
        $deviceId = $request->get('device_id');
        $device = $deviceId ? WhatsAppConfig::find($deviceId) : null;

        if ($device && $device->device_url) {
            try {
                $response = Http::timeout(5)->get(rtrim($device->device_url, '/') . '/status');

                if ($response->successful()) {
                    return $this->ok([
                        'connected' => true,
                        'device' => $device->device_name,
                        'status' => $device->device_status,
                        'data' => $response->json(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('WhatsApp FRWA status check failed', [
                    'device_id' => $deviceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->ok([
            'connected' => false,
            'device' => $device?->device_name,
            'status' => $device?->device_status ?? 'disconnected',
        ]);
    }

    /**
     * POST /whatsapp/frwa/restart
     * Restart FRWA service.
     */
    public function frwaRestart(Request $request): JsonResponse
    {
        $deviceId = $request->get('device_id');
        $device = $deviceId ? WhatsAppConfig::find($deviceId) : null;

        if (!$device || !$device->device_url) {
            return $this->error('Device URL tidak dikonfigurasi', 422);
        }

        try {
            $response = Http::timeout(10)->post(rtrim($device->device_url, '/') . '/restart');

            if ($response->successful()) {
                $device->update(['device_status' => 'pending']);

                Log::info('WhatsApp FRWA restart triggered', ['device_id' => $device->id]);

                return $this->ok(null, 'FRWA restart berhasil dipicu');
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp FRWA restart failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->error('Gagal restart FRWA service', 502);
    }

    /**
     * POST /whatsapp/frwa/scan-qr
     * Initiate QR scan for new device pairing.
     */
    public function frwaScanQr(Request $request): JsonResponse
    {
        $deviceId = $request->get('device_id');
        $device = $deviceId ? WhatsAppConfig::find($deviceId) : null;

        if (!$device || !$device->device_url) {
            return $this->error('Device URL tidak dikonfigurasi', 422);
        }

        try {
            $response = Http::timeout(15)->post(rtrim($device->device_url, '/') . '/scan-qr');

            if ($response->successful()) {
                $device->update(['device_status' => 'pending']);

                Log::info('WhatsApp QR scan initiated', ['device_id' => $device->id]);

                return $this->ok($response->json(), 'QR scan berhasil dipicu');
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp QR scan failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->error('Gagal memulai QR scan', 502);
    }

    /**
     * POST /whatsapp/frwa/disconnect
     * Disconnect a WhatsApp device.
     */
    public function frwaDisconnect(Request $request): JsonResponse
    {
        $deviceId = $request->get('device_id');
        $device = $deviceId ? WhatsAppConfig::find($deviceId) : null;

        if (!$device) {
            return $this->error('Device tidak ditemukan', 404);
        }

        if ($device->device_url) {
            try {
                Http::timeout(5)->post(rtrim($device->device_url, '/') . '/logout');
            } catch (\Exception $e) {
                Log::warning('WhatsApp FRWA disconnect API call failed', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $device->update(['device_status' => 'disconnected']);

        Log::info('WhatsApp device disconnected', ['device_id' => $device->id]);

        return $this->ok(null, 'Device berhasil disconnect');
    }

    /**
     * POST /whatsapp/setting/save
     * Save WhatsApp settings.
     */
    public function saveSettings(SaveSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['device_id'])) {
            $device = WhatsAppConfig::find($data['device_id']);
            if ($device) {
                $device->update(collect($data)->except('device_id')->toArray());
            }
        }

        return $this->ok(null, 'Pengaturan WhatsApp berhasil disimpan');
    }

    /**
     * POST /whatsapp/whatsapp/setting
     * Get WhatsApp settings (alias).
     */
    public function getSettings(Request $request): JsonResponse
    {
        $deviceId = $request->get('device_id');
        $device = $deviceId ? WhatsAppConfig::find($deviceId) : null;

        return $this->ok([
            'device_id' => $device?->id,
            'device_name' => $device?->device_name,
            'device_number' => $device?->device_number,
            'device_status' => $device?->device_status,
            'jumlah_pesan_per_batch' => $device?->jumlah_pesan_per_batch ?? 10,
            'jeda_antar_batch_menit' => $device?->jeda_antar_batch_menit ?? 5,
            'auto_reconnect' => $device?->auto_reconnect ?? true,
            'save_messages' => $device?->save_messages ?? true,
        ]);
    }

    /**
     * GET /whatsapp/template
     * List message templates.
     */
    public function templateList(Request $request): JsonResponse
    {
        $templates = DB::table('whatsapp_templates')
            ->when($request->get('search'), fn($q) => $q->where('name', 'like', '%' . $request->get('search') . '%'))
            ->orderBy('name')
            ->get();

        return $this->ok($templates);
    }

    /**
     * POST /whatsapp/template
     * Save a message template.
     */
    public function saveTemplate(StoreTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $templateId = $request->get('id');

        if ($templateId) {
            DB::table('whatsapp_templates')->where('id', $templateId)->update([
                'name' => $data['name'],
                'content' => $data['content'],
                'variables' => json_encode($data['variables'] ?? []),
                'updated_at' => now(),
            ]);

            return $this->ok(null, 'Template berhasil diperbarui');
        }

        $id = DB::table('whatsapp_templates')->insertGetId([
            'name' => $data['name'],
            'content' => $data['content'],
            'variables' => json_encode($data['variables'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id], 'Template berhasil disimpan', 201);
    }

    /**
     * POST /whatsapp/broadcast/area-options
     * Get area options for broadcast targeting.
     */
    public function broadcastAreaOptions(BroadcastAreaOptionsRequest $request): JsonResponse
    {
        // Returns POP areas or zones for broadcast filtering
        $areas = DB::table('pop_areas')
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nama', 'lokasi']);

        return $this->ok($areas);
    }

    /**
     * POST /whatsapp/broadcast/receiver-list
     * Get receiver list preview for broadcast.
     */
    public function receiverList(ReceiverListRequest $request): JsonResponse
    {
        $query = DB::table('pppoe_users')
            ->select(['id', 'username', 'nama', 'whatsapp', 'alamat'])
            ->where('status', 'aktif')
            ->whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '');

        if ($request->get('area')) {
            $query->where('alamat', 'like', '%' . $request->get('area') . '%');
        }

        if ($request->get('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        $receivers = $query->limit(200)->get();

        return $this->ok([
            'count' => $receivers->count(),
            'receivers' => $receivers,
        ]);
    }
}