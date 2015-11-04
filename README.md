Dependency Injection Container made Universal
=============================================

Substantially
-------------

Hardcoded way:  
 
```php
$a = new A(new B, new C, new D(new E, new F));  
    
```


Wire way:  
 
```php
$a = $di->create('A');  
        
```


 By dint of [reverse engineering](https://en.wikipedia.org/wiki/Reverse_engineering) practiced by [php reflection](http://php.net/manual/en/book.reflection.php), all the dependencies, and, recursively, dependencies of those dependencies, are automatically resolved !

Summary
-------

1. [Notes about Wire](http://redcatphp.com/wire-dependency-injection#about)
	1. [Origin](http://redcatphp.com/wire-dependency-injection#origin)
	2. [Differences](http://redcatphp.com/wire-dependency-injection#differences)
2. [Paradigm](http://redcatphp.com/wire-dependency-injection#paradigm)
	1. [Simplify your code](http://redcatphp.com/wire-dependency-injection#simplify)
	2. [Improve maintainability](http://redcatphp.com/wire-dependency-injection#maintainability)
	3. [Improve scalability](http://redcatphp.com/wire-dependency-injection#scalability)
	4. [Be Wise](http://redcatphp.com/wire-dependency-injection#be-wise)
3. [Get Started](http://redcatphp.com/wire-dependency-injection#get-started)
	1. [Di instanciation](http://redcatphp.com/wire-dependency-injection#di-instanciation)
	2. [Classes instanciation](http://redcatphp.com/wire-dependency-injection#classes-instanciation)
4. [Basic usage](http://redcatphp.com/wire-dependency-injection#basic-usage)
	1. [Object graph creation](http://redcatphp.com/wire-dependency-injection#basic-usage-1)
	2. [Providing additional arguments to constructors](http://redcatphp.com/wire-dependency-injection#basic-usage-2)
5. [Shared dependencies](http://redcatphp.com/wire-dependency-injection#shared-dependencies)
	1. [Using rules to configure shared dependencies](http://redcatphp.com/wire-dependency-injection#shared-dependencies)
6. [Configuring the container with rules](http://redcatphp.com/wire-dependency-injection#config-rules)
	1. [Substitutions](http://redcatphp.com/wire-dependency-injection#config-rules-substitutions)
	2. [Inheritance](http://redcatphp.com/wire-dependency-injection#config-rules-inheritance)
	3. [Constructor Parameters](http://redcatphp.com/wire-dependency-injection#config-rules-constructor)
	4. [Setter injection (mutators)](http://redcatphp.com/wire-dependency-injection#config-rules-mutators)
	5. [Default rules](http://redcatphp.com/wire-dependency-injection#config-rules-default)
	6. [Named instances](http://redcatphp.com/wire-dependency-injection#config-rules-named)
	7. [Sharing instances across a tree](http://redcatphp.com/wire-dependency-injection#config-rules-tree)
7. [Rule cascading](http://redcatphp.com/wire-dependency-injection#cascading-rules)
8. [Arbitrary Data](http://redcatphp.com/wire-dependency-injection#arbitrary-data)
	1. [Simple variable definition](http://redcatphp.com/wire-dependency-injection#simple-variable)
	2. [Anonymous functions](http://redcatphp.com/wire-dependency-injection#anonymous-functions)
	3. [Defining factories manually](http://redcatphp.com/wire-dependency-injection#manual-factory)
	4. [Protecting anonymous functions](http://redcatphp.com/wire-dependency-injection#protecting-anonymous)
	5. [Extend definitions](http://redcatphp.com/wire-dependency-injection#extend-definitions)
9. [PHP Config](http://redcatphp.com/wire-dependency-injection#php-config)

1. Notes about Wire
----------------------

### 1.1 Origin

 Wire is mainly inspired by [Dice](https://r.je/dice.html) with added [Pimple](http://pimple.sensiolabs.org) abilities and great improvements.

### 1.2 Differences

 For those who allready knowing the marvellous [Dice](https://r.je/dice.html), here is the additionals features:

- lazy load cascade rules resolution (make rules cascade at instanciation)
- associative array fitting the constructor name variables
- lazy load instance with DiExpand object instead of *instance* array
- full registry implementation
- dynamic rules construct and call variables
- cascade config for arbitrary data and rules which can use them
- freeze config optimisation

 Many chapters of the following documentation correspond to [Dice documentation](https://r.je/dice.html) with some modifications, reformulations, additions and new features explanations. The main differences from Dice and new features explanations will be foreword by Wire specificity label.

2. Paradigm
-----------

### 2.1 Simplify your code !

Consider base classes like these: 
```php
class A {  
    private $a;  
    private $b;  
    private $c;  
    function \_\_construct(A $a, B $b, C $c, D $d){  
        $this->a = $a;  
        $this->b = $b;  
        $this->c = $c;  
        $this->d = $d;  
    }  
}  
class D {  
    private $e;  
    private $f;  
    function \_\_construct(E $e, F $f){  
        $this->e = $e;  
        $this->f = $f;  
    }  
}  
        
```


Hardcoded way:  
 
```php
$a = new A(new B, new C, new D(new E, new F));  
        
```


Wire way (zero configuration):  
 
```php
$a = $di->create('A');  
        
```


 All the dependencies, and dependencies of those dependencies (recursively), are automatically resolved. Magic! Isn't it?

### 2.2 Improve maintainability

 With Wire, you're now able to add dependencies to any class just by modification on its constructor. Let's take an example:  
 The definition of the class *C* is modified during the development lifecycles and now has a dependency on a class called *X*.  
 Instead of finding everywhere that *C* is created and have to passing an instance of X to it, this is handled automatically by the [IoC](https://en.wikipedia.org/wiki/Inversion_of_control) Container.  
 That's all you need to do:  
 
```php
class C {  
    private $x;  
    function \_\_construct(X $x){  
        $this->x = $x;  
    }  
}  
        
```


### 2.3 Bring higher flexibility

 By using [dependency injection](https://en.wikipedia.org/wiki/Dependency_injection), your class isn't anymore hardcoded to a particular instance.

 Hardcoded way:  
 
```php
class A {  
    private $b;  
    private $c;  
    private $d;  
    function \_\_construct(){  
        $this->b = new B;  
        $this->c = new C;  
        $this->d = new D(new E, new F);  
    }  
  
}  
        
```


 With this code, it's impossible to use a subclass of *B* in place of the instance of *B*. *A* is very tightly coupled to its dependencies. With dependency injection, any of the components can be substituted.

Dependency injection way:  
 
```php
class A {  
    private $b;  
    private $c;  
    private $d;  
    function \_\_construct(B $b, C $c, D $d){  
        $this->b = $b;  
        $this->c = $c;  
        $this->d = $d;  
    }  
  
}          
        
```


 Here, *B* could be any subclass of *B* configured in any way possible. This allows for a far greater [Separation of Concerns](https://en.wikipedia.org/wiki/Separation_of_concerns). *A* never has to worry about configuring its dependencies, they're given to it in a state that is ready to use. By giving less responsibility to the class, flexibility is greatly enhanced because *A* can be reused with any variation of *B*, *C* and *D* instances.

### 2.4 Improve scalability

 Forget almost all factories, registries and service locators. By using Wire you can change the constructor parameters adding/removing dependencies on a whim without worrying about side-effects throughout your code.  
 Objects doesn't anymore need to know what dependencies other objects have and making changes become incredibly easy!

 Once Wire is handling your application's dependencies, you can simply add a class to the system like this:

 
```php
class B {  
    function \_\_construct(PDO $pdo, C $c){  
  
    }  
}  
        
```


And require it in one of your existing classes:

 
```php
class ExistingA {  
    function \_\_construct(B $b){  
          
    }  
}          
        
```


 And it will just work without even telling Wire anything about it. You don't need to worry that you've changed an existing class's constructor as it will automatically be resolved and you don't need to worry about locating or configuring the dependencies that the new class needs!

### 2.5 Be wise

 Wire can manage dependencies on top level of your application and resolve them through deep tree of coupled components helping you to avoid ["couriers"](http://r.je/oop-courier-anti-pattern.html) [anti-pattern](https://en.wikipedia.org/wiki/Anti-pattern) which is a common problem when using dependency injection.  
 But it does not exempt you to use pure [OO](https://en.wikipedia.org/wiki/Object-oriented_programming) [encapsulation](https://en.wikipedia.org/wiki/Encapsulation_%28computer_programming%29) inside low levels of a decoupled component where you don't expect external scalability and where you choose consciously to tightly couple things.  
 Finally, it belongs to you to rule what is the limit between object-oriented and component-oriented approach by clearly define decoupled components and add separately coupling couch.

3. Get Started
--------------

### 3.1 Di Instanciation

 
```php
$di = \\Wire\\Di::getInstance(); //global shared instance  
  
$di = new \\Wire\\Di; //classical new instance  
        
```


### 3.2 Classes Instanciation

 
```php
$di->create('My\\Class');  
  
\\Wire\\Di::getInstance()->create('My\\Class'); //global shared instance used via object  
  
\\Wire\\Di::make('My\\Class'); //global shared instance used via static call  
        
```


4. Basic usage
--------------

 Why is Wire (and its [Dice](https://r.je/dice.html) parent) different? A lot of DICs require that you provide some configuration for each possible component in order just to work.

 Wire takes a convention-over-configuration approach and uses type hinting to infer what dependencies an object has. As such, no configuration is required for basic object graphs.

### 4.1 Object graph creation

 
```php
class A {  
    private $b;  
  
    function \_\_construct(B $b) {  
        $this->b = $b;  
    }  
}  
  
class B {  
    private $c,$d;  
  
    function \_\_construct(C $c, D $d) {  
        $this->c = $c;  
        $this->d = $d;  
    }  
}  
  
class C {  
  
}  
  
class D {  
    private $e;  
      
    function \_\_construct(E $e) {  
        $this->e = $e;  
    }  
}  
  
class E {  
      
}  
  
$a = $di->create('A');  
print\_r($a);  
        
```


 Which creates:  
 
```php
A Object  
(  
    [b:A:private] => B Object  
        (  
            [c:B:private] => C Object  
                (  
                )  
  
            [d:B:private] => D Object  
                (  
                    [e:D:private] => E Object  
                        (  
                        )  
  
                )  
  
        )  
  
)  
        
```


 At its simplest level, this has removed a lot of the initialisation code that would otherwise be needed to create the object graph.

### 4.2 Providing additional arguments to constructors

It's common for constructors to require both dependencies which are common to every instance as well as some configuration that is specific to that particular instance. For example: 
```php
class A {  
    public $name;  
    public $b;  
      
    function \_\_construct(B $b, $name) {  
        $this->name = $name;  
        $this->b = $b;  
    }  
}  
        
```


 Here, the class needs an instance of B as well as a unique name. Wire allows this: 
```php
$a1 = $di->create('A', ['FirstA']);  
$a2 = $di->create('A', ['SecondA']);  
  
echo $a1->name; // "FirstA"  
echo $a2->name; // "SecondA"  
        
```


 The dependency of B is automatically resolved and the string in the second parameter is passed as the second argument. You can pass any number of additional constructor arguments using the second argument as an array to $di->create();

 Wire specificity  
 You can also use associative array where the name of argument will fit the name of variable in construct definition and even combine associative, numeric index and type hinting!  
 Let's take an example: 
```php
class A {  
    public $name;  
    public $lastname;  
    public $pseudo;  
    public $b;     
  
    function \_\_construct(B $b, $name, $lastname, $pseudo){  
        $this->name = $name;  
        $this->lastname = $lastname;  
        $this->pseudo = $pseudo;  
        $this->b = $b;  
    }  
}  
  
$a1 = $di->create('A', [ //order of associative keys doesn't matter  
    'lastname'=>'RedCat'  
    'name'=>'Jo',  
    'pseudo'=>'Surikat',  
]);  
$a2 = $di->create('A', [  
    'RedCat',  
    'Surikat'  
    'name'=>'Jo', //order of associative key doesn't matter  
]);  
$a3 = $di->create('A', [  
    'Jo',  
    'lastname'=>'RedCat' //order of associative key doesn't matter  
    'Surikat',  
]);  
  
echo $a1->name; // "Jo"  
echo $a1->lastname; // "RedCat"  
echo $a1->pseudo; // "Surikat"  
  
echo $a2->name; // "Jo"  
echo $a2->lastname; // "RedCat"  
echo $a2->pseudo; // "Surikat"  
  
echo $a3->name; // "Jo"  
echo $a3->lastname; // "RedCat"  
echo $a3->pseudo; // "Surikat"
        
```
  
 There is a limitation on this feature that is that's only work on user's defined classes, you cannot use associative keys on native php classes (like PDO). The reflection API which extract constructor's variables names does'nt work on them because they are precompiled. To work around this limitation you have to extends them and name yourself theses variables in the extended constructor that can call directly it's parent constructor, it's that easy.

5. Shared dependencies
----------------------

 By far the most common real-world usage of Dependency Injection is to enable a single instance of an object to be accessible to different parts of the application. For example, Database objects and locale configuration are common candidates for this purpose.

 Wire makes it possible to create an object that is shared throughout the application. Anything which would traditionally be a global variable, a singleton, accessible statically or accessed through a Service Locator / Repository is considered a shared object.

 Any class constructor which asks for an instance of a class that has been marked as shared will be passed the shared instance of the object rather than a new instance.

### 5.1 Using rules to configure shared dependencies

 The method of defining shared objects is by Rules. See the section on [Rules](http://redcatphp.com/wire-dependency-injection#config-rules) below for more information. They are used to configure the container. Here's how a shared object is defined using a rule.

 Wire accepts a rule for a given class an applies it each time it creates an instance of that class. A rule is an array with a set of options that will be applied when an instance is requested from the container.

 This example uses PDO as this is a very common use-case. 
```php
//create a rule to apply to shared object  
$rule = ['shared' => true];  
  
//Apply the rule to instances of PDO  
$di->addRule('PDO', $rule);  
  
//Now any time PDO is requested from Wire, the same instance will be returned  
$pdo = $di->create('PDO');  
$pdo2 = $di->create('PDO');  
var\_dump($pdo === $pdo2); //TRUE  
  
//And any class which asks for an instance of PDO will be given the same instance:  
class MyClass {  
    public $pdo;  
    function \_\_construct(PDO $pdo) {  
        $this->pdo = $pdo;  
    }  
}  
  
$myobj = $di->create('MyClass');  
var\_dump($pdo === $myobj->pdo); //TRUE  
        
```


 Here, both instances of PDO would be the same. However, because this is likely to be the most commonly referenced piece of code on this page, to make this example complete, the PDO constructor would need to be configured as well: 
```php
$rule = [  
    //Mark the class as shared so the same instance is returned each time  
    'shared' => true,   
    //The constructor arguments that will be supplied when the instance is created  
    'construct' => ['mysql:host=127.0.0.1;dbname=mydb', 'username', 'password']   
];  
  
//Apply the rule to the PDO class  
$di->addRule('PDO', $rule);  
  
//Now any time PDO is requested from Wire, the same instance will be returned  
//And will havebeen constructed with the arugments supplied in 'construct'  
$pdo = $di->create('PDO');  
$pdo2 = $di->create('PDO');  
var\_dump($pdo === $pdo2); //TRUE  
  
  
//And any class which asks for an instance of PDO will be given the same instance:  
class MyClass {  
    public $pdo;  
    function \_\_construct(PDO $pdo) {  
        $this->pdo = $pdo;  
    }  
}  
  
class MyOtherClass {  
    public $pdo;  
    function \_\_construct(PDO $pdo) {  
        $this->pdo = $pdo;  
    }  
}  
  
  
//Note, Wire is never told about the 'MyClass' or 'MyOtherClass' classes, it can  
//just automatically create them and inject the required PDO isntance  
  
$myobj = $di->create('MyClass');  
$myotherobj = $di->create('MyOtherClass');  
  
//When constructed, both objects will have been passed the same instance of PDO  
var\_dump($myotherobj->pdo === $myobj->pdo); //TRUE  
        
```


 The *construct* rule has been added to ensure that every time an instance of PDO is created, it's given a set of constructor arguments. See the section on [construct](http://redcatphp.com/wire-dependency-injection#config-rules-constructor) for more information.

 Wire specificity  
 The global instance of *RedCat\\Wire\\Di* class is naturally shared.

6. Configuring the container with Rules
---------------------------------------

 In order to allow complete flexibility, the container can be fully configured using rules provided by associative arrays rules are passed to the container using the addRule method: 
```php
$rule = ['name' => 'value'];  
$di->addRule('rulename', $rule);  
        
```


 By default, rule names match class names so, to apply a rule to a class called *A* you would use: 
```php
$di->addRule('A', $rule);  
$a = $di->create('A');  
        
```


 Each time an instance of *A* is created by the container it will use the rule defined by $rule

 Wire Rules can be configured with these properties:

- **shared** (*boolean*) - Whether a single instance is used throughout the container. [View Example](http://redcatphp.com/wire-dependency-injection#shared-dependencies-rule)
- **inherit** (*boolean*) - Whether the rule will also apply to subclasses (defaults to true). [View Example](http://redcatphp.com/wire-dependency-injection#config-rules-inheritance)
- **construct** (*array*) - Additional parameters passed to the constructor. [View Example](http://redcatphp.com/wire-dependency-injection#config-rules-constructor)
- **substitutions** (*array*) - key->value substitutions for dependencies. [View Example](http://redcatphp.com/wire-dependency-injection#config-rules-substitutions)
- **call** (*multidimensional array*) - A list of methods and their arguments which will be called after the object has been constructed. [View Example](http://redcatphp.com/wire-dependency-injection#config-rules-shared)
- **instanceOf** (*string*) - The name of the class to initiate. Used when the class name is not passed to $di->addRule(). [View Example](http://redcatphp.com/wire-dependency-injection#config-rules-named)
- **shareInstances** (*array*) - A list of class names that will be shared throughout a single object tree. [View Example](http://redcatphp.com/wire-dependency-injection#config-rules-tree)

### 6.1 Substitutions

When constructor arguments are type hinted using interfaces or to enable polymorpsim, the container needs to know exactly what it's going to pass. Consider the following class:

 
```php
class A {  
    function \_\_construct(Iterator $iterator) {  
      
    }  
}  
        
```


Clearly, an instance of "Iterator" cannot be used because it's an interface. If you wanted to pass an instance of B:

 
```php
class B implements Iterator {  
    //...  
}  
        
```


The rule can be defined like this:

 
```php
//When a constructor asks for an instance of Iterator pass it an instance of B instead  
$rule = ['substitutions' => ['Iterator' => new DiExpand('B')]];  
  
$di->addRule('A', $rule);  
$a = $di->create('A');  
        
```


 Wire specificity  
*['instance' => 'name'] syntax* was removed because it was not compatible with associative array of arguments and not very consistent in some other cases and it was replaced by DiExpand object that can act like a lazy instanciator, or lazy callback resolver if you pass a Closure (anonymous function) to it.  
 To use it, simply instanciate it by is full class name *new \\RedCat\\Wire\\DiExpand()* or by call *use RedCat\\Wire\\DiExpand;* at top of your code so you can use just *new DiExpand()* throughout the following code.

 The new DiExpand('B') object is used to tell Wire to create an instance of 'B' in place of 'Iterator'. *new DiExpand('B')* can be read as 'An instance of B created by the Wire'.

The reason that *['substitutions' => ['iterator' => $di->create('B')]]* is not used is that this creates a B object there and then. Using the DiExpand object means that an instance of B is only created at the time it's required.

However, what if If the application required this?

 
```php
$a = new A(new DirectoryIterator('/tmp'));  
        
```


There are three ways this can be achieved using Wire.

1. Direct substitution, pass the fully constructed object to the rule:

 
```php
$rule = ['substitutions' => ['Iterator' => new DirectoryIterator('/tmp')]];  
  
$di->addRule('A', $rule);  
$a = $di->create('A');  
        
```


2. factory substitution with closures

You can pass a closure into the DiExpand object and it will be called and the return value will be used as the substitution when it's required. Please note this is done just-in-time so will be called as the class it's been applied to is instantiated.

 
```php
$rule = ['substitutions' =>   
            ['Iterator' => new DiExpand(function() {  
                            return new DirectoryIterator('/tmp');  
                        })]  
            ]  
        ];  
  
$di->addRule('A', $rule);  
$a = $di->create('A');  
        
```


3. Named instances. See the section on [Named instances](http://redcatphp.com/wire-dependency-injection#config-rules-named) for a more detailed explanation of how this works.

 
```php
$namedDirectoryIteratorRule = [  
                    //An instance of the DirectoryIterator class will be created  
                    'instanceOf' => 'DirectoryIterator',  
                    //When the DirectoryIterator is created, it will be passed the string '/tmp' as the constructor argument  
                    'construct' => ['/tmp']  
];  
  
  
//Create a rule under the name "$MyDirectoryIterator" which can be referenced as a substitution for any other rule  
$di->addRule('$MyDirectoryIterator', $namedDirectoryIteratorRule);  
  
  
//This tells the DI Container to use the configuration for $MyDirectoryIterator when an Iterator is asked for in the constructor argument  
$aRule = ['substitutions' =>   
            [  
                'Iterator' => new DiExpand('$MyDirectoryIterator')  
            ]  
        ];  
  
//Apply the rule to the A class  
$di->addRule('A', $aRule);  
  
//Now, when $a is created, it will be passed the Iterator configured as $MyDirectoryIterator  
$a = $di->create('A');  
        
```


### 6.2 Inheritance

By default, all rules are applied to any child classes whose parent has a rule. For example:

 
```php
class A {  
}  
  
class B extends A {  
}  
  
  
//Mark instances of A as shared  
$aRule = ['shared' => true];  
$di->addRule('A', $aRule);  
  
//Get the rule currently applied to 'B' objects  
$bRule = $di->getRule('B');  
  
//And B instances will also be shared  
var\_dump($bRule['shared']); //TRUE  
  
//And to test it:  
$b1 = $di->create('B');  
$b2 = $di->create('B');  
  
var\_dump($b1 === $b2); //TRUE (they are the same instance)  
        
```


The rule's inherit property can be used to disable this behaviour:

 
```php
class A {  
}  
  
class B extends A {  
}  
  
  
//This time mark A as shared, but turn off rule inheritance  
$aRule = ['shared' => true, 'inherit' => false];  
  
$di->addRule('A', $rule);  
$bRule = $di->getRule('B');  
  
//Now, B won't be marked as shared as the rule applied to A is not inherited  
var\_dump($bRule['shared']); //FALSE  
  
//And to test it:  
$b1 = $di->create('B');  
$b2 = $di->create('B');  
  
var\_dump($b1 === $b2); //FALSE (they are not the same instance)  
        
```


### 6.3 Constructor parameters

 Wire specificity  
 constructParams was renamed construct.

When defining a rule, any constructor parameters which are not type hinted must be supplied in order for the class to be initialised successfully. For example:

 
```php
class A {  
    function \_\_construct(B $b, $foo, $bar) {  
    }  
}  
        
```


The container's job is to resolve B. However, without configuration it cannot possibly know what $foo and $bar should be.

These are supplied using:

 
```php
$rule = ['construct' => ['Foo', 'Bar']];  
  
$di->addRule('A', $rule);  
  
$a = $di->create('A');  
        
```


This is equivalent to:

 
```php
new A(new B, 'Foo', 'Bar');  
        
```


Constructor parameter order for dependencies does not matter:

 
```php
class A {  
    function \_\_construct($foo, $bar, B $b) {  
    }  
}  
  
$rule = ['construct' => ['Foo', 'Bar']];  
  
$di->addRule('A', $rule);  
  
$a = $di->create('A')  
        
```


Wire is smart enough to work out the parameter order and will execute as expected and be equal to:

 
```php
new A('Foo', 'Bar', new B);  
        
```


### 6.4 Setter injection

Objects often need to be configured in ways that their constructor does not account for. For example:[PDO::setAttribute()](http://www.php.net/manual/en/pdo.setattribute.php) may need to be called to further configure PDO even after it's been constructed.

To account fo this, Wire Rules can supply a list of methods to call on an object after it's been constructed as well as supply the arguments to those methods. This is achieved using $rule->call:

 
```php
class A {  
    function \_\_construct(B $b) {  
      
      
    }  
      
    function method1($foo, $bar) {  
        echo 'Method1 called with ' . $foo . ' and ' . $bar . "\\n";  
    }  
      
    function method2() {  
        echo "Method2 called\\n";  
    }  
}  
  
  
$rule = [  
            'call' => [  
                ['method1', ['Foo1' ,'Bar1']],  
                ['method1', ['Foo2' ,'Bar2']],  
                ['method2', []]  
            ]  
        ];  
  
  
$di->addRule('A', $rule);  
$a = $di->create('A');  
        
```


This will output:

 
```php
Method1 called with Foo1 and Bar1   
Method1 called with Foo2 and Bar2  
Method2 called  
        
```


The methods defined in $rule['call'] will get called in the order of the supplied array.

#### Practical example: PDO

Here is a real world example for creating an instance of PDO.

 
```php
$rule = [  
    'construct' => ['mysql:host=127.0.0.1;dbname=mydb', 'username', 'password'],  
    'shared' = true,  
    'call' => [  
        ['setAttribute', [PDO::ATTR\_DEFAULT\_FETCH\_MODE, PDO::FETCH\_OBJ]]  
    ]  
];  
  
$di->addRule('PDO', $rule);  
  
class MyClass {  
    function \_\_construct(PDO $pdo) {  
      
    }  
}  
  
//MyObj will be constructed with a fully initialisd PDO object  
$myobj = $di->create('MyClass');  
        
```


 Wire specificity  
 For passing parameters to calls, you can also use associative array fitting the methods variables names like for the construct rule. But you can also use associative array that will use method name for keys and if you have to pass just one argument to the method that is not an array you can pass it without wrap it in array, it will be casted automatically.  
 Let's take some examples: 
```php
    $rule = [  
        'call' => [  
            'methodName'=>[$arg1, $arg2],  
            ['methodName',[$arg3, $arg4]],  
            'otherMethodName'=>$singleArgThatIsNotAnArray,  
              
            'methodName'=>[  
                'varname2'=>$arg2  
                'varname'=>$arg1,  
            ],  
            ['methodName',[  
                'varname2'=>$arg2  
                'varname'=>$arg1,  
            ]],  
        ]  
    ];  

```


### 6.5 Default rules

Wire also allows for a rule to apply to any object it creates by applying it to '\*'. As it's impossible to name a class '\*' in php this will not cause any compatibility issues.

The default rule will apply to any object which isn't affected by another rule.

The primary use for this is to allow application-wide rules. This is useful for type-hinted arguments. For example, you may want any class that takes a PDO object as a constructor argument to use a substituted subclass you've created. For example:

 
```php
class MyPDO extends PDO {  
    //...  
}  
        
```


Wire allows you to pass a "MyPDO" object to any constructor that requires an instance of PDO by adding a default rule:

 
```php
class Foo {  
    public $pdo;  
    function \_\_construct(PDO $pdo) {  
        $this->pdo = $pdo;  
    }  
}  
  
//When PDO is type hinted, supply an instance of MyPDO instead  
$rule = ['substitutions' => ['PDO' => new DiExpand('MyPDO')]];  
  
//Apply the rule to every class  
$di->addRule('\*', $rule);  
  
$foo = $di->create('Foo');  
echo get\_class($foo->pdo); // "MyPDO"  
        
```


The default rule is identical in functionality to all other rules. Objects could be set to shared by default, for instance.

### 6.6 Named instances

One of Wire's most powerful features is Named instances. Named instances allow different configurations of dependencies to be accessible within the application. This is useful when not all your application logic needs to use the same configuration of a dependency.

For example, if you need to copy data from one database to another you'd need two database objects configured differently. With named instances this is possible:

 
```php
class DataCopier {  
    function \_\_construct(PDO $database1, PDO $database2) {  
          
    }  
}  
  
//A rule for the default PDO object  
$rule = [  
    'shared' => true,  
    'construct' = ['mysql:host=127.0.0.1;dbname=mydb', 'username', 'password']  
];  
  
$di->addRule('PDO', $rule);  
  
  
//And a rule for the second database  
$secondDBRule = [  
    'shared' => true,  
    'construct' = ['mysql:host=externaldatabase.com;dbname=foo', 'theusername', 'thepassword'],  
      
    //This rule will create an instance of the PDO class  
    'instanceOf' => 'PDO'  
];  
  
  
//Add named instance called $Database2  
//Notice that the name being applied to is not the name of class  
//but a chosen named instance  
$di->addRule('$Database2', $secondDBRule);  
  
//Now set DataCopier to use the two different databases:  
$dataCopierRule = [  
    'construct' => [  //Set the constructor parameters to the two database instances.  
            new DiExpand('PDO'),  
            new DiExpand('$Database2')  
    ]  
];  
  
  
$di->addRule('DataCopier', $dataCopierRule);  
  
$dataCopier = $di->create('DataCopier');  
        
```


$dataCopier will now be created and passed an instance to each of the two databases.

Once a named instance has been defined, it can be referenced using new DiExpand('$name') by other rules using the Dependency Injection Container in either substitutions or constructor parameters.

**Named instances do not need to start with a dollar, however it is advisable to prefix them with a character that is not valid in class names.**

### 6.7 Sharing instances across a tree

In some cases, you may want to share a a single instance of a class between every class in one tree but if another instance of the top level class is created, have a second instance of the tree.

For instance, imagine a MVC triad where the model needs to be shared between the controller and view, but if another instance of the controller and view are created, they need a new instance of their model shared between them.

The best way to explain this is a practical demonstration:

 
```php
class A {  
  
    public $b, $c;  
      
    function \_\_construct(B $b, C $c) {  
      
      
    }  
  
}  
  
  
class B {  
    public $d;  
      
    function \_\_construct(D $d) {  
        $this->d = $d;  
    }  
}  
  
class C {  
    public $d;  
      
    function \_\_construct(D $d) {  
        $this->d = $d;  
    }  
}  
  
  
class D {}  
        
```


 By using $rule->shareInstances it's possible to mark D as shared within each instance of an object tree. The important distinction between this and global shared objects is that this object is only shared within a single instance of the object tree.

 
```php
$rule = [  
    'shareInstances' = ['D']  
];  
  
$di->addRule('A', $rule);  
  
  
//Create an A object  
$a = $di->create('A');  
  
//Anywhere that asks for an instance D within the tree that existis within A will be given the same instance:  
//Both the B and C objects within the tree will share an instance of D  
var\_dumb($a->b->d === $a->c->d); //TRUE  
  
//However, create another instance of A and everything in this tree will get its own instance of D:  
  
$a2 = $di->create('A');  
var\_dumb($a2->b->d === $a2->c->d); //TRUE  
  
var\_dumb($a->b->d === $a2->b->d); //FALSE  
var\_dumb($a->c->d === $a2->c->d); //FALSE  
        
```


7 Cascading Rules
-----------------

When adding a rule that has already been set, Wire will update the existing rule that is applied to that class

 
```php
$di->addRule('B', ['shared' => true]);  
$di->addRule('B', ['construct' => ['foo']]);  
        
```


Both rules will be applied to the B class.

Where this is useful is when using inheritance

 
```php
class A {  
  
}  
  
class B extends A {  
  
}  
        
```


 
```php
$di->addRule('A', ['shared' => true]);  
$di->addRule('B', ['construct' => ['foo']]);  
        
```


Because B inherits A, rules applied to A will applied to B (this behaviour can be turned off, [see the section on inheritance](http://redcatphp.com/wire-dependency-injection#config-rules-inheritance)) so in this instance, B will be both shared and have the constructor parameters set.

However if required, shared can be turned off for B:

 
```php
$di->addRule('A', ['shared' => true]);  
$di->addRule('B', [  
    'construct' => 'foo'],  
    'shared' => false  
]);  
        
```


And this keep A shared, but turn it off for the subclass B.

 Wire specificity  
 Here is the most significative improvement to Dice made in Wire.  
 Unlike Dice, Wire make rules cascade at instanciation, the advantage of that technique is the inheritance can fit php native inheritance (extends and implements) without to load class, in a case we use an autoloader, the use of is\_subclass\_of in Dice call theses classes at rule definition time and do a lot of unnecessary work.  
 It also affect how the rules are defined, with "making rules on call" practice the order of rules definition doesn't matter, the cascade will follow natural php heritance from ancestor to final class, passing by interfaces in the order they're implemented in the class definition.  
 An other difference is that the rules will be recursively merged during cascade.  
 And there is an other api feature for extending rule and not replacing it unlike to addRule: $di->extendRule($name, $key (shared|construct|shareInstances|call|inherit|substitutions|instanceOf|newInstances), $value, $push = null).

8 Arbitrary Data
----------------

 These features comes from [Pimple](http://pimple.sensiolabs.org) .  
 Arbitrary variables are used for share specific config across a whole application. You can also use them for bring very specific higher flexibility to factories, it can be convenient sometimes, but this practice can be considered here as an anti-pattern and you can avoid this most of time using [rules](http://redcatphp.com/wire-dependency-injection#config-rules).

 All the [Pimple](http://pimple.sensiolabs.org) API is the same as on original doc except when you "offsetGet" an unexistant key it will be filled with $di->create($key).

### 8.1 Simple variable definition

 
```php
$di['foo'] = 'bar';  
echo $di['foo'];  
        
```


### 8.2 Anonymous functions

 
```php
$container['session\_storage'] = function ($c) {  
    return new SessionStorage('SESSION\_ID');  
};  
  
$container['session'] = function ($c) {  
    return new Session($c['session\_storage']);  
};  
  
$session = $container['session']; //get the session object  
$session2 = $container['session']; //get the same session object  
var\_dump($session===$session2); //will show true  
        
```


### 8.3 Defining factories manually

 
```php
$container['session'] = $container->factory(function ($c) {  
    return new Session($c['session\_storage']);  
});  
$session = $container['session']; //get a session object  
$session2 = $container['session']; //get a new session object  
var\_dump($session===$session2); //will show false  
        
```


### 8.4 Protecting anonymous functions

 Because Wire sees anonymous functions as service definitions, you need to wrap them with the protect() method to store them as parameters and to be able to reuse them. 
```php
$container['random\_func'] = $container->protect(function () {  
    return rand();  
});  
  
        
```


### 8.5 Extend definitions

 
```php
$container['session\_storage'] = function ($c) {  
    return new $c['session\_storage\_class']($c['cookie\_name']);  
};  
  
$container->extend('session\_storage', function ($storage, $c) {  
    $storage->...();  
  
    return $storage;  
});  
        
```


9 PHP Config
------------

### defineClass

 You can use this API to automatically interchange constructor params or setters params by name with arbitrary variables setted in Wire (see [arbitrary data](http://redcatphp.com/wire-dependency-injection#arbitrary-data)).   
 It's a convenient way to decouple somes common configuration variables from classes rules definitions.  
 By prefixing an associative or numeric key of array with "*$*", the value will be used to point the variable that have to be used instead. You can use a "*.*" (dot) in the pointer to traverse an array.   
Let's take an example:   
By using this:  
 
```php
$di['zero'] = 'foo';  
$di['varname'] = 'bar';  
$di['dotted']['sub'] = 'Sub data accessible by dot';  
$di->defineClass('A', [  
    'construct' =>[  
        '$0'=>'zero',  
        '$assoc'=>'varname',  
        '$other'=>'dotted.sub',  
        'assoc2'=>'realvar',  
    ],  
    'call' =>[  
        'method'=>[  
            '$assoc'=>'varname',  
            '$other'=>'dotted.sub',  
        ]  
    ],  
]);  
        
```
 The result will be like:  
 
```php
$di->addRule('A', [  
    'construct' =>[  
        'foo',  
        'assoc'=>'bar',  
        'assoc2'=>'realvar',  
        'other'=>'Sub data accessible by dot',  
    ],  
    'call' =>[  
        'method'=>[  
            'assoc'=>'bar'  
            'other'=>'Sub data accessible by dot',  
        ]  
    ],  
]);  
        
```


### loadPhp

 Here is your config file 
```php
<?php  
return [  
    '$'=>  
        'db\_name'=>'mydb',  
        'db\_user'=>'me',  
        'db\_password'=>'@FuçK1ngP@ssW0rd',  
    ],  
    'rules'=>[  
        'MyPDO'=>[  
            '$name'=>'db\_name',  
            '$user'=>'db\_user',  
            '$pass'=>'db\_password',  
        ],  
    ],  
];  
        
```
  
 And you can load it by:  
 
```php
$di->loadPhp('/my/path/to/config.php');  
        
```


### loadPhpMap

 This method is based on the same princile than *loadPhp* but you have to pass it an array of config files. The difference is that all variables defined by *$* will be merged recursively by cascade following the order of files in *map* array before applying rules which will be merged recursively too.  
 
```php
$di->loadPhpMap([  
    '/path/to/default\_config.php',  
    '/path/to/config.php',  
]);  
        
```


### load

 This is a static method operant on global instance of *Di*. You have to pass it a config map like with *loadPhpMap* but you also can pass a boolean to enable or disable the *frozen* mode and a path to store the *frozen* file. This is the last optimization step for server in production, it will backup the resolved config by serializing the Container so it will be faster to load. You'll have to delete your *frozen* file to update config if you change it. 
```php
RedCat\\Wire\\Di::load([  
    '/path/to/default\_config.php',  
    '/path/to/config.php',  
],true,'temp-path/to/myApplyConfig.svar');  
        
```

