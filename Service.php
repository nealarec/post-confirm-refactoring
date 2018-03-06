<?php

// namespace MyPersonal\Namespace if it's required 

class Service extends OriginalSystemServiceClass
{
    public static $SERVICE_NOT_FUND = '3';
    public static $WRONG_SERVICE_STATUS = '2';
    public static $PRECONDITION_FAILED = '1';
    public static $NOT_ERROR = 0;

    public static $SERVICE_MESSAGE = '¡Tu servicio ha sido confirmado!';

    public static function driverCanBeAssign($service)
    {
        if ($service === null) {
            return static::$SERVICE_NOT_FUND;
        }

        if ($service->status_id == '6') {
            return static::$WRONG_SERVICE_STATUS;
        }

        if ($service->driver_id !== null || $service->status_id !== '1') {
            return static::$PRECONDITION_FAILED;
        }

        return true;
    }

    public function assignDriver($driver)
    {
        return static::update($this->id, array(
            "driver_id" => $driver->id,
            "car_id" => $driver->car_id,
            "status_id" => 2
        ));
    }
}