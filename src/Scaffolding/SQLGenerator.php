<?php
namespace GKA\Noctis\Model\Scaffolding;

use \FDT2k\Noctis\Core\Env as Env;
use \GKA\Noctis\Model\ModelException as ModelException;

class SQLGenerator extends \IObject{


	// generate a select query

	/*
		Array of entities
	*/
	function select($fields){
		$this->setMethod('SELECT');
		$this->setFields($fields);
		return $this;
	}

	function createTable($fields){
		$this->setMethod('CREATE TABLE');
		$this->setFields($fields);
		return $this;
	}

	function likeTable($table){

	}

	function alterTable(){
		$this->setMethod('ALTER TABLE');
		return $this;
	}

	function addColumns($columns){

	}

	function deleteColumns($columns){

	}

	//append fields to a select
	function from($fields){
		foreach($fields as $f){
			$this->addFields($f);
		}
		return $this;
	}

	function delete(){
		$this->setMethod('DELETE');
		return $this;
	}

	function assertArray($var){
		if (!is_array($var)){
			throw new ModelException("Not an array",0);
		}
	}

	function update($fields=array()){
		$this->assertArray($fields);
		$this->setMethod('UPDATE');

		foreach($fields as $f){
			 $this->addFields($f);
		}

		return $this;
	}

	function insert($fields){
		foreach($fields as $f){
			$this->addFields($f);
		}
		$this->setMethod('INSERT');
		return $this;
	}
	function orderAsc($fields){
		foreach($fields as $f){
			$this->addOrderBy(array($f,'asc'));
		}
		return $this;
	}
	function orderDesc($fields){
		foreach($fields as $f){
			$this->addOrderBy(array($f,'desc'));
		}
		return $this;
	}
	// generate a join statement
	/*
	param: array of EntityField
	*/
	function join($entities){
		$this->setJoin($entities);
		#var_dump($entities);
		return $this;
	}

	function where($fields){
	//	$this->setWhere($fields);
		if($this->hasWhereExp()){
			throw new \Exception("you can't use where and whereExp at the same time");
		}
		if(is_array($fields)){
			foreach($fields as $f){
				$this->addWhere($f);
			}
		}else{
			$this->addWhere($fields);
		}
		return $this;

	}

	function whereExp($expr){
		if($this->hasWhere()){
			throw new \Exception("you can't use where and whereExp at the same time");
		}
		$this->setWhereExp($expr);
		return $this;
	}

	function convertString($field){
		if(!$field->isFunctionValue()){

			return Env::getDatabase()->convertString($field->getValue());
		}
		return $field->getValue();
	}

	function generateFieldUpdateStatement(){

		$sql = "";
		$sep = "";
		#var_dump(sizeof($this->getFields()));
		if(is_array($this->getFields())){
			foreach($this->getFields() as $field){
				#var_dump($field->getName());
				#var_dump($field->getAlias(),$field->getValue());
				if(!$field->isIgnore()  && $field->isSQL()){
					if(!$field->hasFlags('auto_increment')){
						if($field->isMandatory() || !empty($field->getValue())){
							$sql .=  $sep. Env::getDatabase()->convertStringSQL($field->getAlias())."=".$this->convertString($field);
							$sep = ",";
						}
					}
				}
			}
		}
		return $sql;
	}

	function generateFieldStatement(){
		$sql = "";
		$sep = "";

		foreach($this->getFields() as $field){
			#var_dump($field->getName());
			if(!$field->isIgnore() && $field->isSQL()){
				if($field->getType()=="point"){
					$sql.= $sep. "AsText(".$field->getEntity()->getTable().".".$field->getName().") as `".$field->getAlias()."`" ;
					$sql.= $sep. "X(".$field->getEntity()->getTable().".".$field->getName().") as `".$field->getAlias()."_x`" ;
					$sql.= $sep. "Y(".$field->getEntity()->getTable().".".$field->getName().") as `".$field->getAlias()."_y`" ;
				}else{
					$sql.= $sep.$field->getEntity()->getTable().".".$field->getName()." as `".$field->getAlias()."`" ;
				}
				$sep = ",";
			}
		}


		/*

		foreach($this->getJoin()as $field){
			if(!$field->isIgnore()){
				$sql.= $sep.$field->getForeignKey()->getTable().".".$field->getName()." as `".$field->getAlias()."`" ;
				$sep = ",";
			}
		}

		*/



		return $sql;
	}
/**
TODO multiple constraint / PKEY
**/
function countPrimaryKeys(){
	$c =0;
	$a = $this->getFields();
	if(is_array($a)){
		foreach($a as $f){
			if($f->isPrimaryKey()){
				$c++;
			}
		}
	}
	return $c;
}

	function generateCreateField($field,$prefix=''){
		$constraint = "";

		if($field->isMandatory()){
			$constraint .= " NOT NULL ";
		}
		$numpkey=$this->countPrimaryKeys();
		if($field->isPrimaryKey() && $numpkey==1){

			$constraint .= " PRIMARY KEY ";
			//$this->hasFlags('auto_increment')
			if($field->hasFlags('auto_increment')){
			//	var_dump('prout');
				$constraint .=" AUTO_INCREMENT ";
			}
		}

		if($field->hasDefaultValue()){

			$constraint .= " DEFAULT ".Env::getDatabase()->convertString($field->getDefaultValue());
		}
		$sql = $prefix." ". Env::getDatabase()->convertStringSQL($field->getName());


		if($prefix !=="DROP COLUMN"){
			$sql.=" ".$field->getMysqlType();
			$maxlength =  $field->getMaxLength();
		//	var_dump($maxlength);
			if($field->getType() != 'datetime' && $field->getType() != 'date' &&  !empty($maxlength)){
				$sql.="(".$field->getMaxLength().") ";
			}
			$sql.= " ".$constraint;

			$collation = $field->getCollation();

			if(!empty($collation)){
				$sql.= " COLLATE ".$collation;
			}
		}

		return $sql;
	}

	function dropPrimaryKey(){
		//return "DROP PRIMARY KEY";
		$this->setDropPKey(true);
		return $this;
	}



	//generate a query
	function query(){
		$sql = $this->getMethod();

		switch ($this->getMethod()) {

			case 'SELECT':
				$sql.= " ".$this->generateFieldStatement()." from ".$this->getTable();
				break;
			case 'INSERT':
				$sql.= " into ".$this->getTable();
				break;
			case 'UPDATE':
				$sql.= " ".Env::getDatabase()->convertStringSQL($this->getTable()) . " SET ". $this->generateFieldUpdateStatement();
				break;
			case 'DELETE':
				$sql.= " from ".$this->getTable();
				break;
			case 'CREATE TABLE':
				$sql .= " ".Env::getDatabase()->convertStringSQL($this->getTable())."(";
				$sep = "";
				foreach($this->getFields() as $field){
					$sql.= $sep.$this->generateCreateField($field);
					$sep=",";
				}
				if($this->countPrimaryKeys()>1){
					$sql.=",primary key (";
					$sep="";
						foreach($this->getFields() as $field){
							if($field->isPrimaryKey()){
								$sql.=$sep.$field->getName();
								$sep=",";
							}
						}
					$sql.=")";
				}
				$sql.=")";
				if($this->getEntity()->hasStorageEngine()){
					$sql.= " ENGINE=".$this->getEntity()->getStorageEngine();
				}
				$sql.=";";
				break;
			case 'ALTER TABLE':
				$sql .= " ".Env::getDatabase()->convertStringSQL($this->getTable())." \n";
				$sep = "";
				if(is_array($this->getFieldsToAdd())){
					foreach($this->getFieldsToAdd() as $field){
						$sql.= $sep.$this->generateCreateField($field,'ADD');
						$sep=",";
					}
				}
				if(is_array($this->getFieldsToDelete())){

					foreach($this->getFieldsToDelete() as $field){
						$sql.= $sep.$this->generateCreateField($field,'DROP COLUMN');
						$sep=",";
					}
				}
				if(is_array($this->getFieldsToModify())){

					foreach($this->getFieldsToModify() as $field){
						//var_dump($field->getName());
						$sql.= $sep.$this->generateCreateField($field,'MODIFY');
						$sep=",";
					}
				}
				if($this->isDropPKey()){
					$sql.= "DROP PRIMARY KEY";
				}

				if(is_array($this->getRelationShips())){
					foreach ($this->getRelationShips() as $rel){

						// ALTER TABLE users ADD CONSTRAINT fk_NAME FOREIGN KEY (grade_id) REFERENCES remotetable(remotefield);
						$sql .=" ADD CONSTRAINT ".$rel->getName()." FOREIGN KEY (".$rel->getField().") REFERENCES ".$rel->getRefTable()."(".$rel->getRefField().")";

					}
				}
				if(is_array($this->getRelationShipsToDelete())){
					// alter table footable drop foreign key fk_name;
					foreach ($this->getRelationShipsToDelete() as $rel){

						//alter table footable drop foreign key fk_name;
						$sql .=" DROP FOREIGN KEY ".$rel->getName()." ";

					}
				}
				$sql.="";
		/*		if($this->getEntity()->hasStorageEngine()){
					$sql.= " ENGINE=".$this->getEntity()->getStorageEngine();
				}*/
				$sql.=";";
			break;
		}


		if($this->hasJoin() && $this->getMethod()=='SELECT'){

			foreach($this->getJoin() as $field){

				if($e = $field->getForeignKey()){

					if($field->getMandatory()){
						$separator = 'inner join ';
					}else{
						$separator = 'left join ';
					}

					$pkeys = $e->getPrimaryKeys();

					if(count($pkeys)==1){ // I can't see why it should be otherwise (more than one key in foreign key)
						$inner .= $separator." `".$e->getTable()."` on `".$this->getTable()."`.`".$field->getName()."`=`".$e->getTable()."`.`".$pkeys[0]->getName()."`";
						$separator = ",";
					}else{
						throw new ModelException("Can't handle multiple primary key in this context",0);
					}

				}

			}

			$sql.= " ".$inner;
		}

		if($this->hasWhere() && $this->getMethod() != "INSERT"){
			$sql .= " where ";
			$sep = "";
			if(is_array($this->getWhere())){
				foreach($this->getWhere() as $field){

					$sql.= " ".$sep.Env::getDatabase()->convertStringSQL($field->getAlias())."=".Env::getDatabase()->convertString($field->getValue());
					$sep =" and ";
				}
			}
		}

		if($this->hasWhereExp() && $this->getMethod() != "INSERT"){
			$sql .= " where ".$this->getWhereExp();
		}

		if($this->hasOrderBy() && $this->getMethod()=="SELECT"){
			$sql .= " order by ";

			$sep = "";
			foreach($this->getOrderBy() as $field){
				#var_dump($field);
				$sql.= " ".$sep.$field[0]->getAlias()." ".$field[1];
				$sep =",";
			}
		}

		if($this->getMethod()=='INSERT'){ // no where.
			$sql.= "(";
				$sep = "";
				foreach($this->getFields() as $field){
					if(!$field->hasFlags('auto_increment')){
						if($field->isMandatory() || $field->getValue()!=NULL){
							$sql .=$sep.Env::getDatabase()->convertStringSQL($field->getAlias());
							$sep=",";
						}
					}
				}

				$sql.=") VALUES (";
				$sep = "";
				foreach($this->getFields() as $field){
					if(!$field->hasFlags('auto_increment')){
						if($field->isMandatory() || $field->getValue()!=NULL){
							$sql.=$sep.$this->convertString($field);
							$sep=",";
						}
					}
				}
				$sql .=");";
		}
	#	var_dump($sql);
		$this->reset();
		return $sql;
	}

	function reset(){
		$this->setFields(NULL);
		$this->setWhere(NULL);
		$this->setFieldsToAdd(NULL);
		$this->setFieldsToDelete(NULL);
		$this->setFieldsToModify(NULL);
		$this->setDropPKey(false);
		$this->setRelationShipsToDelete(NULL);
		$this->setRelationShips(NULL);
	}

}
