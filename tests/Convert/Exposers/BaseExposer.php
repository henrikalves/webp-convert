<?php

namespace WebPConvert\Tests\Convert\Exposers;

use WebPConvert\Convert\BaseConverters\AbstractConverter;

/**
 * Class for exposing otherwise unaccessible methods of classes
 * - so they can be tested
 */
class BaseExposer {

    //public static $extraOptions = [];

    public $objectToExposeFrom;
    public static $currentlyCalling;
    public static $currentlyStealing;

    public function __construct($objectToExposeFrom)
    {
        $this->objectToExposeFrom = $objectToExposeFrom;
    }

    protected function bindDynamicFunctionToObjectAndCallIt($functionToBindToObject, $class = null)
    {
        if (is_null($class)) {
            $class = get_class($this->objectToExposeFrom);
        }
        //$functionNowBinded = $functionToBindToObject->bindTo($this->objectToExposeFrom, AbstractConverter::class);
        $functionNowBinded = $functionToBindToObject->bindTo($this->objectToExposeFrom, $class);
        //$functionNowBinded = $functionToBindToObject->bindTo($this->objectToExposeFrom, get_class($this->objectToExposeFrom));
        return $functionNowBinded();
    }

    /**
     * @param string $functionNameToCall
     * @param string $class The class to inject into, ie a base class of the object to expose from (optional). If none is specified, it will be the class of the exposed object
     */
    protected function callPrivateFunction($functionNameToCall, $class = null)
    {
        self::$currentlyCalling = $functionNameToCall;

        $cb = function() {
            return call_user_func_array(array($this, BaseExposer::$currentlyCalling), func_get_args());
        };
        return $this->bindDynamicFunctionToObjectAndCallIt($cb, $class);
    }

    /**
     * @param string $propertyToSteal
     */
    protected function getPrivateProperty($propertyToSteal, $class = null)
    {
        self::$currentlyStealing = $propertyToSteal;

        $thief = function() {
            return $this->{BaseExposer::$currentlyStealing};
        };

        return $this->bindDynamicFunctionToObjectAndCallIt($thief, $class);
    }

    /**
     * @param string $propertyToSteal
     */
    protected function getPrivateStaticProperty($propertyToSteal, $class = null)
    {
        self::$currentlyStealing = $propertyToSteal;

        $thief = function() {
            $propertyName = BaseExposer::$currentlyStealing;
            return static::$$propertyName;
        };

        return $this->bindDynamicFunctionToObjectAndCallIt($thief, $class);
    }


}
