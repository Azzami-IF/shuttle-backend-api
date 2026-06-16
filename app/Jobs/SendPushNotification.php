<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use App\Models\User;
use App\Models\Notification;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public $userId,
        public $title,
        public $body,
        public $data = []
    ) {}

    public function handle()
    {
        try {
            $user = User::find($this->userId);
            if (!$user || !$user->fcm_token) return;

            $apiKey = config('notifications.fcm_api_key');
            if (!$apiKey) {
                throw new \Exception('FCM API key not configured');
            }

            $client = new Client();
            $response = $client->post('https://fcm.googleapis.com/fcm/send', [
                'headers' => [
                    'Authorization' => 'key=' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $user->fcm_token,
                    'notification' => [
                        'title' => $this->title,
                        'body' => $this->body,
                    ],
                    'data' => $this->data,
                ]
            ]);

            Notification::create([
                'user_id' => $this->userId,
                'channel' => 'push',
                'recipient' => $user->fcm_token,
                'subject' => $this->title,
                'body' => $this->body,
                'data' => $this->data,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Push notification failed: ' . $e->getMessage());
            Notification::create([
                'user_id' => $this->userId,
                'channel' => 'push',
                'subject' => $this->title,
                'body' => $this->body,
                'status' => 'failed',
            ]);
        }
    }
}