<?php
namespace GKA\Noctis\Model\Scaffolding;
use \FDT2k\Noctis\Core\Env as Env;
use \GKA\Noctis\Model\ModelException as ModelException;

class Entity extends \FDT2k\Noctis\Core\IObject {

	protected $isForeignKey = false;
	static $loadedFieldsets;
	protected $loaded=false;

	static function create($table=''){ //override magic create
		if (is_object(Entity::$loadedFieldsets[$table])){
			#var_dump('return existing '.$table);
			return Entity::$loadedFieldsets[$table];
		}
		$o= parent::create()->setTable($table);
		return $o;
	}

	// load descriptors from table
	function loadFromTable($table=""){
		if(!$this->loaded){
			$this->loaded = true;
			Entity::$loadedFieldsets[$table]= &$this;
			$this->setTable($table);
			if($this->table != $table){ //prevent stupid reconstruct
				$this->sqlgen = SQLGenerator::create();
				$this->sqlgen->setTable($table);
				$this->table = $table;
				$this->level = $level;
				$this->maxlevel=  $maxlevel;
				$usedFields = array();
				$this->fields = array();
				$this->bFetchFKeyDatas=false;

				$rsFields = Env::getDatabase()->getFields($table);
				if($rsFields){

					foreach($rsFields as $res){
					//	var_dump($res);
						$field = EntityField::create()->setEntity($this)->initFromArray($res);
						$this->indexField($field);
					}
				}
				else{
					throw new ModelException('Table not found: '.$table,0);
				}
			}
		}
		return $this;
	}

	function loadFromQuery($query){


	}

	/**
		Migrate an entity on the Database server
		If the entity does not exists it will be created
		If it's not, the table will be updated with existing fields, be aware that it is for
		development use only. It should always be disabled in production.
	**/
	function migrate(){
		$db = $this->getDB();
		Env::getLogger('sql')->startLog('migrating');
		$table_exists = $db->tableExists($this->getTable());
		if($table_exists){
			//var_dump('table_exists');
			Env::getLogger('sql')->log($this->getTable().' table exists');
			$existing_entity = Entity::create()->loadFromTable($this->getTable());
			//var_dump($existing_entity);
			if(($altered_fields = $this->compareFields($existing_entity))!==true){
			//	var_dump($this->getTable().' table is different');
				Env::getLogger('sql')->log($this->getTable().' table is different from server');

				$keys = array_keys($this->getValues());
			//	var_dump($keys);
				$ex_keys = array_keys($existing_entity->getValues());

				$f2add= array_diff($keys,$ex_keys);

			//	var_dump($f2add);
				$f2del=array_diff($ex_keys,$keys);
			//	var_dump($f2del);

				if(sizeof($f2add)>0 || sizeof($f2del)>0){
					// check if pk exists;
					/*
						If there is already a PKey we need to remove it to proceed

						// COMMENTED because I can't remember why I put this here

						Okay so, we remove the primary keys in case we have to add a new one
					*/
					$existing_pk = $existing_entity->getPrimaryKeys();

					if(count($existing_pk) ==1 && $existing_pk[0]->hasFlags('auto_increment')){
						$existing_entity->sqlgen->alterTable();
						$existing_pk[0]->setPrimaryKey(false);
						$f2Modify = $existing_pk[0];
						$existing_entity->sqlgen->setFieldsToModify(array($f2Modify));
						$q = $existing_entity->sqlgen->query();
						$r = $this->getDB()->executeUpdate($q);
						if(!$r ){
							throw new \Exception("migration failed ".$this->getDB()->getErrorString());
						}
						$q = $existing_entity->sqlgen->alterTable()->dropPrimaryKey()->query();
						$r = $this->getDB()->executeUpdate($q);
						if(!$r ){
							throw new \Exception("migration failed ".$this->getDB()->getErrorString());
						}
					}


					/*
						Next we can alter the table by adding the new fields
					*/
					$this->sqlgen->alterTable();

					if(sizeof($f2add)>0){
						$this->sqlgen->setFieldsToAdd($this->getFieldsByNames($f2add));
					}


					$q = $this->sqlgen->query();

					$r = $this->getDB()->executeUpdate($q);

					if(!$r ){

						throw new \Exception("migration failed ".$this->getDB()->getErrorString());
					}
					/*DROP fields*/
					$this->sqlgen->alterTable();
					if(sizeof($f2del)>0){
						$this->sqlgen->setFieldsToDelete($existing_entity->getFieldsByNames($f2del));
					}
					$q = $this->sqlgen->query();
					$r = $this->getDB()->executeUpdate($q);

					if(!$r ){
						throw new \Exception("migration failed ".$this->getDB()->getErrorString());
					}

					//reapply primary keys


					$existing_pk = $this->getPrimaryKeys();

					if(count($existing_pk) ==1 && $existing_pk[0]->hasFlags('auto_increment')){
						$existing_entity->sqlgen->alterTable();
						//$existing_pk[0]->setPrimaryKey(tru);
						$f2Modify = $existing_pk[0];
						$existing_entity->sqlgen->setFieldsToModify(array($f2Modify));
						$q = $existing_entity->sqlgen->query();
						$r = $this->getDB()->executeUpdate($q);
						if(!$r ){
							throw new \Exception("migration failed ".$this->getDB()->getErrorString());
						}
						//$q = $existing_entity->sqlgen->alterTable()->dropPrimaryKey()->query();
						//$r = $this->getDB()->executeUpdate($q);
						//if(!$r ){
						//	throw new \Exception("migration failed ".$this->getDB()->getErrorString());
						//}
					}
				}
				/*Modify fields*/

				$q = $this->sqlgen->alterTable()->setFieldsToModify($altered_fields)->query();
			//	var_dump($q);
				$r = $this->getDB()->executeUpdate($q);

				if(!$r ){
					throw new \Exception("migration failed ".$this->getDB()->getErrorString());
				}
				//first we copy the table

				//second we copy the Database

				//third we alter the table

				//migrate the Database
				//drop and rename
			}else{
			//	var_dump('identical tables');
				Env::getLogger('sql')->log($this->getTable().' table is identical on server');
			}
		}else{
			//create the table;

			$s= $this->sqlgen->createTable($this->getFields())->query();
			$result = $this->getDB()->executeUpdate($s);

			if(!$result){
					throw new ModelException('SQL ERROR: '.$this->getDB()->getErrorString(),0);
			}
		///	var_dump($s);
		//	var_dump($this->getDB()->getErrorString());
		}

		//resolve relationships, it cannot be solved here.
		Env::getLogger('sql')->endLog('migrating');

	}

	function compareFields($entity){
			Env::getLogger('sql')->log($this->getTable().': comparing fields');
		$f = $this->getFields();
		$f2 = $entity->getFields();
		$changed_fields = array();
		//var_dump($f);
		if(count($f) != count($f2)){
			Env::getLogger('sql')->log($this->getTable().': fields count differs ['.count($f).' vs '.count($f2).']');
			return false;
		}

		foreach($this->getFields() as $field){
		//	var_dump($field->getName());
		//	var_dump($entity->getFieldByAlias($field->getName()));
				Env::getLogger('sql')->log($this->getTable().': checking each field');
				if($f2 = $entity->getFieldByAlias($field->getName())){
					Env::getLogger('sql')->log($field->getName().': exists on server');

					if(
							$f2->getMysqlType() != $field->getMysqlType()
							||
							$f2->getMaxLength() != $field->getMaxLength()
							||
							$f2->getDefaultValue() != $field->getDefaultValue()
							||
							$f2->getCollation() != $field->getCollation()
							||
							$f2->isMandatory() != $field->isMandatory()
							){
				//				var_dump($f2->getName());
Env::getLogger('sql')->log($field->getName()." (type): ".$f2->getMysqlType()."				=	". $field->getMysqlType());
Env::getLogger('sql')->log($field->getName()." (length): ".$f2->getMaxLength()."			=". $field->getMaxLength());
Env::getLogger('sql')->log($field->getName()." (default): ".$f2->getDefaultValue()."	=". $field->getDefaultValue());
Env::getLogger('sql')->log($field->getName()." (collation): ".$f2->getCollation()."		=". $field->getCollation());
Env::getLogger('sql')->log($field->getName()." (mandatory): ".$f2->isMandatory()."		=". $field->isMandatory());

/*var_dump($f2->getMysqlType(), $field->getMysqlType());
var_dump(	$f2->getMaxLength(), $field->getMaxLength());
var_dump($f2->getDefaultValue(),$field->getDefaultValue());
var_dump($f2->getCollation(), $field->getCollation());
	*/							$changed_fields[] = $field;
							//	var_dump($field->getName());
					}
				}else{
					Env::getLogger('sql')->log($field->getName().': exists on server');
					return false;
				}

		}

		return (sizeof($changed_fields) == 0 )? true: $changed_fields;
	}

	function loadFromRS($rs){
	//	var_dump($rs->resultset);
	//	var_dump($rs->getFieldName(0));

		$fields = $rs->getFields();
		//var_dump($fields);
		foreach($fields as $field){
			$eField = EntityField::create()->setEntity($this)->initFromArrayField($field);
			$this->indexField($eField);
		}
		//var_dump($fields);
		return $this;
	}

	function loadFromModelDef($def,$tabledef){
	//	$f = $this->getFields();
		$this->setFields(array());
	//	var_dump(count($f),var_dump($this));
		$this->sqlgen = SQLGenerator::create();
		$this->sqlgen->setTable($this->getTable());

		$this->sqlgen->setEntity($this);
		$this->setStorageEngine($tabledef['mysql_storage_engine']);

		if($tabledef['mysql_collation']!=''){
			$this->setCollation($tabledef['mysql_collation']);
		}else{
			$this->setCollation(Env::getConfig('database')->get('collation'));
		}
		foreach($def as $key => $field){
			$eField = EntityField::create()->setEntity($this)->initFromDef($key,$field);
		//	var_dump($eField);
			$this->indexField($eField);
		}
		$this->loaded = true;
		return $this;
	}

	function indexField($field){

		$this->fieldsByName[$field->getName()]=$field;
		if ($field->isPrimaryKey()){
			$this->fieldsPrimaryKeys[]=$field;
		}
		$this->addFields($field);
	}

	function getFieldByAlias($name){
		//var_dump($this->gettable());
		foreach($this->getFields() as $field){
			//var_dump($field->getAlias());
			if($field->getAlias()==$name){
				return $field;
			}
		}
		return false;
	}
	function getDB(){
		return Env::getDatabase();
	}

	/**
	input: array of field names
	return. array of EntityField
	**/
	function getFieldsByNames($array){
		$datas = array();
		foreach($this->getFields()as $field){
			if(in_array($field->getName(),$array)){
				$datas[] = $field;
			}
		}
		return $datas;
	}

	function setValues($array){
		if(is_array($array)){
			foreach ($array as $key => $value){
				$field = null;
				//var_dump($key);

				if($field = $this->getFieldByAlias($key)){

					$field->setValue($value);
					$field->setDisplayValue($value);
				}
			}
		}
	}

	function setDisplayValues($array){
		if(is_array($array)){
			foreach ($array as $key => $value){
				$field = null;
				//var_dump($key);
				if($field = $this->getFieldByAlias($key)){
					$field->setDisplayValue($value);
				}
			}
		}
	}

	function getValues(){
		$fields = $this->getFields();
		//var_dump($fields);
		$datas = array();
		foreach($fields as  $field){
			$datas[$field->getAlias()]=$field->getValue();
		}
		return $datas;
	}

	//return array with only the valid fields
	function normalize($array){

		$this->clearError();
		$results = array();
		#var_dump($datas);
		if (is_array($array)){
			$success = true;
			foreach($this->getFields() as $field){
				$key = $field->getAlias();
				$results[$key]=$array[$key];

			}

			return $results;
		}
		return false;
	}

/**
	//validate and set the datas in the fields
	// should do transforms here
	// new returns validated data
**/
	function validate($datas,$skipAutoInc= true){

		$this->clearError();
		$displays =  $datas;
		#var_dump($datas);
		if (is_array($datas)){
			$success = true;
			foreach($this->getFields() as $field){
				$key = $field->getAlias();

				if($field->isForeignKey()){
					$key = $field->getKey()->getAlias();
				}
				#var_dump($key);
				$value = $datas[$key];

				// format dates

				if($field->getType() == 'date' && !empty($value) &&($format=Env::getConfig('formats')->get('dateFormatTransform'))){

					if(preg_match('/'.Env::getConfig('formats')->get('dateFormatSourceCheck').'/i',$value)){

						sscanf($value, $format, $day, $month, $year);
						$value = $year."-".$month."-".$day;
					} else{
						if(!$field->isMandatory()){
							$value = null;
						}
					}
				}

				if($field->getType() == 'datetime' && !empty($value) &&($format=Env::getConfig('formats')->get('dateTimeFormatTransform'))){
					//dateTimeFormatSourceCheck

					if(preg_match('/'.Env::getConfig('formats')->get('dateTimeFormatSourceCheck').'/i',$value)){
						sscanf($value, $format, $day, $month, $year,$h,$i,$s);
						$value = $year."-".$month."-".$day." ".intval($h).":".intval($i).":".intval($s);
					//	var_dump($value);
					} else{

						if(!$field->isMandatory() && empty($value)){
							$value = null;
						}
					}
				}

				if($field->getType()=='point'){


					$x = $datas[$key.'_x'];
					$y = $datas[$key.'_y'];
					if($field->isMandatory()){
						if(empty($x) || empty($y)){
							$success = false;
						}
					}
					$value="GeomFromText('POINT(".$x." ".$y.")',0)";

				}
				//var_dump($datas);
				$datas[$field->getAlias()]=$value;

				//skip auto_increment value check
				#var_dump($skipAutoInc,$field->isPrimaryKey(), $field->hasFlag('auto_increment'));
				if(($skipAutoInc && $field->isPrimaryKey() && $field->hasFlags('auto_increment'))){
					continue;
				}
				if(!$field->checkValue($datas[$field->getAlias()])  ){
					$success = false;
					$this->setError($field->getError());
					break;
				}
			}
			//removed, we need the values anyway
		//	if($success){
		#	var_dump($datas);
				$this->setValues($datas);
				$this->setDisplayValues($displays);
		//	}
			return $success;
		}
		return false;
	}


	// magic field get
	public function __call($name, $args){
		//magic setter / getter

		if(($pos = strpos($name, 'field'))===0){ //starting with field ?
			$property = substr($name, 5);
			$property = lcfirst($property);

			return $this->fieldsByName[$property];

		}else{
			return 	parent::__call($name,$args);
		}
	}

	function getPrimaryKeys(){
		return $this->fieldsPrimaryKeys;
	}

	function getPrimaryKeysAssoc(){
		if (is_array($this->getPrimaryKeys())){
			foreach ($this->getPrimaryKeys() as $key => $value) {
				$a[$value->getAlias()]=":".$value->getAlias();
			}
		}
		return $a;
	}


	function filter(){

	}



	function hideAllExcept($array){
		foreach($this->getFields() as $field){
			$alias = "";
		//	var_dump($field);
		//	var_dump($field instanceof EntityField);
			if($field instanceof EntityField){
				$alias = $field->getAlias();
			}else{
				$alias = $field;

				$field = $this->getFieldByAlias($alias);
			//	var_dump($field);
			//	var_dump($alias);
			}
			if(!in_array($alias, $array)){
				$field->setHidden(true);
			}
		}
	}


	//SQL Generation methods

	function query(){

		$query = $this->sqlgen->query();
		$this->query = false;
		$this->where = array();

		return $query;
	}


	function getFieldList($prefix = true){
		$sql = "";
		$sep ="";
		foreach ($this->getFields() as $key => $field) {
			$sql.= $sep.$this->getTable().".".$field->getName()." as `".$field->getAlias()."`" ;
			$sep = ",";
		}
		return $sql;
	}

	function join($fields=""){

		if(empty($fields)){
			$joins = array() ;
			foreach($this->fieldsByName as $field){
				if($e = $field->getForeignKey()){
					$joins[]=$field;
				}
			}
		}else{
			$joins = $fields;
		}

		$this->sqlgen->join($joins);
		return $this;
	}

	function from($array){
		$this->sqlgen->from($array);
		return $this;
	}

	function orderAsc($array){
		$this->sqlgen->orderAsc($array);
		return $this;
	}

	// generate a select query
	function select( $fields = '' ){
		if(empty($fields)){
			$fields = $this->getFields();
		}

		$this->sqlgen->select($fields);

		return $this;
	}

	function insert($fields=''){
		$fields	= $this->unscalarize($fields);

		$fields = $this->objectify($fields);
	/*	if(empty($fields)){
			$fields = $this->getFields();
		}*/

		$q = $this->sqlgen->insert($fields);

		#$this->getModel()->executeUpdate($q);
		#var_dump($this->getModel()->getLastQuery());
		#var_dump($this->getModel()->getError());
		#var_dump($this->getModel()->getLastQuery());
		return $this;
	}

	//update all not ignored fields
	function update($fields=''){
		/*if(empty($fields)){
			$fields = $this->getFields();
		}*/

		$fields	= $this->unscalarize($fields);

		$fields = $this->objectify($fields);
//	var_dump(sizeof($fields));
		$this->sqlgen->update($fields);
		return $this;
	}


function onePKName(){
	$pk = $this->getPrimaryKeys();
	$value = $fields;
	$fields=array();
	if(count($pk)==1){
		$pk= $pk[0]->getName();
	}else{
		$pk = false;
	}
	return $pk;
}
	/**
		Requesting a scalar type means we do a rquest on the primary key.
	**/
	function unscalarize($fields){
		if(is_scalar($fields)){
			$pk = $this->getPrimaryKeys();
			$value = $fields;
			$fields=array();
			if(count($pk)==1){
				$pk= $pk[0];
				$fields[$pk->getName()]=$value;
			}else{
				$this->setError("You cannot request a scalar value when multiple primary keys defined in table",1002);
			}
		}
		return $fields;
	}
	function objectify($fields){
		if(is_array($fields)){
			$this->validate($fields);
			$_fields = array_keys($fields);

			$fields = $this->getFieldsByNames($_fields);
		//	var_dump($fields);
		}
		return $fields;
	}
	function where ($fields=''){
		//hu @TODO set default behavior

		/**
			Requesting a scalar type means we do a rquest on the primary key.
		**/

		$fields	= $this->unscalarize($fields);

		$fields = $this->objectify($fields);
		$this->sqlgen->where($fields);

		return $this;

	}

	function delete($fields=''){
		//by default delete the current key
		if(empty($fields)){
			$fields = $this->getPrimaryKeys();
		}

		if(sizeof($fields)==0){
			throw ModelException("No primary key defined");
		}

		$q = $this->sqlgen->delete($fields)->where($fields)->query();

		return $this;
	}

	function executeUpdate(){
		if(!$this->getModel()){
			throw new ModelException("Method require a model",0);
		}

		if($query = $this->sqlgen->query()){
		#	var_dump($query);
			$this->getModel()->executeUpdate($query);
		}else{
			throw new ModelException("Tryed to run an empty query",0);
		}
	}
	/*
	Methods requiring a model
	*/

	function fetchAll(){
		if(!$this->getModel()){
			throw new ModelException("Method require a model",0);
		}
		$query = $this->select()->query();
		return $this->getModel()->fetchAll($query);
	}

}
