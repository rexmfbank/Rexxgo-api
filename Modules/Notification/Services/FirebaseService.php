<?php
namespace Modules\Notification\Services;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Contract\Messaging;

class FirebaseService
{
    protected Messaging $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Send push notification to a device
     *
     * @param string $token Device FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Optional data payload
     */
    public function sendPush(string $token, string $title, string $body, array $data = [])
    {
        $notification = Notification::create($title, $body);

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData($data);

        return $this->messaging->send($message);
        return "";
    }
}
