<?php

class Controller
{
    public function post_confirm()
    {
        $id = Input::get('service_id');
        $servicio = Service::find($id);
        //dd($servicio);
        if ($servicio != null) {
            if ($servicio->status_id == '6') {
                return Response::json(array('error' => '2'));
            }
            if ($servicio->driver_id == null && $servicio->status_id == '1') {
                $servicio = Service::update($id, array(
                    "driver_id" => Input::get('driver_id'),
                    "status_id" => 2
                    // Up Carro
                    //, 'pwd' => md5(Input::get('pwd'))
                ));
                $driverTmp = Driver::find(Input::get('driver_id'));
                Service::update($id, array(
                    "car_id" => $driverTmp->car_id
                    // Up Carro
                    //, 'pwd' => md5(Input::get('pwd'))
                ));
                // Notificar al usuario
                $pushMessage = 'Tu servicio ha sido confirmado!';
                /* $servicio=Service::find($id);
                $push = Push::make();
                if($servicio->user->type = '1') {
                $pushAns = $push->ios($servicio->user->uuid, $pushMessage);
                } else {
                $pushAns = $push->android($servicio->user->uuid, $pushMessage);
                } */
                $servicio = Service::find($id);
                $push = Push::make();
                if ($servicio->user->uuid == '') {
                    return Response::json(array('error' => '0'));
                }
                if ($servicio->user->type == '1') {
                    $pushAns = $push->ios($servicio->user->uuid, $pushMessage, 1, 'honk.wav', 'Open', array('serviceId', $servicio->id));
                } else {
                    $pushAns = $push->android($servicio->user->uuid, $pushMessage, 1, 'default', 'Open', array('serviceId', $servicio->id));
                }
                return Response::json(array('error' => '0'));
            } else {
                return Response::json(array('error' => '1'));
            }
        } else {
            return Response::json(array('error' => '3'));
        }
    }
}