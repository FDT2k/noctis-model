<?php

namespace GKA\Noctis\Model\Scaffolding;

class Action extends \IObject {

	function __construct(){
		/*$this->name = $name;
		$this->label = $label;

		if(empty($label)){
			$this->label = $name;

		}
		$this->type = $type;*/
	}

	function setAction($action,$data=array()){

		foreach($data as $key =>$value){
			$action = str_replace(':'.$key, $value, $action);
		//	var_dump($key,$value,$action);
		}
		//var_dump($data);
		//var_dump($action);
		return $this->__ice_magic_set('action',$action);
	}

}
