# Medic Dependency Injector

Medic Injector is a lightweight PHP 5.3+ Dependency Injection utility.

    At the simplest, Dependency Injection is that act of supplying objects with their instance variables or properties.
    When you pass a variable to the constructor of a class, you are using Dependency Injection.
    When you set a property on a class, you are using Dependency Injection.
    If you aren't coding your PHP in a strictly procedural or linear fashion, the odds are that you are making use
    of Dependency Injection right now.

Medic Injector uses automated annotations-based Dependency Injection. This is provided as a convenience for the developer and has the advantage of greatly reducing the amount of code needed to wire together an application and provide classes with their necessary dependencies.

The Medic Injector is heavily inspired by the great [RobotLegs](https://github.com/robotlegs/robotlegs-framework/tree/master) ActionScript framework and its [SwiftSuspenders](https://github.com/tschneidereit/SwiftSuspenders) Injector.

Coding in ActionScript with Robotlegs is a great pleasure, and I wanted to keep the same pleasure in my PHP projects.
I hope you will enjoy programming with this "RobotLegs style" too! :-)

This README struture and content are an adaptation of the RobotLegs "[Best Practices](https://github.com/robotlegs/robotlegs-framework/wiki/Best-Practices)" wiki page.


## Using Injector

Medic Injector provides a simple mechanism for providing a dependency injection mechanism to any PHP class.

### Injection Syntax

Medic Injector supports two types of dependency injection.

* Property (field) Injection
* Parameter (method/setter) Injection

For a given PHP class to benefit dependency injection, you juste have to add some ```@Inject``` annotations on the
properties and setters you want to be automaticaly injected:

```php
class MyClass
{

    /**
     * @Inject
     * @var \SomeVendor\SomeClass
     */
    public $myDependency;// property injection, based on @var annotation

    /**
     * @Inject
     * @param \SomeVendor\SomeClass $myDependency my protected dependency
     */
    public function setAnotherDepency ($myDependency)
    {
        // simple setter injection, based on @param annotation
        $this->myProtectedDependency = $myDependency;
    }

    /**
     * @Inject
     */
    public function setLogger (Logger $myTypedDependency)
    {
        // setter injection without @param annotation : the dependency type will be determined via the setter parameter type
        $this->logger = $myTypedDependency;
    }

}
```

As you can see, injection is very simple : you provide a property type as you usually do with a ```@var Type``` annotation, and
you just add a ```@Inject``` additional annotation to tell the Injector that you want this property value to be
automatically filled.

All you have to do is to first tell the Injector which values will be injected for each injection PHP type : this is the
Injection Mapping. This is explained in the next chapter.

For the purposes of this document, we are going to focus on Property injection. Setters injections just behave the same,
as you can see in the previous code sample.


### Injection Mapping with the Injector Class

Below are the five mapping methods that you can use with the Medic Injector:

#### mapValue

```mapValue``` is used to map a specific instance of an object to an Injector. When asked for a specific class, use this specific instance of the class for injection.

```
// someplace in your application where mapping/configuration occurs
$myClassInstance = new MyClass();
$injector->mapValue('MyClass', $myClassInstance);
//This works with PHP 5.3 namespaced classes too : $injector->mapValue('\Vendor\Core\MyClass', $myClassInstance);
```
```
// in the class to receive injections
/**
 * @Inject
 * @var MyClass
 */
public $myClassSharedInstance;
```

The instance of MyClass is created and is held waiting to be injected when requested. When it is requested, that instance is used to fill the injection request.

Here is the signature of this Injector method:
```
/**
 * @param string $whenAskedFor A PHP Interface or Class name
 * @param mixed $useValue Injected value
 * @param string|null $named Injection name
 */
function mapValue ($whenAskedFor, $useValue, $named = null)
```

If the mapped value is a class instance which itself use injected dependencies, it has to implement the ```Medic\Injection\InjectorTarget``` Interface. This allows the Injector to know that it should seek for dependencies to inject in this class instance.

```Medic\Injection\InjectorTarget``` is an empty PHP Interface: you don't have to implement any additional method in your class. It's only used by the Medic Injector to recognize a class that request injections.

If you can't or don't want to implement ```Medic\Injection\InjectorTarget```, you can also trigger a dependencies injection manually via the Injector:

```
$injector->injectInto($myClassInstance);
```

This will provide the instance with its mapped injectable properties immediately.

You may have noticed the ```$named``` parameter : we will explain named injections later in this document.


#### mapClass

```mapClass``` provides a _new_ instance of the mapped class for each injection request.

```
// someplace in your application where mapping/configuration occurs
$injector->mapClass('MyClass', 'MyClass');
```

```
// in the first class to receive injections
/**
 * @Inject
 * @var MyClass
 */
public $myClassNewInstance;
```

```
// in the second class to receive injections
/**
 * @Inject
 * @var MyClass
 */
public $myClassNewInstance;
```

Each of the injections above will provide a _new_ instance of MyClass to fulfill the request.

Here is the signature of this Injector method:
```
/**
 * @param string $whenAskedFor A PHP Interface or Class name
 * @param string|null $instantiateClass A PHP Class name
 * @param string|null $named Injection name
 */
function mapClass ($whenAskedFor, $instantiateClass = null, $named = null)
```

You may also provide a PHP Interface or abstract class name instead of its implementation class name. In this case, the first parameter
for ```mapClass``` is the Interface / abstract class name, and the second one is the implementation class name :
```
// someplace in your application where mapping/configuration occurs
$injector->mapClass('SerializerInterface', 'Serializer');//Serializer implements SerializerInterface
```

```
// in the class to receive injections
/**
 * @Inject
 * @var SerializerInterface
 */
public $serializer;// a new instance of 'Serializer' will be injected in this property
```


The Medic Injector provides a method for instantiating mapped objects:

```
$injector->mapClass('MyClassInterface', 'MyClass');
$myClassInstance = $injector->instantiate('MyClassInterface');
```

This provides an instance of your object and all mapped injection points contained in the object are filled.

#### mapSingleton

```mapSingleton``` provides a _unique_, _shared_ instance of the requested class for every injection.
Providing a single instance of a class across all injections ensures that you maintain a consistent state and don't
create unnecessary instances of the injected class.

This is a managed single instance, enforced by the framework, and not a Singleton enforced within the class itself.

```
// someplace in your application where mapping/configuration occurs
$injector->mapSingleton('MyClass');
```

```
// in the first class to receive injections
/**
 * @Inject
 * @var MyClass
 */
public $myClassSharedInstance;
```

```
// in the second class to receive injections
/**
 * @Inject
 * @var MyClass
 */
public $myClassSharedInstance;
```

In the above example, both injections requests will be filled with the same instance of the requested class.

_This injection is deferred_, meaning the object is not instantiated until it is first requested.

Here is the signature of this Injector method:
```
/**
 * @param string $whenAskedFor A PHP Class name
 * @param string|null $named Injection name
 */
function mapSingleton ($whenAskedFor, $named = null)
```


#### mapSingletonOf

```mapSingletonOf``` is much like ```mapSingleton``` in functionality.
It is useful for mapping abstract classes and interfaces, where ```mapSingleton``` is for mapping concrete class implementations.

```
// someplace in your application where mapping/configuration occurs
$injector->mapSingletonOf('MyClassInterface', 'MyClass');//MyClass implements MyClassInterface
```

```
// in the first class to receive injections
/**
 * @Inject
 * @var MyClassInterface
 */
public $myClassSharedInstance;
```

```
// in the second class to receive injections
/**
 * @Inject
 * @var MyClassInterface
 */
public $myClassSharedInstance;
```

This injection allows easy and clean use of PHP Interfaces and Abstract classes.

Here is the signature of this Injector method:
```
/**
 * @param string $whenAskedFor A PHP Interface or Class name
 * @param string $useSingletonOf A PHP Class name
 * @param string|null $named Injection name
 */
function mapSingletonOf ($whenAskedFor, $useSingletonOf, $named = null)
```

#### mapSingletonThroughClosure

```mapSingletonThroughClosure``` provides a _unique_, _shared_ instance of the requested class for every injection.
When the requested class is first requested, the Closure is triggered and its return value will be used as the
injection value for this first (and all subsequents) requests.

This is a managed single instance, enforced by the framework, and not a Singleton enforced within the class itself.

```
// someplace in your application where mapping/configuration occurs
$injector->mapSingletonThroughClosure('MyClass', function () {
    $myClassSharedInstance = new MyClass();
    // Any initialization code for the instantation of this shared class instance :
    // ...
    // ...
    return $myClassSharedInstance;
};);
```

```
// in the first class to receive injections
/**
 * @Inject
 * @var MyClass
 */
public $myClassSharedInstance;
```

```
// in the second class to receive injections
/**
 * @Inject
 * @var MyClass
 */
public $myClassSharedInstance;
```

In the above example, both injections requests will be filled with the same instance of the requested class, generated
in the Closure.

_This injection is deferred_, meaning the Closure is not triggered until its mapped class is first requested.

This method is useful for providing a shared class instance in your application with custom initialization code.

Here is the signature of this Injector method:
```
/**
 * @param string $whenAskedFor A PHP Interface or Class name
 * @param \Closure $closure This Closure return value will be used as the injected value when this PHP Interface or Class injection is requested
 * @param string|null $named Injection name
 */
function mapSingletonThroughClosure ($whenAskedFor, Closure $closure, $named = null)
```

### Named injections

As you can see, every described Medic Injector methods have an additional optional ```$named``` parameter. This allows
the use of "named injections".

Since the injections mapping is made with the properties types, without named injections you couldn't map two instances
of the same class in your application. Naming an injection gives it a unique ID, allowing the Medic Injector to know
which instance it must inject in a given property or setter.

```
// someplace in your application where mapping/configuration occurs
$myClassFirstInstance = new MyClass('debug_mode');
$injector->mapValue('MyClass', $myClassFirstInstance, 'DEBUG');//the class first instance is mapped with the 'DEBUG' injection name
$myClassSecondInstance = new MyClass('production_mode');
$injector->mapValue('MyClass', $myClassFirstInstance, 'PROD');//the class second instance is mapped with the 'PROD' injection name
```

```
// in the class to receive injections
/**
 * @Inject("DEBUG")
 * @var MyClass
 */
public $myClassDebugInstance; // named injection : the first instance, with name "DEBUG", is injected in this property

/**
 * @Inject("PROD")
 * @var MyClass
 */
public $myClassProductionInstance; // named injection : the second instance, with name "PROD", is injected in this property
```


This allows mapping of PHP primitive types too. For instance, you could have a few configuration options in you application,
of type "string". With named injections you can provide these configuration values in your classes :

```
// someplace in your application where mapping/configuration occurs
$config = array(
    'debug'         => true,
    'DSN'           => 'mysql://root:password@locahost/app',
    'exportPath'    => '/var/app/export',
);
$injector->mapValue('boolean', $config['debug'], 'DEBUG');
$injector->mapValue('string', $config['DSN'], 'DSN');
$injector->mapValue('string', $config['exportPath'], 'EXPORT_PATH');
```

```
// in the class to receive injections
/**
 * @Inject("DEBUG")
 * @var boolean
 */
public $debug; // --> 'true' will be injected in this property

/**
 * @Inject("DSN")
 * @var string
 */
public $dsn; // --> 'mysql://root:password@locahost/app' will be injected in this property

/**
 * @Inject("EXPORT_PATH")
 * @var string
 */
public $exportPath; // --> '/var/app/export' will be injected in this property
```

### Proceed to injection!

We have seen how to map values, class instances and shared singleton class instances with the Medic Injector.
To proceed to injection after having set your mappings, you just have to call your injector ```injectInto``` method:

```
// someplace in your application where mapping/configuration occurs
use Medic\Injection\Injector;
$injector = new Injector();
// Misc mappings...
$logger = new Monolog\Logger\Logger();
$logger->pushHandler(new StreamHandler('path/to/your.log'));
$injector->mapValue('Monolog\Logger\Logger', $logger);
$injector->mapClass('Symfony\Component\Serializer\SerializerInterface', 'Symfony\Component\Serializer\Serializer');
```

```
// someplace in your application where you start your application engine
$applicationStartPoint = new MyApplication();
$injector->injectInto($applicationStartPoint);
// your "MyApplication" instance is now aware of your logger, have a Serializer instance, and so on!
$applicationStartPoint->run();
```

If any of the injected value is a class instance which implements the ```Medic\Injection\InjectionTarget``` empty Interface,
the Injector will handle its required injections before proceeding to the injection in the target class instance.

This injection process is recursive, and any class instance implementing ```Medic\Injection\InjectionTarget``` will
be handled as long as a class requires it as an injection.

A class can proceed to its own injections as well, if needed:
```
class MyClass
{
    /**
     * @Inject
     * @var \Monolog\Logger\Logger
     */
    public $logger;


    public function __construct ()
    {
        if (null === $this->logger) {
            $myInjector = MyModuleOwnInjectorSubClass::getInstance();
            $myInjector->injectInto($this);
        }
    }
}
```

Note that this is not recommended, as it is best to follow the dependency injection design pattern :
your class shall not be aware of any Injector.


### Mandatory injections

When an injection is requested in a class and no mapping has been done for this property/setter type, Medic Injector
will just inject nothing in the property (or don't trigger the setter, for a setter), leaving it to its initial value.

But you may need to track errors which occur with your application essential mappings. In this case, you just have to
use the ```@InjectMandatory``` annotation instead of ```@Inject```. Mandatory injections annotations can be used on properties and
setters and can named, just as default  injections annotations.

When no mapping can be found by the Medic Injector for a given mandatory injection, the injector will throw a PHP Exception,
typed as ```Medic\Injection\InjectionException```.

```
// in a class to receive injections
/**
 * @Inject
 * @var Monolog\Logger\Logger
 */
public $logger; // this is an optionnal dependency : our class instance will use this logger only if it has been mapped in the application bootstrap

/**
 * @MandatoryInject
 * @var \MyApp\Model\UserModel
 */
public $userModel; // if no UserModel (a required dependency for this class ) has been mapped, an Exception will be thrown by the Injector

```

