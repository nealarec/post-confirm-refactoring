<?php

require('Notifier.php');
require('Service.php');

// Class importing from wherever they stay

class Controller
{
    public function post_confirm()
    {
        $driver_id = Input::get('driver_id');
        $service_id = Input::get('service_id');
    
        $driver = Driver::find($driver_id);
        $service = Service::find($service_id);
    
        $validateStatus = Service::driverCanBeAssign($service);
    
        if($validateStatus === true) {
            $service = $service->assignDriver($driver);
            Notifier::notify($service->user, Service::$SERVICE_MESSAGE, array('serviceId' => $service->id));
            return Response::json(array('error' => Service::$NOT_ERROR));
        }
    
        return Response::json(array('error' =>  $validateStatus));
    }
}