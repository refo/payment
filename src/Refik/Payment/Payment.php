<?php namespace Refik\Payment;

use Exception;
use Config;

class Payment {


    public static function instance($gateway, Array $userConfig = [])
    {
        $ns    = __NAMESPACE__;
        $class = $ns . '\\Gateway\\' . ucfirst($gateway);
        if (class_exists($class)) {

            // Merge user supplied configuration with laravel app configuration
            $appConfig = Config::get('payment.' . $gateway, []);
            $config = array_merge($appConfig, $userConfig);

            $configIsValid = (bool)(
                ! empty($config) AND
                isset($config['endpoint']) AND
                ! empty($config['endpoint'])
            );

            if (! $configIsValid) throw new Exception(sprintf('Cannot initialize "%s" payment gateway. A valid configuration should be provided.', $gateway), 1);
            
            
            // Instanciate, initiate and return the gateway
            $gateway = new $class;
            $gateway->init($config);
            return $gateway;

        } else {
            throw new Exception(sprintf('Payment gateway "%s" not found', $gateway), 1);
        }

    }    
}
