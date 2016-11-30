<?php
namespace RedCat\Strategy;
class App extends Di implements \ArrayAccess{
	private $values = [];
	private $factories;
	private $protected;
	private $frozen = [];
	private $raw = [];
	private $keys = [];
	private $mapMerge = [];
	
	private $configVarsTmp = [];
	private $phpCacheFile = [];
	
	function __construct(array $values = []){
		parent::__construct();
		$this->factories = new \SplObjectStorage();
		$this->protected = new \SplObjectStorage();
		foreach ($values as $key => $value) {
			$this->offsetSet($key, $value);
		}
	}
	
	static function load($map,$freeze=null,$file=null){
		if(!isset($freeze)){
			$freeze = defined('REDCAT_DEV_CONFIG')?!REDCAT_DEV_CONFIG:false;
		}
		if($freeze){
			if(!isset($file)){
				$file = getcwd().'/.tmp/redcat-strategy.svar';
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
			$this[$id] = $this->get($id);
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
			$a = $this->get(substr($s,4),$args);
		return $a;
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
			$object = $dic->get(array_shift($parts));
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
	
	
}
function includeConfig(){
	if(func_num_args()>1&&count(func_get_arg(1)))
		extract(func_get_arg(1));
	return [include(func_get_arg(0)),get_defined_vars()];
}