<?php
namespace GKA\Noctis\Model;

class ModelException extends \Exception{
	public function __construct($message,$code,$title=""){
		$this->title= $title;
		parent::__construct($message,$code);
	}


	public function __toString() {
		//if(DEBUG){
			header('HTTP/1.1 500 Internal Server Error',true,500);


				return "<h1>Model Exception</h1><br/><br/><pre style=\"border: 2px solid #FF0000;\"><b>".$this->title."</b><br> [{$this->code}]: {$this->message}\n</pre>";



				//}else{
		//	return "";
		//}
	}
}
