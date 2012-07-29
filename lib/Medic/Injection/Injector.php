<?php

/*
 * This file is part of the Medic-Injector package.
 *
 * (c) Olivier Philippon <https://github.com/DrBenton>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Medic\Injection;

use \Closure;
use Medic\Injection\Vo\InjectionVO;
use Medic\Injection\InjectionReader;

class Injector
{

    static protected $askedClassesRegistryNamedInjectionsSeparator = '->';

    protected $askedClassesRegistry = array();

    /**
     * @param string $whenAskedFor A PHP Interface or Class name
     * @param mixed $useValue Injected value
     * @param string|null $named Injection name
     */
    public function mapValue ($whenAskedFor, $useValue, $named = null)
    {
        $registryKey = $this->getRegistryKey($whenAskedFor, $named);
        $this->askedClassesRegistry[$registryKey] = $useValue;
    }

    /**
     * @param string $whenAskedFor A PHP Interface or Class name
     * @param string|null $instantiateClass A PHP Class name
     * @param string|null $named Injection name
     */
    public function mapClass ($whenAskedFor, $instantiateClass = null, $named = null)
    {
        $registryKey = $this->getRegistryKey($whenAskedFor, $named);
        if (null === $instantiateClass) {
            $instantiateClass = $whenAskedFor;
        }
        $this->askedClassesRegistry[$registryKey] = function() use ($instantiateClass) {
            return new $instantiateClass();
        };
    }

    /**
     * @param string $whenAskedFor A PHP Class name
     * @param string|null $named Injection name
     */
    public function mapSingleton ($whenAskedFor, $named = null)
    {
        $this->mapSingletonOf($whenAskedFor, $whenAskedFor, $named);
    }

    /**
     * @param string $whenAskedFor A PHP Interface or Class name
     * @param string $useSingletonOf A PHP Class name
     * @param string|null $named Injection name
     */
    public function mapSingletonOf ($whenAskedFor, $useSingletonOf, $named = null)
    {
        $registryKey = $this->getRegistryKey($whenAskedFor, $named);
        $this->askedClassesRegistry[$registryKey] = function() use ($useSingletonOf) {
            static $singletonInstance;
            if (null === $singletonInstance) {
                $singletonInstance = new $useSingletonOf();
            }

            return $singletonInstance;
        };
    }

    /**
     * Allows lazy-loading and easy custom initialization after instantiation on a Singleton injected instance.
     *
     * @param string $whenAskedFor A PHP Interface or Class name
     * @param \Closure $closure This Closure return value will be used as the injected value when this PHP Interface or Class injection is requested
     * @param string|null $named Injection name
     */
    public function mapSingletonThroughClosure ($whenAskedFor, Closure $closure, $named = null)
    {
        $registryKey = $this->getRegistryKey($whenAskedFor, $named);
        $this->askedClassesRegistry[$registryKey] = function() use ($closure) {
            static $singletonClosureResult;
            if (null === $singletonClosureResult) {
                $singletonClosureResult = call_user_func($closure);
            }

            return $singletonClosureResult;
        };
    }

    /**
     * Handles properties and setters injection on a Class instance.
     * Any injected property or setter parameter which implements \Medic\Injection\InjectionTarget will also be recursively
     * handled by this "injectTo()" method.
     *
     * @param object $classInstance
     */
    public function injectInto ($classInstance)
    {
        $injectionReader = new InjectionReader($classInstance);

        // Properties injections
        $propertiesToInject = $injectionReader->getInjectedProperties();
        foreach ($propertiesToInject as $property) {
            $injectionValue = $this->getInjectionValue($property, $classInstance);
            $classInstance->{$property->targetName} = $injectionValue;
        }

        // Setters injections
        $settersToInject = $injectionReader->getInjectedSetters();
        foreach ($settersToInject as $setter) {
            $injectionValue = $this->getInjectionValue($setter, $classInstance);
            call_user_func(array($classInstance, $setter->targetName), $injectionValue);
        }

    }

    /**
     * @param string $askedClass A PHP Interface or Class name
     * @param string|null $named Injection name
     * @return mixed
     */
    public function instantiate ($askedClass, $named = null)
    {
        $registryKey = $this->getRegistryKey($askedClass, $named);

        return $this->getRegistryKeyInjectionValue($registryKey);
    }

    /**
     * @param Medic\Injection\Vo\InjectionVO $injectionVO
     * @param object|null $classInstance
     * @return mixed
     */
    protected function getInjectionValue (InjectionVO $injectionVO, $classInstance = null)
    {
        $registryKey = $this->getRegistryKey($injectionVO->injectionVarType, $injectionVO->injectionName);
        // We check that we have such a mapping
        if (!isset($this->askedClassesRegistry[$registryKey]) && $injectionVO->mandatoryInjection) {
            $this->throwInjectionException($injectionVO, $classInstance);
        }

        return $this->getRegistryKeyInjectionValue($registryKey);
    }


    /**
     * @param string $registryKey
     * @return mixed
     */
    protected function getRegistryKeyInjectionValue ($registryKey)
    {
        if (!isset($this->askedClassesRegistry[$registryKey])) {
            return null;
        }

        // We retrieve the injection value
        $injectionValue = $this->askedClassesRegistry[$registryKey];
        // If the injected item is a PHP callable (for instance, a Closure), we trigger it
        if (is_callable($injectionValue)) {
            $injectionValue = call_user_func($injectionValue);
        }
        // If the injected item implements InjectionTarget, we first handle Injections recursively in this item
        if ($injectionValue instanceof InjectionTarget) {
            $this->injectInto($injectionValue);
        }

        return $injectionValue;
    }


    /**
     * @param Medic\Injection\Vo\InjectionVO $injection
     * @param object|null $classInstance
     * @throws InjectionException
     */
    protected function throwInjectionException (InjectionVO $injection, $classInstance = null)
    {
        $errMsg = 'Mandatory Injection asked for PHP type "'.$injection->injectionVarType.'"';
        if (null !== $injection->injectionName) {
            $errMsg .= ' and injection name "'.$injection->injectionName.'"';
        }
        if (null !== $classInstance) {
            $errMsg .= ' in class "'.get_class($classInstance).'"';
        }
        $errMsg .= ', but there is no matching injection mapping!';

        //print_r($this->askedClassesRegistry);
        throw new InjectionException($errMsg);
    }

    /**
     * @param $whenAskedFor
     * @param string|null $named
     * @return string
     */
    protected function getRegistryKey ($whenAskedFor, $named = null)
    {
        if ('\\' !== $whenAskedFor[0]) {
            $whenAskedFor = '\\' . $whenAskedFor;//we add a heading slash for consistency with all Injections mechanisms
        }

        return $whenAskedFor .
            ($named ? self::$askedClassesRegistryNamedInjectionsSeparator . $named : '');
    }

}