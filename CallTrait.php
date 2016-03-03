<?php
namespace RedCat\Ding;
trait CallTrait {
	function __call($func,$args){
		$di = property_exists($this,'di')&&$this->di instanceof Di?$this->di:Di::getInstance();
		
		if(substr($func,0,4)=='call'&&ctype_upper(substr($func,4,1))&&method_exists($this,$m='_'.lcfirst(substr($func,4)))){
			$params = $di->methodGetParams($this, $m, $args);
			$closure = function()use($m,$params){
				return call_user_func_array([$this,$m],$params);
			};
			$closure->bindTo($this);
			return $closure();
		}
		
		$method = '_'.$func;
		if(method_exists($this, $method)){
			if(!(new \ReflectionMethod($this, $method))->isPublic()) {
				throw new \RuntimeException("The called method is not public.");
			}
			return $di->method($this, $method, $args);
		}
		if(($c=get_parent_class($this))&&method_exists($c,__FUNCTION__)){
			return parent::__call($func,$args);
		}
		throw new \BadMethodCallException('Call to undefined method '.get_class($this).'->'.$func);
	}
}