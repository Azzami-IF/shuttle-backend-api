<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Notification;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Trip;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public $userId,
        public $subject,
        public $viewName,
        public $data = []
    ) {}

    public function handle()
    {
        $user = User::find($this->userId);
        if (!$user) return;

        try {
            // Rehydrate nested models in data in case they were serialized as arrays
            $data = $this->data;
            if (isset($data['booking']) && is_array($data['booking']) && isset($data['booking']['id'])) {
                $data['booking'] = Booking::with(['user', 'seat'])->find($data['booking']['id']);
            }
            if (isset($data['schedule']) && is_array($data['schedule']) && isset($data['schedule']['id'])) {
                $data['schedule'] = Schedule::with(['driver', 'vehicle'])->find($data['schedule']['id']);
            }
            if (isset($data['trip']) && is_array($data['trip']) && isset($data['trip']['id'])) {
                $data['trip'] = Trip::with(['schedule.driver', 'schedule.vehicle', 'locations'])->find($data['trip']['id']);
            }

            Mail::to($user->email)
                ->send(new \App\Mail\NotificationMail(
                    $this->subject,
                    $this->viewName,
                    $data
                ));

            Notification::create([
                'user_id' => $this->userId,
                'type' => $data['type'] ?? 'generic',
                'channel' => 'email',
                'recipient' => $user->email,
                'subject' => $this->subject,
                'body' => $data['message'] ?? '',
                'data' => $data,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Email notification failed: ' . $e->getMessage());
            Notification::create([
                'user_id' => $this->userId,
                'type' => $this->data['type'] ?? 'generic',
                'channel' => 'email',
                'recipient' => $user->email,
                'subject' => $this->subject,
                'status' => 'failed',
            ]);
        }
    }
}