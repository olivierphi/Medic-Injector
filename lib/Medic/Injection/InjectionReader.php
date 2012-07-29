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

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Medic\Injection\Vo\InjectionVO;

class InjectionReader
{

    // Parsing Regexp patterns
    protected static $COMMENT_PATTERN_ANNOTATION = '/^\s*\*\s*@([a-z])/i';
    protected static $ANNOTATION_PATTERN_INJECT = '/^Inject(Mandatory)?(?:\("([a-z0-9_]+)"\))?\s*$/i';
    protected static $ANNOTATION_PATTERN_VAR = '/^var\s+([^\s]+)/i';
    protected static $ANNOTATION_PATTERN_PARAM = '/^param\s+([^\s]+)/i';
    protected static $PHP_TYPE_PATTERN = '/^[\\a-z0-9_]+$/i';

    /**
     * @var string|object
     */
    protected $classToParse;

    /**
     * @var \ReflectionClass
     */
    private $reflectionClass;

    /**
     * @param string|object|null $classToParse
     */
    public function __construct ($classToParse = null)
    {
        $this->classToParse = $classToParse;
    }


    /**
     * @return InjectionVO[]
     */
    public function getInjectedProperties ()
    {
        $ret = array();
        $classProperties = $this->getReflectionClass()->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($classProperties as $property) {
            $propertyComments = $property->getDocComment();
            $propertyAnnotations = $this->getAnnotationsArray($propertyComments);
            $propertyInjection = $this->getInjectionVOFromAnnotations($propertyAnnotations);
            if (null !== $propertyInjection) {
                // @Inject annotation found ; let's look for a @var comment
                $varType = $this->getVarTypeFromAnnotations($propertyAnnotations, self::$ANNOTATION_PATTERN_VAR);
                if (null !== $varType) {
                    // This property has an @Inject annotation and a valid @var comment
                    // --> let's add it to our injected properties array
                    $propertyInjection->targetName = $property->getName();
                    $propertyInjection->injectionVarType = $this->normalizeInjectionType($varType);
                    $ret[] = $propertyInjection;

                }
            }
        }

        return $ret;
    }

    /**
     * @return InjectionVO[]
     */
    public function getInjectedSetters ()
    {
        $ret = array();
        $classMethods = $this->getReflectionClass()->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($classMethods as $method) {
            if (1 !== $method->getNumberOfParameters()) {
                continue;//we only want setters, with one parameter
            }
            $methodComments = $method->getDocComment();
            $methodAnnotations = $this->getAnnotationsArray($methodComments);
            $methodInjection = $this->getInjectionVOFromAnnotations($methodAnnotations);
            if (null !== $methodInjection) {
                // @Inject annotation found ; let's look for a @param comment
                $paramType = $this->getVarTypeFromAnnotations($methodAnnotations, self::$ANNOTATION_PATTERN_PARAM);
                if (null === $paramType) {
                    // No @param annotation found ; let's see if the setter parameter is typed
                    $parameters = $method->getParameters();
                    $setterParameter = $parameters[0];
                    $setterParameterClass = $setterParameter->getClass();
                    if (null !== $setterParameterClass) {
                        $paramType = $setterParameterClass->name;
                    }
                }
                if (null !== $paramType) {
                   $methodInjection->targetName = $method->getName();
                   $methodInjection->injectionVarType = $this->normalizeInjectionType($paramType);
                   $ret[] = $methodInjection;
                }
            }
        }

        return $ret;
    }

    /**
     * @param mixed $callable
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getParamsTypes ($callable)
    {
        if (!is_callable($callable, true)) {
            throw new \InvalidArgumentException('Method "getInjectedParams()" expects a PHP callable (class method, function or Closure) but got "'.$callable.'"!');
        }

        $ret = array();

        // Reflection base class initialization, depending on $callable type
        if (is_array($callable)) {

            // This is a ['ClassName', 'methodName'] array
            $classNameOrInstance = $callable[0];
            $methodName = $callable[1];
            $classReflection = new \ReflectionClass($classNameOrInstance);
            $callableReflection = $classReflection->getMethod($methodName);

        } else if (is_string($callable) && strpos($callable, '::') > 1) {

            // This is a 'ClassName::methodName' string
            list($className, $methodName) = explode('::', $callable);
            $classReflection = new \ReflectionClass($className);
            $callableReflection = $classReflection->getMethod($methodName);

        } else if (is_object($callable) || (is_string($callable) && function_exists($callable))) {

            // This is a function or a Closure
            $callableReflection = new \ReflectionFunction($callable);

        } else {
            throw new \InvalidArgumentException('Unable to resolve method "getInjectedParams()" PHP callable "'.$callable.'"!');
        }

        // Ok, let's extract the parameters types
        $parameters = $callableReflection->getParameters();

        foreach ($parameters as $parameter) {
            $setterParameterClass = $parameter->getClass();
            if (null !== $setterParameterClass) {
                $paramType = $setterParameterClass->name;
                $ret[] = $this->normalizeInjectionType($paramType);
            }
        }

        return $ret;
    }

    /**
     * @param string $commentsStr
     * @return string[]
     */
    protected function getAnnotationsArray ($commentsStr)
    {
        $annotationsArray = array();
        $rawCommentsArray = preg_split('/$/m', $commentsStr);
        foreach ($rawCommentsArray as $commentLine) {
            $count = 0;
            $commentLine = preg_replace(self::$COMMENT_PATTERN_ANNOTATION, '$1', $commentLine, 1, $count);
            if (1 === $count) {
                $annotationsArray[] = $commentLine;
            }
        }

        return $annotationsArray;
    }

    /**
     * @param array $annotationsArray
     * @return null|Vo\InjectionVO
     */
    protected function getInjectionVOFromAnnotations (array $annotationsArray)
    {
        foreach ($annotationsArray as $annotationLine) {

            if (preg_match(self::$ANNOTATION_PATTERN_INJECT, $annotationLine, $matches)) {
                $injectionVO = new InjectionVO();
                if (3 === sizeof($matches)) {
                    $injectionVO->mandatoryInjection = ('' === $matches[1]) ? false : true ;
                    $injectionVO->injectionName = $matches[2];
                }

                return $injectionVO;
            }

        }

        return null;
    }

    /**
     * @param array $annotationsArray
     * @param string $typeDescriptionPattern
     * @return null|string
     */
    protected function getVarTypeFromAnnotations (array $annotationsArray, $typeDescriptionPattern)
    {
        foreach ($annotationsArray as $annotationLine) {

            if (preg_match($typeDescriptionPattern, $annotationLine, $matches) && 2 === sizeof($matches)) {

                if (preg_match(self::$PHP_TYPE_PATTERN, $matches[1])) {

                    return $matches[1];
                }
            }

        }

        return null;
    }

    /**
     * @param string $injectionType
     * @return string
     */
    protected function normalizeInjectionType ($injectionType)
    {
        if ('\\' !== $injectionType[0]) {
            $injectionType = '\\' . $injectionType;//we add a heading slash for consistency with all Injections mechanisms
        }

        return $injectionType;
    }


    /**
     * @return \ReflectionClass
     */
    protected function getReflectionClass ()
    {
        if (null === $this->reflectionClass) {
            $this->reflectionClass = new ReflectionClass($this->classToParse);
        }

        return $this->reflectionClass;
    }

}
