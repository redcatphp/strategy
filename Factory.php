<?php
namespace RedCat\Ding;
use Closure;
class Factory implements ExpanderInterface {
	private $closure;
	private $rules;
	function __construct(Closure $closure, array $rules=[]){
		$this->closure = $closure;
		$this->rules = $rules;
	}
	function __invoke(Di $di){
		return function()use($di){
			return $di->closureInvoke($this->closure,func_get_args(),$this->rules);
		};
	}
}