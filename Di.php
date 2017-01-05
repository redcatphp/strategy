<?php
/*
 * Strategy - Dependency Injection Made Universal
 * 
 * inspired from Dice | http://r.je/dice.html
 * with lot of RedCat's improvements, addons and remixs
 *		for clean decoupled dependencies resolution
 *		for powerfull API, lazy load cascade rules resolution,
 *		full registry implementation
 * 
 * @package Strategy
 * @version 5.0.1
 * @link http://github.com/redcatphp/Strategy/
 * @author Jo Surikat <jo@surikat.pro>
 * @website http://redcatphp.com
 */

namespace RedCat\Strategy;

use LogicException;
use ReflectionException;
use RuntimeException;

class Di{
	private $php7;
	private $hashArgumentsStorage;

	private $rules = ['*' => ['shared' => false, 'construct' => [], 'shareInstances' => [], 'call' => [], 'method' => [], 'inherit' => true, 'substitutions' => [], 'instanceOf' => null, 'newInstances' => []]];
	private $cache = [];
	private $instances = [];
	
	protected static $instance;
	
	static function getInstance(){
		if(!isset(static::$instance)){
			static::$instance = new static;
			static::$instance->instances[get_called_class()] = static::$instance;
		}
		return static::$instance;
	}
		
	function __construct(){
		$this->php7 = version_compare(PHP_VERSION,'7','>=');
	}
	
	function extendRule($name, $key, $value, $push = null){
		if(!isset($push))
			$push = is_array($this->rules['*'][$key]);
		if(isset($this->rules[$name]))
			$rule = $this->rules[$name];
		elseif($key==='instanceOf'&&is_string($value)&&isset($this->rules[$value]))
			$rule = $this->rules[$value];
		else
			$rule = [];
		if($push){
			if(!isset($rule[$key]))
				$rule[$key] = [];
			if(is_array($value)){
				$rule[$key] = array_merge($rule[$key],$value);
			}
			else{
				$rule[$key][] = $value;
			}
		}
		else{
			$rule[$key] = $value;
		}
		$this->rules[$name] = $rule;
	}
	function addRule($name, array $rule){
		if(isset($this->rules[$name])){
			$this->rules[$name] = self::merge_recursive($this->rules[$name], $rule);
		}
		elseif(isset($rule['instanceOf'])&&is_string($rule['instanceOf'])&&isset($this->rules[$rule['instanceOf']])){
			$this->rules[$name] = self::merge_recursive($this->rules[$rule['instanceOf']], $rule);
		}
		else{
			$this->rules[$name] = $rule;
		}
	}
	function validateClassName($name){
		return preg_match('(^(?>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\\?)+$)', $name);
	}
	function getRule($name, $noInstanceOf=false){
		$rules = $this->rules;
		$rule = $rules['*'];
		unset($rules['*']);
		$validClassName = $this->validateClassName($name);
		if($validClassName){
			$class = new \ReflectionClass($name);
			$classNames = array_reverse($class->getInterfaceNames());
			do{
				$classNames[] = $class->getName();
			}while($class=$class->getParentClass());
			
			$rules = array_intersect_key($rules, array_flip($classNames));
			uksort($rules,function($a,$b)use($classNames){
				return array_search($a,$classNames)<array_search($b,$classNames);
			});
			foreach($rules as $r){
				if($rule['instanceOf']===null&&(!isset($r['inherit'])||$r['inherit']===true)){
					$rule = self::merge_recursive($rule, $r);
				}
			}
		}
		if(isset($this->rules[$name])){
			$rule = self::merge_recursive($rule, $this->rules[$name]);
		}
		elseif(!$validClassName){
			return false;
		}
		return $rule;
	}
	
	protected function resolveAlias($name){
		$stack = [];
		$alias = $name;
		while( isset($this->rules[$alias]) && isset($this->rules[$alias]['alias']) ){
			$r = $this->rules[$alias];
			$alias = $r['alias'];
			if(current($stack)==$alias)
				break;
			if(in_array($alias,$stack)){
				throw new LogicException("cyclic instanceOf reference for class '$name' expected as instance of '$alias'");
			}
			$stack[] = $alias;
		}
		return $alias;
	}
	
	function get($name, $args = [], $forceNewInstance = false, $share = []){
		if(!is_array($args))
			$args = (array)$args;
		$name = $this->resolveAlias($name);
		$instance = $name;
		if($p=strpos($name,':')){
			$this->addRule($name,['instanceOf'=>substr($name,0,$p),'shared'=>true]);
			if(substr($instance,$p+1)==='#')
				$instance = $name.':'.$this->hashArguments($args);
		}
		if(!$forceNewInstance&&isset($this->instances[$instance]))
			return $this->instances[$instance];
		$rule = $this->getRule($name);
		if(!$rule)
			return;
		if(empty($this->cache[$name]))
			$this->cache[$name] = $this->getClosure($name, $rule, $instance);
		return $this->cache[$name]($args, $share);
	}
	
	function share($obj,$instance=null){
		if(!isset($instance))
			$instance = get_class($obj);
		elseif(is_array($instance))
			$instance = get_class($obj).':'.$this->hashArguments($instance);
		$this->instances[$instance] = $obj;
	}
	function methodGetParams($object,$func,array $args=[]){
		if(!is_object($object))
			$object = call_user_func_array([$this,'get'],(array)$object);
		$class = get_class($object);
		$reflectionClass = new \ReflectionClass($class);
		return $this->getParams($reflectionClass->getMethod($func), $this->getRule($class))->__invoke($this->expand($args),[],false);
	}
	function method($object,$func,array $args=[]){
		return call_user_func_array([$object,$func],$this->methodGetParams($object,$func,$args));
	}
	function closureInvoke(\Closure $closure,array $args=[], $rules=[]){
		$reflectionFunction = new \ReflectionFunction($closure);
		$params = $this->getParams($reflectionFunction, $rules+$this->rules['*'])->__invoke($this->expand($args));
		return call_user_func_array($closure,$params);
	}
	private function getClosure($name, array $rule, $instance){
		if(isset($rule['instanceOf'])&&is_object($rule['instanceOf'])){
			return function(array $args)use($rule,$instance){
				$object = $rule['instanceOf'];
				if($object instanceof ExpanderInterface)
					$object = $object($this,$args);
				$class = new \ReflectionClass(get_class($object));
				if($rule['shared'])
					$this->instances[$instance] = $object;
				if(!empty($rule['call'])){
					foreach ($rule['call'] as $k=>$call){
						if(!is_integer($k)){
							$call = [$k,(array)$call];
						}
						call_user_func_array([$object,$call[0]],$this->getParams($class->getMethod($call[0]), $rule)->__invoke($this->expand(isset($call[1])?$call[1]:[])));
					}
				}
				return $object;
			};
		}
		else{
			$class = new \ReflectionClass(isset($rule['instanceOf']) ? $rule['instanceOf'] : $name);
			$constructor = $class->getConstructor();
			$params = $constructor ? $this->getParams($constructor, $rule) : null;
			if($rule['shared']){
				$closure = function (array $args, array $share) use ($class, $constructor, $params, $instance) {
					$this->instances[$instance] = $class->newInstanceWithoutConstructor();
					if ($constructor) $constructor->invokeArgs($this->instances[$instance], $params($args, $share));
					return $this->instances[$instance];
				};
			}
			elseif ($params){
				$closure = function(array $args, array $share)use($class, $params){
					return $class->newInstanceArgs($params($args, $share));
				};
			}
			else{
				$closure = function()use($class){
					return new $class->name;
				};
			}
			return !empty($rule['call']) ? function (array $args, array $share) use ($closure, $class, $rule) {
				$object = $closure($args, $share);
				foreach ($rule['call'] as $k=>$call){
					if(!is_integer($k)){
						$call = [$k,(array)$call];
					}
					call_user_func_array([$object,$call[0]],$this->getParams($class->getMethod($call[0]), $rule)->__invoke($this->expand(isset($call[1])?$call[1]:[])));
				}
				return $object;
			} : $closure;
		}
	}

	private function expand($param, array $share = []) {
		if (is_array($param)){
			foreach($param as $k=>$value){
				$param[$k] = $this->expand($value, $share);
			}
		}
		elseif($param instanceof ExpanderInterface){
			$param = $param($this,$share);
		}
		return $param;
	}

	private function getParams(\ReflectionFunctionAbstract $method, array $rule) {
		$paramInfo = [];
		foreach ($method->getParameters() as $i=>$param){
			if($this->php7){
				$type = $param->getType();
				$class = $type&&!$type->isBuiltin()?(string)$type:null;
			}
			else{
				try{
					$classObject = $param->getClass();
					$class = $classObject?$classObject->name:null;
				}
				catch(ReflectionException $e){
					if($param->allowsNull()) $class = null;
					else throw $e;
				}
			}
			if($class&&class_exists($class)||interface_exists($class)){
				$classObject = $param->getClass();
				if(!array_key_exists($class, $rule['substitutions'])){
					$classRule = $this->getRule($class);
					if(isset($classRule['instanceOf'])){
						if(is_string($classRule['instanceOf']))
							$classObject = new \ReflectionClass($class);
						$rule['substitutions'][$class] = $classRule['instanceOf'];
					}
					elseif(!$classObject->isInstantiable()){
						$class = null;
					}
				}
			}
			elseif($class){
				if($param->allowsNull()) $class = null;
				else throw new RuntimeException('Class '.$class.' does not exist');
			}
			$paramName = $param->getName();
			if(isset($rule['method'][$method->name][$paramName])){
				$default = $rule['method'][$method->name][$paramName];
			}
			elseif(isset($rule['method'][$method->name][$i])){
				$default = $rule['method'][$method->name][$i];
			}
			else{
				$default = $param->isDefaultValueAvailable()?$param->getDefaultValue():null;
			}
			$paramInfo[] = [$class, $param->allowsNull(), array_key_exists($class, $rule['substitutions']), in_array($class, $rule['newInstances']),$paramName,$default];
		}
		return function (array $args, array $share = [], $construct = true) use ($paramInfo, $rule) {
			if(!empty($rule['shareInstances'])){
				$shareInstances = [];
				foreach($rule['shareInstances'] as $v){
					if(isset($rule['substitutions'][$v])){
						$v = $rule['substitutions'][$v];
					}
					if(is_object($v)){
						$shareInstances[] = $v;
					}
					else{
						$new = in_array($v,$rule['newInstances']);
						$shareInstances[] = $this->get($v,[],$new);
					}
				}
				$share = array_merge($share, $shareInstances);
			}
			if($construct && ( $share || !empty($rule['construct']) )){
				$nArgs = $args;
				foreach($this->expand($rule['construct']) as $k=>$v){
					if(is_integer($k)){
						$nArgs[] = $v;
					}
					elseif(!isset($args[$k])){
						$nArgs[$k] = $v;
					}
				}
				$args = array_merge($nArgs, $share);
			}
			$parameters = [];
			if (!empty($args)){
				foreach ($paramInfo as $j=>list($class, $allowsNull, $sub, $new, $name, $default)) {
					if(false!==$offset=array_search($name, array_keys($args),true)){
						$parameters[$j] = current(array_splice($args, $offset, 1));
					}
				}
			}
			foreach($paramInfo as $j=>list($class, $allowsNull, $sub, $new, $name, $default)){
				if(array_key_exists($j,$parameters))
					continue;
				if($class){
					if (!empty($args)){
						foreach($args as $i=>$arg){
							if($arg instanceof $class || ($arg === null && $allowsNull) ){
								$parameters[$j] = &$args[$i];
								unset($args[$i]);
								continue 2;
							}
						}
					}
					if($sub){
						if(is_string($rule['substitutions'][$class]))
							$parameters[$j] = $this->get($rule['substitutions'][$class],[],false,$share);
						elseif($rule['substitutions'][$class] instanceof ExpanderInterface)
							$parameters[$j] = $rule['substitutions'][$class]->__invoke($this,$share);
						else
							$parameters[$j] = $rule['substitutions'][$class];
					}
					else{
						$parameters[$j] = $this->get($class, [], $new, $share);
					}
				}
				elseif(!empty($args)){
					reset($args);
					$k = key($args);
					$parameters[$j] = &$args[$k];
					unset($args[$k]);
					$parameters[$j] = $this->expand($parameters[$j]);
				}
				else{
					$parameters[$j] = $default;
				}
			}
			if(!empty($args)){
				foreach($args as $arg){
					$parameters[] = $arg;
				}
			}
			ksort($parameters);
			return $parameters;
		};
	}
	
	function __invoke($name, $args = [], $forceNewInstance = false, $share = []){
		return $this->get($name, $args, $forceNewInstance, $share);
	}
	private function hashArguments($args){
		if(!isset($this->hashArgumentsStorage))
			$this->hashArgumentsStorage = new \SplObjectStorage();
		$hash = [];
		ksort($args);
		foreach($args as $k=>$arg){
			if(is_array($arg)){
				$h = $this->hashArguments($arg);
			}
			elseif(is_object($arg)){
				$this->hashArgumentsStorage->attach($arg);
				$h = spl_object_hash($arg);
			}
			else{
				$h = sha1($arg);
			}
			$hash[] = sha1($k).'='.$h;
		}
		return sha1(implode('.',$hash));
	}
	protected static function merge_recursive(){
		$args = func_get_args();
		$merged = array_shift($args);
		foreach($args as $array2){
			if(!is_array($array2)){
				continue;
			}
			foreach($array2 as $key => $value){
				if(is_array($value)&&isset($merged[$key])&&is_array($merged[$key])){
					$merged[$key] = self::merge_recursive($merged[$key],$value);
				}
				else{
					$merged[$key] = $value;
				}
			}
		}
		return $merged;
	}
}