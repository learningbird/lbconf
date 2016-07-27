<?php
namespace LbConf\Config;

use Exceptions\Collection\KeyNotFoundException;

class Properties
{
    public static function has($data, $prop): bool
    {
        $isObject = is_object($data);
        $isArray  = is_array($data);

        if (!$isObject && !$isArray) {
            throw new KeyNotFoundException("Data must be either object or array");
        }

        return ($isObject && property_exists($data, $prop)) || ($isArray && array_key_exists($prop, $data));
    }

    public static function &get(&$data, $prop)
    {
        if (!self::has($data, $prop)) {
            throw new KeyNotFoundException("Could not find property '$prop'");
        }

        if (is_object($data)) {
            return $data->$prop;
        } else {
            return $data[$prop];
        }
    }

    public static function set(&$data, $prop, $value)
    {
        $isObject = is_object($data);
        $isArray  = is_array($data);

        if ($isObject) {
            $data->$prop = $value;
        } elseif ($isArray) {
            $data[$prop] = $value;
        } else {
            throw new KeyNotFoundException("Data must be either object or array");
        }
    }

    public static function unset(&$data, $prop)
    {
        if (!self::has($data, $prop)) {
            throw new KeyNotFoundException("Could not find property '$prop'");
        }

        $isObject = is_object($data);
        $isArray  = is_array($data);

        if ($isObject) {
            unset($data->$prop);
        } elseif ($isArray) {
            unset($data[$prop]);
        }
    }
}