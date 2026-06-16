<?php

namespace App\Services;

use Illuminate\Support\Facades\Queue;
use Exception;

class NotificationService
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_PUSH = 'push';

    /**
     * Send notification through multiple channels
     */
    public static function send($userId, $message, $channels = ['email'], $data = [])
    {
        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    self::CHANNEL_EMAIL => self::sendEmail($userId, $message, $data),
                    self::CHANNEL_SMS => self::sendSMS($userId, $message, $data),
                    self::CHANNEL_PUSH => self::sendPush($userId, $message, $data),
                    default => null
                };
            } catch (Exception $e) {
                \Log::error("Notification failed for {$channel}: " . $e->getMessage());
            }
        }
    }

    /**
     * Send email notification
     */
    private static function sendEmail($userId, $message, $data = [])
    {
        Queue::push(new \App\Jobs\SendEmailNotification(
            $userId,
            $data['subject'] ?? 'Notification',
            $data['view'] ?? 'emails.notification',
            $data
        ));
    }

    /**
     * Send SMS notification
     */
    private static function sendSMS($userId, $message, $data = [])
    {
        $user = \App\Models\User::find($userId);
        if ($user && $user->phone) {
            Queue::push(new \App\Jobs\SendSMSNotification(
                $userId,
                $message,
                $user->phone
            ));
        }
    }

    /**
     * Send push notification
     */
    private static function sendPush($userId, $message, $data = [])
    {
        Queue::push(new \App\Jobs\SendPushNotification(
            $userId,
            $data['title'] ?? 'Notification',
            $message,
            $data
        ));
    }

    /**
     * Send booking confirmation notification
     */
    public static function notifyBookingConfirmation($booking)
    {
        $user = $booking->user;
        $channels = ['email'];

        if ($user->phone) {
            $channels[] = 'sms';
        }

        self::send($user->id, 'Your booking has been confirmed!', $channels, [
            'subject' => 'Booking Confirmation',
            'view' => 'emails.booking-confirmation',
            'title' => 'Booking Confirmed',
            'booking' => $booking,
        ]);
    }

    /**
     * Send booking cancellation notification
     */
    public static function notifyBookingCancellation($booking)
    {
        $user = $booking->user;
        $channels = ['email'];

        if ($user->phone) {
            $channels[] = 'sms';
        }

        self::send($user->id, 'Your booking has been cancelled.', $channels, [
            'subject' => 'Booking Cancellation',
            'view' => 'emails.booking-cancellation',
            'title' => 'Booking Cancelled',
            'booking' => $booking,
        ]);
    }

    /**
     * Send trip update notification
     */
    public static function notifyTripUpdate($booking, $updateMessage)
    {
        $user = $booking->user;
        $channels = ['email'];

        if ($user->phone) {
            $channels[] = 'sms';
        }

        self::send($user->id, $updateMessage, $channels, [
            'subject' => 'Trip Update',
            'view' => 'emails.trip-update',
            'title' => 'Trip Update',
            'message' => $updateMessage,
            'booking' => $booking,
        ]);
    }
}