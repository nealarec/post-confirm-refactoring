### Código Original ### 

```php
public function post_confirm() {
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
            $pushMessage = '¡Tu servicio ha sido confirmado!';
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
                $pushAns = $push->ios($servicio->user->uuid, $pushMessage, 1, 'honk.wav', 'Open', array('serviceId' => $servicio->id));
            } else {
                $pushAns = $push->android($servicio->user->uuid, $pushMessage, 1, 'default', 'Open', array('serviceId' => $servicio->id));
            }
            return Response::json(array('error' => '0'));
        } else {
            return Response::json(array('error' => '1'));
        }
    } else {
        return Response::json(array('error' => '3'));
    }
}
```

#### Análisis #### 

Una de las malas prácticas más evidente en el código anterior es 
el exceso de singularidad de la función, si bien estamos realizando 
un análisis en un un fragmento de código aislado, no podemos olvidar que 
este código hace parte de un sistema. Una función que hace muchas cosas, en 
esencia, genera problemas al momento de la integración, ya que implementa 
funcionalidades que no son de su competencia, lo que obliga a la repetición
de código y dificulta la automatización de pruebas y los proceso de 
actualización o modificación de estas funcionalidades. 

el ejemplo más cercano que tenemos de lo anterior en el código es el 
fragmento que envía la notificación al usuario: 

```php
$push = Push::make();
if ($servicio->user->uuid == '') {
    return Response::json(array('error' => '0'));
}
if ($servicio->user->type == '1') {
    $pushAns = $push->ios($servicio->user->uuid, $pushMessage, 1, 'honk.wav', 'Open', array('serviceId' => $servicio->id));
} else {
    $pushAns = $push->android($servicio->user->uuid, $pushMessage, 1, 'default', 'Open', array('serviceId' => $servicio->id));
}
```


Si bien parte de la funcionalidad definida es enviar dicha notificación,
no hace parte del dominio de la función decidir cómo hacerlo,
debido a que cuando otra función del sistema quiera notificar algo al usuario 
se debería repetir esta decisión, lo cual incrementa el volumen de código 
y divide la funcionalidad en N versiones de la misma,lo que 
dificulta el mantenimiento de la característica. 

Una buena práctica que contrarresta lo anterior es el encapsulamiento,
que permite llevar una característica o funcionalidad al interior 
de una clase para que sea administrada, si bien esta funcionalidad puede ser 
una extensión de la funcionalidad usuario, para efectos prácticos crearemos
una clase totalmente independiente que solo contenga esta funcionalidad. 

```php 
// Notifier.php

<?php

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
            case static::IOS_NOTIFICATION:
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
```

Ahora cualquier función del sistema que quiera notificar a un
usuario de un suceso solo tendrá que usar la interface 

```php
Notifier::notify($user, $message, $data);
```

Para nuestro caso práctico sería:

```php
$pushData = array('serviceId' => $servicio->id);
Notifier::notify($servicio->user, $pushMessage, $pushData);
```

> Cabe anotar que el condicional ```if ($servicio->user->uuid == '')``` 
> se encuentra interno en la función ```Notifier::notify``` y no cambia 
> su valor de retorno, por tanto no es necesario conservarlo 
> dentro de la misma, en caso de ser necesario se podría validar el estado 
> de la notificación con el valor devuelto por ```Notifier::notify```

> `Warning:` la variable `$pushAns` nunca fue usada, por tanto no es tenida 
> en cuenta, aún cuando sería el valor de retorno de `Notifier::notify` en caso
> de que el envío sea exitoso.

Después de este cambio nuestro código quedaría así:

```php
public function post_confirm() {
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
            $pushMessage = '¡Tu servicio ha sido confirmado!';
            $pushData =  array('serviceId' => $servicio->id);
            Notifier::notify($servicio->user, $pushMessage, $pushData);
            
            return Response::json(array('error' => '0'));
        } else {
            return Response::json(array('error' => '1'));
        }
    } else {
        return Response::json(array('error' => '3'));
    }
}
```

Otra mala práctica presente en el código se hace evidente al momento 
de cambiar el estado del servicio, pues se realizan multiples accesos a la
base de datos cuando es suficiente con uno.

```php
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
```

Se podría reemplazar por: 

```php
$driverTmp = Driver::find(Input::get('driver_id'));

$servicio = Service::update($id, array(
    "driver_id" => Input::get('driver_id'),
    "car_id" => $driverTmp->car_id,
    "status_id" => 2
));
```

Después de este cambio nuestro código quedaría así:

```php
public function post_confirm() {
    $id = Input::get('service_id');
    $servicio = Service::find($id);
    //dd($servicio);
    if ($servicio != null) {
        if ($servicio->status_id == '6') {
            return Response::json(array('error' => '2'));
        }
        if ($servicio->driver_id == null && $servicio->status_id == '1') {
            $driverTmp = Driver::find(Input::get('driver_id'));

            $servicio = Service::update($id, array(
                "driver_id" => Input::get('driver_id'),
                "car_id" => $driverTmp->car_id,
                "status_id" => 2
            ));

            // Notificar al usuario
            $pushMessage = '¡Tu servicio ha sido confirmado!';
            $pushData =  array('serviceId' => $servicio->id);
            Notifier::notify($servicio->user, $pushMessage, $pushData);
            
            return Response::json(array('error' => '0'));
        } else {
            return Response::json(array('error' => '1'));
        }
    } else {
        return Response::json(array('error' => '3'));
    }
}
```

Para mejorar la legibilidad del código se podría realizar un cambio de orden en el proceso 
de validación con el objeto de aclarar la razón por la cual falló la petición. 

```php
public function post_confirm() {
    $id = Input::get('service_id');
    $servicio = Service::find($id);

    // Inicio del proceso de validación

    if ($servicio === null) {
        return Response::json(array('error' => '3'));
    }

    if ($servicio->status_id == '6') {
        return Response::json(array('error' => '2'));
    }

    if ($servicio->driver_id !== null || $servicio->status_id !== '1') {
        return Response::json(array('error' => '1'));
    }

    // Fin del proceso de validación

    $driverTmp = Driver::find(Input::get('driver_id'));

    // Cambio de estado del servicio
    $servicio = Service::update($id, array(
        "driver_id" => Input::get('driver_id'),
        "car_id" => $driverTmp->car_id,
        "status_id" => 2
    ));

    // Notificar al usuario
    $pushMessage = '¡Tu servicio ha sido confirmado!';
    $pushData =  array('serviceId' => $servicio->id);
    Notifier::notify($servicio->user, $pushMessage, $pushData);
    
    return Response::json(array('error' => '0'));
}
```

Finalmente, realizaría un ajuste de coherencia y definición de variables 
con el propósito de que el código sea mas claro, acompañado 
de la traducción del código a un único lenguaje. 

```php
// file header
define('SERVICE_NOT_FUND', '3');
define('WRONG_SERVICE_STATUS', '2');
define('PRECONDITION_FAILED', '1');
define('NOT_ERROR', '0')

define('SERVICE_MESSAGE', '¡Tu servicio ha sido confirmado!')

''''
// function body
public function post_confirm()
{
    $driver_id = Input::get('driver_id');
    $service_id = Input::get('service_id');

    $driver = Driver::find($driver_id);
    $service = Service::find($service_id);

    // Start of validation process 
    if ($service === null) {
        return Response::json(array('error' => SERVICE_NOT_FUND));
    }

    if ($service->status_id == '6') {
        return Response::json(array('error' => WRONG_SERVICE_STATUS));
    }

    if ($service->driver_id !== null || $service->status_id !== '1') {
        return Response::json(array('error' => PRECONDITION_FAILED));
    }

    // End of validation process 


    // Change of service status 
    $service = Service::update($id, array(
        "driver_id" => $driver->id,
        "car_id" => $driver->car_id,
        "status_id" => 2
    ));

    // Send user notification
    $pushMessage = ;
    $pushData = array('serviceId' => $service->id);
    Notifier::notify($service->user, SERVICE_MESSAGE, $pushData);

    return Response::json(array('error' => NOT_ERROR));
}
''''
// rest of controller
```

Es importante resaltar que con más conocimiento sobre el funcionamiento interno del 
sistema, podríamos estandarizar más aún nuestro método, al punto de extraer
todas las responsabilidades externas a el, incluido el proceso de validación 
que puede ser común con otros métodos o procesos.

En una fase completa del refactor en el sistema original el código de nuestro controlador
sería el siguiente:


```php

''''
// function body
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
''''
// into Service Model class

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
''''
// Notifier.php

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
'''''
```