<?php
namespace GKA\Noctis\Model\Scaffolding;
use \FDT2k\Helpers\String as StringHelper;
use \FDT2k\Noctis\Core\Env as Env;

class EntityField extends \IObject {
	/**
		initialize from Array type returned by a mysqli_fetch_field_direct
	**/
	function initFromArrayField($array){
		$this->setName($array['orgname']);
		$this->setAlias($array['name']);
		$this->setLabel($array['name']);

		$this->setTable($array['table']);
		$this->setTable($array['database']);
		$this->setMaxLength($array['max_length']);

		$flag = $array['flags'];
		if(($flag & MYSQLI_NOT_NULL_FLAG) ){
			$this->setMandatory(true);
		}
		if($flag & MYSQLI_PRI_KEY_FLAG){
			$this->setPrimaryKey(true);
		}
		if($flag & MYSQLI_AUTO_INCREMENT_FLAG){
			$this->addFlags('auto_increment');
		}
		$this->init();
		return $this;
	}
	/**
		initialize from Array type by ICE Model definition

		return array(
			'account_id'=>array(
				'name'=>'account_id'
				'type'=>'int',
				'primary'=>true,
				'autoinc'=>true
			),
			'name'=>array(
				'type'=>'string',
				'maxlength'=>255,
				'mandatory'=>false,
				'default'=>NULL
			),
			'email'=>array(
				'type'=>'string',
				'maxlength'=>255,
				'mandatory'=>true,
			),
			'password'=>array(
				'type'=>'string',
				'maxlength'=>255,
				'mandatory'=>true,
				'transform'=>'sha1'
			),
			'valid'=>array(
				'type'=>'int',
				'maxlength'=>1,
				'mandatory'=>false,
			),
			'sample_field'=>array(
				'migrate_from'=>'<2',
				'destination_field'=>NULL
			)
		);
	**/
	function initFromDef($name,$def){
		$this->setHidden(false);
		$type = $def['type'];
		$name = $name;
		$alias = $name;
		$label = $name;
		$maxlength = $def['maxlength'];
		if(isset($def['name'])){
			$name= $def['name'];
		}
		if(isset($def['alias'])){
			$alias = $def['alias'];
		}
		if(isset($def['label'])){
			$label = $def['label'];
		}
		$this->setName($name);
		$this->setAlias($alias);
		$this->setLabel(__($label));
		$this->setTable($table);
		if($type=='string' && $maxlength<=255){
			$type='varchar';
		}
		$this->setType($type);
		$this->setMandatory(boolval($def['mandatory']));
		$this->setSQL(true);
		if($def['primary']==true){
			$this->setPrimaryKey(True);
		}
		if($def['autoinc']){

			$this->addFlags('auto_increment');
		}
		$this->setFunctionValue(false);
		$this->setMaxLength($maxlength);

		if($this->isMandatory()){
			$this->setMinLength(1);
		}

		if(!\isEmpty($def['default'])){
			$this->setDefaultValue($def['default']);
		}

		if($this->isString()){
			if(isset($def['mysql_collation']) ){
				$this->setCollation($def['mysql_collation']);
			}else{
				$this->setCollation($this->getEntity()->getCollation());
			}
		}

		//foreign key creation:
		if(($rel = $def['relation'])!==NULL){
			$foreign_model = new $rel();

	//		$remote_entity =
		if(is_array($foreign_model->_modelDef()) && sizeof($foreign_model->_modelDef())>0 && is_array($foreign_model->_tableDef()) && sizeof($foreign_model->_tableDef())){
			$table = $foreign_model->_tableDef();
			$datas = $foreign_model->_modelDef();

			$entity = \ICE\lib\scaffolding\Entity::create($table['name'])->loadFromModelDef($datas,$table);
			$this->setForeignKey($entity);
			if($field = $entity->getFieldByAlias($def['relationKey'])){
				$this->setKey($field);
			}
			//$this->setType('select');
		}
/*
			$e = Entity::create($o->remoteField->table)->loadFromTable($o->remoteField->table);
			$remoteName= ($o->remoteField->field);

			$this->setForeignKey($e);

			if($field = $e->getFieldByAlias($remoteName)){
				$this->setKey($field);
			}
			*/
		}

		$this->init();
		return $this;
	}

	function isString(){
		return $this->getType()=='string' || $this->getType()=='varchar' || $this->getType()=='text' || $this->getType()=='enum';
	}


	function initWithParams($fieldName,$fieldType,$fieldLength,$table,$fieldAlias,$fieldLabel){}
	/**
		initialize from Array type returned SHOW FULL COLUMNS from mysql
	**/
	function initFromArray($array){
		$type = $array['Type'];
		$length = $type;
		$mandatory = true;

		$this->setSQL(true);
		//extracting type

		$pos = strpos($type,'(');
		if ($pos !== false){
			$type = substr($type,0,$pos);
		}


		//extracting length
		$pos = strpos($length,'(');
		if($pos !==false){
			$len = (strpos($length,')') - ($pos)-1);
			$length = substr($length,$pos+1,$len);
		}
		//extracting enum
		if($type == 'enum'){
			$values =  explode(',',str_replace('\'','',$length));
			$datas = $values;  //for select or listboxes
			$this->setValues($datas);
		} else {
			$datas = '';
		}
		if($array['Null']=='YES'){
			$mandatory = false;
		}

		if($array['Key']=='PRI'){
			$this->setPrimaryKey(true);
		}

		//var_dump($array);

		$this->setFunctionValue(false);
		$this->setType($type);

		$this->setMaxLength($length);

		$this->setMandatory($mandatory);

		if($mandatory){
			$this->setMinLength(1);
		}

		$this->setName($array['Field']);

		$this->setAlias($array['Field']);
		$this->setLabel($array['Field']);

		$this->setFlags($array['Extra']);
//var_dump($array['Collation']);
		$this->setCollation($array['Collation']);
	//	var_dump($array['Collation'],$array['Field']);
		// if this is a foreign key, we load the Entity for the remote table and set the field into the key
		if($o = Env::getDatabase()->getForeignKey($this->getEntity()->getTable(),$this->getName())){
			// setting entity for the field
			//var_dump($o);
			$e = Entity::create($o->remoteField->table)->loadFromTable($o->remoteField->table);
			$remoteName= ($o->remoteField->field);

			$this->setForeignKey($e);

			if($field = $e->getFieldByAlias($remoteName)){
				$this->setKey($field);
			}

			$this->setType('select');
		}
		$this->parseCommentString($array['Comment']);

		$this->init();
		return $this;
	}

	function init(){
		//var_dump($this);

		if($this->isPrimaryKey() && $this->hasFlags('auto_increment')){
			$this->setHidden(true);
			$this->setMandatory(true);
		}
		if($this->getType()=='point'){
			$this->setFunctionValue(true);
		}
		if($this->getType()=='int' && $this->getMaxLength()==1){
			$this->setType('checkbox');
			$this->setMinLength(0);
		}

		switch ($this->getType()) {
			case 'string':
				$mysqlType = 'varchar';
				break;
			case 'checkbox':
		//	var_dump('checkbox');
				$mysqlType = 'int';
				break;
			case 'int':
				if($this->getMaxLength() == 0){
					$this->setMaxLength(11);
				}

			default:
			//var_dump($this->getType());
				$mysqlType = $this->getType();
			}
		$this->setMysqlType($mysqlType);
	}

	public function setValue($value,$overrideDisplay=true){

		if(!$this->isMandatory() && $this->hasForeignKey() && ($value===0 || $value==="0"|| empty($value))){
			$value = NULL;
		}
		if($overrideDisplay){
			$this->setDisplayValue($value);
		}
		return parent::setValue($value);
	}

	public function __ice_magic_set($property,$value){
		parent::__ice_magic_set($property,$value);
		if($property == 'ignore' && $value == true){
			$this->setHidden(true);
		}
		return $this;
	}

	function parseCommentString($string){

		$arr = StringHelper::parseMYSQLCommentString($string);
		//applying
		if(!empty($arr['type'])){
			$this->setType($arr['type']);
			if($this->getType()=='slug'){
				$this->setIgnore(true);
			}
			if($this->getType()=='sortable'){
				$this->setIgnore(true);
			}
			if($this->getType()=='thumb'){
				$this->setIgnore(true);
			}
		}

		if(!empty($arr['model']) && !is_object($this->model)){
			$model = $arr['model'];
			$this->model = new $model();
		}

		if($this->type == AK_FIELD_COMPUTED){
			$this->expression = $arr['expr'];
			$this->setIgnore(true); //normal normal
		}

		switch($arr['state']){
			case 'hidden':

				$this->setHidden(true);
			break;
			case 'ignore':
				$this->setIgnore(true);
			break;
		}

		switch($arr['default']){
			case 'ip':
				$this->setDefaultValue($_SERVER['REMOTE_ADDR']);
			break;
			case 'nowfull':
				$this->setDefaultValue(date(Env::getConfig('formats')->get('dateFormat').' '.Env::getConfig('formats')->get('hourFormat')));
			break;
			case 'now':
				$this->setDefaultValue(date(Env::getConfig('formats')->get('dateFormat')));
			break;
		}

		if(is_array($arr)){
			foreach($arr as $k => $val){
				switch($k){
					case 'dim':
					case 'cropsize':
					case 'resize':
						list($w,$h) = explode("x",$arr[$k]);
						$arr[$k]=array('width'=>$w,'height'=>$h);
					break;
				}
			}
		}

		if($arr['spacing']){
			foreach(count_chars($arr['spacing'],1) as $i => $num){
				if(chr($i)=='*'){
					$this->postspace=  $num;
				}
				if(chr($i)=='-'){
					$this->prespace = $num;
				}
			}
		}

		if($arr['use']){

			if(!is_array($arr['use'])){
				$this->useField($arr['use']);
			}else{
				foreach($arr['use'] as $field){
					$this->useField($field);
				}
			}
		}

		if(!empty($arr['label'])){
			//var_dump($arr);
			$this->setLabel(__($arr['label']));
			//var_dump($this->getLabel());
		}
		return $arr;
	}

	function useField($field){

		$fields = $this->getField();


		if($f = $this->getEntity()->getFieldByAlias($field)){
			$fields[$field]=$f;
			$this->setField($fields);
		}else{

		}

	}


	function checkValue($value){
		#var_dump($this->getAlias(),$value);
		$this->clearError();
		if($this->type == 'point'){
			return true;
		}
		if($this->isIgnore()){
			return true;
		}
		#var_dump($this->getAlias(),$value,$this->isMandatory());
		if($this->type == 'upload' || $this->type=='file'){
			if(!$this->isMandatory() || !empty($_FILES[$this->getAlias()]['name']) || (!StringHelper::isEmpty($value))){
				return true;
			}else{
				$this->setError(sprintf(('Field %1$s: you must select a file'),$this->getLabel()));
			}
		}else{
			if(!$this->isMandatory() || (!StringHelper::isEmpty($value)) || (!StringHelper::isEmpty($this->getDefaultValue()) ) || $this->getType() == 'checkbox'){
				if($this->getMinLength() == 0 || strlen($value)>= $this->getMinLength()){
					if($this->getMaxLength() == 0 || strlen($value) <= $this->getMaxLength()){
						if(!$this->mandatory || $this->getType() != 'int' || (is_numeric($value) || is_float($value))){ // this is wrong
							return true;
						}else{
							$this->setError(sprintf(('Field "%1$s" should be numeric or float'),$this->getLabel()));
						}
					}else{
						$this->setError(sprintf(('Field "%1$s" maximum length is: %2$s'),$this->getLabel(),$this->getMaxLength()));
					}
				}else{
					$this->setError(sprintf(('Field "%1$s" minimal length is: %2$s'),$this->getLabel(),$this->getMinLength()));
				}
			}else{
				$this->setError(sprintf(__('Field "%1$s" cannot be empty'),$this->getLabel()));
			}
		}
		return false;
	}

}
