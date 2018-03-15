<?php

namespace NotificationChannels\WebPush;

use Minishlink\WebPush\WebPush;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WebPushChannel
{
    /** @var \Minishlink\WebPush\WebPush */
    protected $webPush;

    /**
     * @param  \Minishlink\WebPush\WebPush $webPush
     * @return void
     */
    public function __construct(WebPush $webPush)
    {
        $this->webPush = $webPush;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed $notifiable
     * @param  \Illuminate\Notifications\Notification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $subscriptions = $notifiable->routeNotificationFor('WebPush');

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toWebPush($notifiable, $notification)->toArray());

        $subscriptions->each(function ($sub) use ($payload, $notification) {
            $this->webPush->sendNotification(
                $sub->endpoint,
                $payload,
                $sub->public_key,
                $sub->auth_token,
                false,
                $notification->options
            );
        });

        $response = $this->webPush->flush();

        if (is_array($response)) {
            Log::error('Responses: ' . json_encode($response));
            Log::error('Subscriptions: ' . json_encode($subscriptions));
        }

        $this->deleteInvalidSubscriptions($response, $subscriptions);
    }

    /**
     * @param  array|bool $response
     * @param  \Illuminate\Database\Eloquent\Collection $subscriptions
     * @return void
     */
    protected function deleteInvalidSubscriptions($response, $subscriptions)
    {
        if (! is_array($response)) {
            return;
        }

        foreach ($response as $index => $value) {
            if (! $value['success'] && isset($subscriptions[$index])) {
                // Remove push data, but keep record to track unsubscribe by updated_at date
                $subscriptions[$index]->endpoint = null;
                $subscriptions[$index]->public_key = null;
                $subscriptions[$index]->auth_token = null;
                $subscriptions[$index]->save();
                // $subscriptions[$index]->delete();
            }
        }
    }
}
