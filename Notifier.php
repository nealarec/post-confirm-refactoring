<?php

// use Wherever\Push\Stay\Push or require('Wherever/Push/Stay/Push.php')

class Notifier
{
    public static $IOS_NOTIFICATION = 1;

    public function __constructor()
    {
        $this->transport = Push::make();
    }

    public static function notify($user, $message, $data)
    {
        if (empty($user->uuid)) {
            return false;
        }

        try {
            $instance = new static();
            return $instance->send($user->type, $user->uuid, $message, $data);
        } catch (Exception $e) {
            // Control of Exception
            return false;
        }
    }

    public function send($type, $uuid, $message, $data)
    {
        switch ($type) {
            case static::$IOS_NOTIFICATION:
                return $this->sendIOs($uuid, $message, $data);
            default:
                return $this->sendAndroid($uuid, $message, $data);

        }
    }

    public function sendAndroid($uuid, $message, $data)
    {
        return $this->transport->android($uuid, $message, 1, 'default', 'Open', $data);
    }

    public function sendIOs($uuid, $message, $data)
    {
        return $this->transport->ios($uuid, $message, 1, 'honk.wav', 'Open', $data);
    }
}