<?php
namespace RedCat\Ding;
trait CallTrait {
	function __call($func,$args){
		$method = '_'.$func;
		$di = property_exists($this,'di')&&$this->di instanceof Di?$this->di:Di::getInstance();
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