<?php
/*
 * Ding - Dependency Injection Made Universal
 * 
 * inspired from a fusion of
 * Dice 2.0-Transitional - 2012-2015 Tom Butler <tom@r.je> | http://r.je/dice.html
 *		for clean decoupled dependencies resolution
 * and Pimple 3 - 2009 Fabien Potencier | http://pimple.sensiolabs.org
 *		for arbitrary data and manual hook
 * with lot of RedCat's improvements, addons and remixs
 *		for powerfull API, lazy load cascade rules resolution,
 *		full registry implementation, freeze optimisation
 * 
 * @package Ding
 * @version 3.8.2
 * @link http://github.com/redcatphp/Ding/
 * @author Jo Surikat <jo@surikat.pro>
 * @website http://redcatphp.com
 */

namespace RedCat\Ding;

class Di implements \ArrayAccess{
	private $php7;
	private $values = [];
	private $factories;
	private $protected;
	private $frozen = [];
	private $raw = [];
	private $keys = [];
	private $mapMerge = [];
	private $hashArgumentsStorage;

	private $rules = ['*' => ['shared' => false, 'construct' => [], 'shareInstances' => [], 'call' => [], 'method' => [], 'inherit' => true, 'substitutions' => [], 'instanceOf' => null, 'newInstances' => []]];
	private $cache = [];
	private $instances = [];
	
	private $configVarsTmp = [];
	private $phpCacheFile = [];
	
	protected static $instance;
	
	static function make($name, $args = [], $forceNewInstance = false, $share = []){
		return static::getInstance()->create($name, $args, $forceNewInstance, $share);
	}
	
	static function load($map,$freeze=null,$file=null){
		if(!isset($freeze)){
			$freeze = defined('REDCAT_DEV_CONFIG')?!REDCAT_DEV_CONFIG:false;
		}
		if($freeze){
			if(!isset($file)){
				$file = getcwd().'/.tmp/redcat-ding.svar';
			}
			if(is_file($file)){
				static::$instance = unserialize(file_get_contents($file));
				static::$instance->instances[__CLASS__] = static::$instance;
			}
			else{
				static::getInstance()->loadPhpMap((array)$map);
				$dir = dirname($file);
				if(!is_dir($dir))
					@mkdir($dir,0777,true);
				file_put_contents($file,serialize(static::$instance));
			}
		}
		else{
			static::getInstance()->loadPhpMap((array)$map);
		}
		return static::$instance;
	}
	
	static function getInstance(){
		if(!isset(static::$instance)){
			static::$instance = new static;
			static::$instance->instances[get_called_class()] = static::$instance;
		}
		return static::$instance;
	}
		
	function __construct(array $values = []){
		$this->php7 = version_compare(PHP_VERSION,'7','>=');
		$this->factories = new \SplObjectStorage();
		$this->protected = new \SplObjectStorage();
		foreach ($values as $key => $value) {
			$this->offsetSet($key, $value);
		}
	}
	function offsetSet($id, $value){
		if (isset($this->frozen[$id])) {
			throw new \RuntimeException(sprintf('Cannot override frozen service "%s".', $id));
		}
		$this->values[$id] = $value;
		$this->keys[$id] = true;
	}
	function &offsetGet($id){
		if(strpos($id,'.')!==false){
			$param = explode('.',$id);
			$k = array_shift($param);
			$null = null;
			if(!isset($this->keys[$k]))
				return $null;
			$v = &$this->values[$k];
			while(null !== $k=array_shift($param)){
				if(!isset($v[$k])) return $null;
				$v = &$v[$k];
			}
			return $v;
		}
		if(!isset($this->keys[$id])){
			$this[$id] = $this->create($id);
		}
		if (
				isset($this->raw[$id])
				|| !is_object($this->values[$id])
				|| isset($this->protected[$this->values[$id]])
				|| !method_exists($this->values[$id], '__invoke')
		) {
				$ref = &$this->values[$id];
				return $ref;
		}
		if (isset($this->factories[$this->values[$id]])) {
			return $this->values[$id]($this);
		}
		$raw = $this->values[$id];
		$this->values[$id] = $raw($this);
		$val = &$this->values[$id];
		$this->raw[$id] = $raw;
		$this->frozen[$id] = true;
		return $val;
	}
	function offsetExists($id){
		return isset($this->keys[$id]);
	}
	function offsetUnset($id){
		if (isset($this->keys[$id])) {
			if (is_object($this->values[$id])) {
				unset($this->factories[$this->values[$id]], $this->protected[$this->values[$id]]);
			}
			unset($this->values[$id], $this->frozen[$id], $this->raw[$id], $this->keys[$id]);
		}
	}
	function __set($k,$v){
		$this->offsetSet($k,$v);
	}
	function __get($k){
		return $this->offsetGet($k);
	}
	function __unset($k){
		$this->offsetUnset($k);
	}
	function __isset($k){
		$this->offsetExists($k);
	}
	function factory($callable){
		if (!is_object($callable) || !method_exists($callable, '__invoke')) {
			throw new \InvalidArgumentException('Service definition is not a Closure or invokable object.');
		}
		$this->factories->attach($callable);
		return $callable;
	}
	function protect($callable){
		if (!is_object($callable) || !method_exists($callable, '__invoke')) {
			throw new \InvalidArgumentException('Callable is not a Closure or invokable object.');
		}
		$this->protected->attach($callable);
		return $callable;
	}
	function raw($id){
		if (!isset($this->keys[$id])) {
			throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
		}
		if (isset($this->raw[$id])) {
			return $this->raw[$id];
		}
		return $this->values[$id];
	}
	function extend($id, $callable){
		if (!isset($this->keys[$id])) {
			throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
		}
		if (!is_object($this->values[$id]) || !method_exists($this->values[$id], '__invoke')) {
			throw new \InvalidArgumentException(sprintf('Identifier "%s" does not contain an object definition.', $id));
		}
		if (!is_object($callable) || !method_exists($callable, '__invoke')) {
			throw new \InvalidArgumentException('Extension service definition is not a Closure or invokable object.');
		}
		$factory = $this->values[$id];
		$extended = function ($c) use ($callable, $factory) {
			return $callable($factory($c), $c);
		};
		if (isset($this->factories[$factory])) {
			$this->factories->detach($factory);
			$this->factories->attach($extended);
		}
		return $this[$id] = $extended;
	}
	function keys(){
		return array_keys($this->values);
	}
	
	function objectify($a){
		if(is_object($a))
			return $a;
		if(is_array($a)){
			if(is_array($a[0])){
				$a[0] = $this->objectify($a[0]);
				return $a;
			}
			else{
				$args = $a;
				$s = array_shift($args);
			}
		}
		else{
			$args = [];
			$s = $a;
		}
		if(is_string($s)&&strpos($s,'new:')===0)
			$a = $this->create(substr($s,4),$args);
		return $a;
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
	function getRule($name){
		$rules = $this->rules;
		$rule = $rules['*'];
		unset($rules['*']);
		$validClassName = $this->validateClassName($name);
		if($validClassName){
			$class = new \ReflectionClass($name);
			$classNames = [];
			$interfaces = $class->getInterfaceNames();
			do{
				$classNames[] = $class->getName();
			}while($class=$class->getParentClass());
			$rules = array_intersect_key($rules, array_flip($classNames));
			uksort($rules,function($a,$b)use($classNames){
				return array_search($a,$classNames)<array_search($b,$classNames);
			});
			foreach($rules as $key=>$r){
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

	function create($name, $args = [], $forceNewInstance = false, $share = []){
		if(!is_array($args))
			$args = (array)$args;
		$instance = $name;
		if($p=strpos($name,':')){
			$this->addRule($name,['instanceOf'=>substr($name,0,$p),'shared'=>true]);
			if(substr($instance,$p+1)==='#')
				$instance = $name.':'.$this->hashArguments($args);
		}
		if(!$forceNewInstance&&isset($this->instances[$instance])) return $this->instances[$instance];
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
			$object = call_user_func_array([$this,'create'],(array)$object);
		$class = get_class($object);
		$reflectionClass = new \ReflectionClass($class);
		return $this->getParams($reflectionClass->getMethod($func), $this->getRule($class))->__invoke($this->expand($args));
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
				$closure = function (array $args, array $share) use ($class, $name, $constructor, $params, $instance) {
					$this->instances[$instance] = $class->newInstanceWithoutConstructor();
					if ($constructor) $constructor->invokeArgs($this->instances[$instance], $params($args, $share));
					return $this->instances[$instance];
				};
			}
			elseif ($params){
				$closure = function(array $args, array $share)use($class, $params, $class){
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
				catch(\ReflectionException $e){
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
				else throw new \Exception('Class '.$class.' does not exist');
			}
			$paramName = $param->getName();
			if(isset($rule['method'][$method->name][$paramName]))
				$default = $rule['method'][$method->name][$paramName];
			elseif(isset($rule['method'][$method->name][$i]))
				$default = $rule['method'][$method->name][$i];
			else
				$default = $param->isDefaultValueAvailable()?$param->getDefaultValue():null;
			$paramInfo[] = [$class, $param->allowsNull(), array_key_exists($class, $rule['substitutions']), in_array($class, $rule['newInstances']),$paramName,$default];
		}
		return function (array $args, array $share = []) use ($paramInfo, $rule) {
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
						$shareInstances[] = $this->create($v,[],$new);
					}
				}
				$share = array_merge($share, $shareInstances);
			}
			if($share||!empty($rule['construct'])){
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
							$parameters[$j] = $this->create($rule['substitutions'][$class],[],false,$share);
						elseif($rule['substitutions'][$class] instanceof ExpanderInterface)
							$parameters[$j] = $rule['substitutions'][$class]->__invoke($this,$share);
						else
							$parameters[$j] = $rule['substitutions'][$class];
					}
					else{
						$parameters[$j] = $this->create($class, [], $new, $share);
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
			ksort($parameters);
			return $parameters;
		};
	}
	function loadPhp($php){
		$php = $this->phpLoadFile($php);
		if(isset($php['$']))
			foreach($php['$'] as $key=>$value)
				$this[$key] = $value;
		if(isset($php['rules']))
			foreach($php['rules'] as $key=>$value)
				$this->defineClass($key,$value);
	}
	function loadPhpMap(array $map){
		foreach(array_reverse($map) as $file){ //preload for $vars transmission
			$this->phpLoadFileCache($file);
		}
		
		$php = $this->phpLoadFile(array_shift($map));
		$mergeConfig = &$php['$']['mergeConfig'];
		$mergeConfig = array_merge($map,isset($mergeConfig)?(array)$mergeConfig:[]);
		foreach($mergeConfig as $k=>$v){
			if($v = realpath($v)){
				$mergeConfig[$k] = $v;
			}
		}
		$mergeConfig = array_unique($mergeConfig);
		$this->loadPhpVar($php);
		$this->loadPhpClass($php);
	}
	function loadPhpVar($php){
		if(isset($php['$'])){
			$merge = isset($php['$']['mergeConfig'])?$php['$']['mergeConfig']:false;
			if($merge){
				$merge = array_map([$this,'phpLoadFile'],(array)$merge);
				array_map([$this,'loadPhpVar'],$merge);
				array_unshift($this->mapMerge,$merge);
			}
			$this->recursiveResolveVar($php['$']);
			foreach($php['$'] as $key=>$value){
				$fc = substr($key,0,1);
				switch($fc){
					case ':':
						$key = substr($key,1);
						if(isset($this[$key])&&is_array($this[$key])){
							$this[$key] = self::merge_recursive($this[$key],$value);
							continue 2;
						}
					break;
					case '+':
						$key = substr($key,1);
						if(isset($this[$key])&&is_array($this[$key])){
							$this[$key] = array_merge($this[$key],$value);
							continue 2;
						}
					break;
					case '!':
						$key = substr($key,1);
						if(isset($this[$key])){
							if(is_array($this[$key])){
								$this[$key] = self::merge_recursive($value,$this[$key]);
							}
							continue 2;
						}
					break;
				}
				$this[$key] = $value;
			}
			if($merge){
				array_map([$this,'loadPhpVar'],$merge);
			}
		}
	}
	protected function recursiveResolveVar(&$var){
		if(is_array($var)){
			foreach($var as $k=>&$v){
				if(strpos($k,'$')===0){
					unset($var[$k]);
					$k = substr($k,1);
					$var[$k] = $this->getDotOffset($v);
				}
				if(is_array($v)){
					$this->recursiveResolveVar($v);
				}
			}
		}
	}
	function loadPhpClass($php){		
		if(isset($php['rules']))
			foreach($php['rules'] as $key=>$value)
				$this->defineClass($key,$value);
		
		$mapMerge = $this->mapMerge;
		$this->mapMerge = [];
		foreach($mapMerge as $map){
			array_map([$this,'loadPhpClass'],$map);
		}
	}
	private function phpLoadFileCache($php){
		if(!isset($this->phpCacheFile[$php])){
			if(is_file($php)){
				list($content,$vars) = includeConfig($php,$this->configVarsTmp);
				$this->configVarsTmp = $vars+$this->configVarsTmp;
				$this->phpCacheFile[$php] = $content;
			}
			else{
				$this->phpCacheFile[$php] = [];
			}
		}
		return $this->phpCacheFile[$php];
	}
	private function phpLoadFile($php){
		$php = $this->phpLoadFileCache($php);
		if($php instanceof \Closure){
			$reflectionFunction = new \ReflectionFunction($php);
			$args = [];
			foreach($reflectionFunction->getParameters() as $param){
				$k = $param->getName();
				$args[] = isset($this->configVarsTmp[$k])?$this->configVarsTmp[$k]:null;
			}
			$php = call_user_func_array($php,$args);
		}
		return $php;
	}
	function defineClass($class,$rule){
		if(isset($rule['instanceOf'])&&is_string($rule['instanceOf'])){
			$rule['instanceOf'] = str_replace('/','\\',$rule['instanceOf']);
		}
		elseif(isset($rule['$instanceOf'])&&is_string($rule['$instanceOf'])){
			$rule['instanceOf'] = $this->getDotOffset($rule['$instanceOf']);
			unset($rule['$instanceOf']);
		}
		if(isset($rule['newInstances'])&&is_string($rule['newInstances']))
			$rule['newInstances'] = explode(',',str_replace('/','\\',$rule['newInstances']));
		if(isset($rule['shareInstances'])&&is_string($rule['shareInstances']))
			$rule['shareInstances'] = explode(',',str_replace('/','\\',$rule['shareInstances']));
		if (isset($rule['substitutions'])){
			$substitutions = $rule['substitutions'];
			$rule['substitutions'] = [];
			foreach ($substitutions as $as=>$use){
				if(substr($as,0,1)==='$'){
					$as = substr($as,1);
					$use = $this->getDotOffset($use);
				}
				$rule['substitutions'][str_replace('/','\\',$as)] = is_string($use)?str_replace('/','\\',$use):$use;
			}
		}
		if(isset($rule['construct'])){
			$construct = $rule['construct'];
			$rule['construct'] = [];
			foreach($construct as $key=>$param){
				if(substr($key,0,1)==='$'){
					$key = substr($key,1);
					$param = $this->getDotOffset($param);
				}
				$rule['construct'][$key] = $param;
			}
		}
		if(isset($rule['call'])){
			$construct = $rule['call'];
			$rule['call'] = [];
			foreach($construct as $key=>$param){
				if(substr($key,0,1)==='$'){
					$key = substr($key,1);
					$param = $this->getDotOffset($param);
				}
				$rule['call'][$key] = $param;
			}
		}
		$this->addRule($class, $rule);
	}
	function getDotOffset($param){
		$param = explode('.',$param);
		$k = array_shift($param);
		if(!isset($this->keys[$k])) return;
		$v = $this->offsetGet($k);				
		while(null !== $k=array_shift($param)){
			if(!isset($v[$k])) return;
			$v = $v[$k];
		}
		return $v;
	}
	function buildCallbackFromString($str){
		$dic = $this;
		return new ExpanderInterface(function()use($dic,$str){
			$parts = explode('::', $str);
			$object = $dic->create(array_shift($parts));
			while ($var = array_shift($parts)){
				if (strpos($var, '(') !== false) {
					$args = explode(',', substr($var, strpos($var, '(')+1, strpos($var, ')')-strpos($var, '(')-1));
					$object = call_user_func_array([$object, substr($var, 0, strpos($var, '('))], ($args[0] == null) ? [] : $args);
				}
				else $object = $object->$var;
			}
			return $object;
		});
	}
	function __invoke($name, $args = [], $forceNewInstance = false, $share = []){
		return $this->create($name, $args, $forceNewInstance, $share);
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
	private static function merge_recursive(){
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
function includeConfig(){
	if(func_num_args()>1&&count(func_get_arg(1)))
		extract(func_get_arg(1));
	return [include(func_get_arg(0)),get_defined_vars()];
}