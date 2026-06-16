<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Twilio\Rest\Client;
use App\Models\Notification;

class SendSMSNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public $userId,
        public $message,
        public $phone
    ) {}

    public function handle()
    {
        try {
            $sid = config('notifications.twilio_sid');
            $token = config('notifications.twilio_token');
            $fromPhone = config('notifications.twilio_phone');

            if (!$sid || !$token || !$fromPhone) {
                throw new \Exception('Twilio credentials not configured');
            }

            $twilio = new Client($sid, $token);
            $twilio->messages->create($this->phone, [
                'from' => $fromPhone,
                'body' => $this->message
            ]);

            Notification::create([
                'user_id' => $this->userId,
                'channel' => 'sms',
                'recipient' => $this->phone,
                'body' => $this->message,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('SMS notification failed: ' . $e->getMessage());
            Notification::create([
                'user_id' => $this->userId,
                'channel' => 'sms',
                'recipient' => $this->phone,
                'body' => $this->message,
                'status' => 'failed',
            ]);
        }
    }
}