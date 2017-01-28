<?php
namespace GKA\Noctis\Model\Scaffolding
use \ICE\lib\helpers\StringHelper as StringHelper;
use \FDT2k\Noctis\Core\Env as Env;



/*
	Form helper class
*/
class Form extends \ICE\core\IObject {
	function __construct(){
		$this->setMethod('POST');
		$this->setIdentifier(uniqid());
		$this->setTrueForm(true); // true form means some things like rendering the <form tag  this doesnt happen when form are part of a form group
	}

	public function handleSave($post){
		if(!empty($post)){ // store
			//var_dump($post);
			if($this->getEntity()->validate($post)){

				$query = $this->getEntity()->update()->where($this->getEntity()->getPrimaryKeys())->query();
			//	var_dump($query);
				if($this->getModel()->db->executeUpdate($query)){
					return true;
				}else{
					$this->setError('Query error:'.$this->getModel()->db->getError());
				}
			}else{
				$this->setError('Data validation failed:'.$this->getEntity()->getError());
			}
		}
		return false;
	}

	public function handleAdd($post){
		if(!empty($post)){ // store
//var_dump($post);
			if($this->getEntity()->validate($post)){

				$query = $this->getEntity()->insert()->query();
				//var_dump($query);
				if($this->getModel()->insert($post)){
					//Env::getRoute()->redirectFrom('index');
					return $this->getModel()->db->lastid();
				//	return true;
				}else{
				//	$this->response->form->setError('Query error:'.$this->model->db->getError());
					$this->setError('Query error:'.$this->getModel()->db->getErrorString());

				}
			}else{
				$this->setError('Data validation failed:'.$this->getEntity()->getError());

			}
		}
		return false;
	}

	public function editRecord($data){
		#var_dump($this->getModel());
		$this->getModel()->setEntity($this->getEntity());
		$this->getEntity()->setValues($data);
		//var_dump($this->getEntity()->select()->quickWhere($data)->query());
		if($query = $this->getEntity()->select()->where($this->getEntity()->getPrimaryKeys())->query()){
			#var_dump($query);
			if($data = $this->getModel()->fetchOne($query)){
				$this->getEntity()->setValues($data);
				$this->getEntity()->setDisplayValues($data);
				return true;
			}
		}
		return false;
	}

	public function setModel($model){
		parent::setModel($model);
		$model->setEntity($this->getEntity());
		return $this;
	}

	public function getListingCount(){

		return $this->getModel()->num_rows;
	}

	public function getFormGroup(){
		if(!($group = parent::getFormGroup())){
			$group = FormGroup::create();
			$group->setIdentifier($this->getIdentifier());
			$this->setFormGroup($group);
		}

		return $group;
	}


	public function setFormGroup($group){
		parent::setFormGroup($group);
		$this->setTrueForm(false);
	}

	/*chain constructor*/
	static function createForm($title,$model,$table,$identifier='form'){
		$class = get_called_class();
		//var_dump();
		$o = new $class();
		$o->setTitle($title)->setEntity(sf\Fieldset::create()->loadFromTable($table))->setModel($model)->setIdentifier($identifier);
		return $o;
	}

	/*chain constructor*/
	static function createForTable($table,$model){
		$class = get_called_class();
		//var_dump();
		$o = new $class();
		$o->setEntity(sf\Entity::create()->loadFromTable($table))->setModel($model);
		return $o;
	}

	function setDefaultActions(){

		$this->addActions(sf\Action::create()->setAction('edit?id=:id')->setLabel('edit')->setClass('edit'));
		$this->addActions(sf\Action::create()->setAction('delete?id=:id')->setLabel('delete')->setClass('trash'));
		return $this;
	}
	function setDefaultUniqueActions(){

		$this->addUniqueActions(sf\Action::create()->setAction('add')->setLabel('add')->setClass('edit'));

		return $this;
	}

	function edit($success=null,$error=null){
		/*if (empty($success)){
			$success = function($id){
				Env::getRoute()->redirectFrom('index');
			};
		}
		if(empty($error)){
			$error = function($form,$message){
				//$form->setError($message);
			};
		}


		//var_dump('test');
		if($post->hasDatas()){

			if($id = $this->handleSave($post->getDatas())){
				$success($id);
			}else{
				$error($this,$this->getError());
			}

		}*/
		$post = Env::$post;
		$get = Env::$get;
		$this->editRecord($get->getDatas());
		return $this;

	}


	function add($success=null,$error=null){
		if (empty($success)){
			$success = function($id){
				Env::getRoute()->redirectFrom('index');
			};
		}
		if(empty($error)){
			$error = function($form,$message){
				//$form->setError($message);
			};
		}

		$post = Env::$post;
		$get = Env::$get;
		if($post->hasDatas()){


			if($id = $this->handleAdd($post->getDatas())){
				$success($id);
			}else{
				$error($this,$this->getError());
			}
		}
		return $this;
	}

	function listing($filter =array()){
		$post = Env::$post;
		$get = Env::$get;

		$fs = $this->getEntity();

		//$q = $e -> select('*') -> query();

		$q = $fs->select()->where($filter)->query();
		#var_dump($q);
		//$this->setData($fs->fetchAll());
		if($data = $this->getModel()->fetchAll($q)){
			$this->setData($data);
		}
		return $this;
	}


	function listingFromQuery($query){

		if($data = $this->getModel()->setAutoFree(false)->fetchAll($query)){
			$this->setData($data);
		}
		$rs =$this->getModel()->db->getLastResultSet();
		$this->setEntity(sf\Entity::create()->loadFromRS($rs));
		return $this;
	}
}
