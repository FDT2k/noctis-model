<?php
namespace GKA\Noctis\Model;


use \FDT2k\Noctis\Core\Env as Env;

class Database extends AbstractModel{
	public $fieldFilter;
	public $fs;

	function _modelDef(){
		return array();
	}

	function _tableDef(){
		return array();
	}

	function __construct($db=null){
	//	var_dump('test');
		$c = Env::getConfig('database');
		$this->setAutoFree(true);
		//var_dump($c);
		//var_dump($db);
		if (empty($db)){
		//	var_dump('initdb');
			$this->db = Env::getDatabase();
		}else{
			$this->db =$db;
		}
		$memcached = $c->get('memcache_servers');

		if(is_array($memcached) && count($memcached)>0 ){
			$this->mc = new \Memcache();
			foreach($memcached as $m){
				list($server,$port)= explode(":",$m);
				$this->mc->addServer($server,$port);
			}

		}
		$this->init();
	}

	function init(){

		if(is_array($this->_modelDef()) && sizeof($this->_modelDef())>0 && is_array($this->_tableDef()) && sizeof($this->_tableDef()) && Env::getConfig('model')->get('update_schema')){
			$table = $this->_tableDef();
			$datas = $this->_modelDef();
			//prior to initialize entity we have to sanitize relationship definition


				if(isset($table) && is_array($table)){
					if(isset($table['relationships']) && is_array($table['relationships'])){
						foreach($table['relationships'] as $key => $rel){
							$class =$rel['class'];
							//var_dump(class_exists($class));
							if(!empty($class) && class_exists($class) ){
								$o = new $class();
							//	$table['relationships'][$key]['referenced_table']=
								$rel['ref_table']=$o->getEntity()->getTable();
								if (empty($rel['ref_field'])){ // if not defined means that the field names are the same
									$rel['ref_field']=$rel['field'];
								}
								if(empty($rel['name'])){
									$rel['name']=  'fk_'.$rel['ref_field'].'_'.$rel['ref_table'];
								}

								$table['relationships'][$key]=$rel;
							}
						}
					}

				}
			//	var_dump($table);
			$this->setEntity(\GKA\Noctis\Model\Scaffolding\Entity::create($table['name'])->loadFromModelDef($datas,$table));
		}
		if(Env::getConfig('model')->get('update_schema')){
		//	var_dump('init '.Env::getConfig('model')->get('update_schema'));

			$this->update_schema();
		}

	}
	function update_schema(){
//var_dump('updating schem<');

	/*	$table = $this->_tableDef();

		$datas = $this->_modelDef();
*/
	/*	if(is_array($datas) && !empty($datas) && is_array($this->_tableDef()) && !empty($table)){

			//var_dump($table);
		//	$e =  \ICE\lib\scaffolding\Entity::create($table['name']);
			//$existing_tables = $this->db->getTables(Env::getConfig('database')->get('database'));
			//var_dump($e);
			$result = $this->db->updateOrCreateTable($table['name'],$this->dataDefToMySQL());
			var_dump($this->db->getError());
		}*/
	//	$e = \ICE\lib\scaffolding\Entity::create($table['name'])->loadFromModelDef($datas,$table);
		//var_dump($e);

		if(null !== $this->getEntity()){


			$this->getEntity()->migrate();

			//set up fixtures, only if table is not empty
			$data = $this->select();

			if(!$data || count($data)==0){

				if(method_exists(get_class($this),'_fixtureDef')){

					$fixtures = $this->_fixtureDef();

					if(sizeof($fixtures)>0){
						foreach($fixtures as $fix){
				//			var_dump($fix);
							$r = $this->store($fix);
							//var_dump($this->getLastQuery(),$this->getError());
						//	var_dump($this->getLastQuery());
						}
					}
				}
			}
		}
	}

	function dataDefToMySQL(){
		$datas = $this->_dataDef();
		$mysqlFieldList = array();
		foreach($datas as $key=>$field){
			$mysqlField['Field']=$key;
			$mysqlField['Type']=$field['type']."(".$field['maxlength'].")";
			$mysqlFieldList[] = $mysqlField;
		}
		return $mysqlFieldList;
	}

	function prepareValue($value){
		switch($value){
			case ':NOW':
				$value= date("Y-m-d H:i:s");
			break;
			case ':IP':
				$value = $_SERVER['REMOTE_ADDR'];
			break;
		}
		return $value;
	}

	function prepareQuery($query,$values){
	//	throw  new ModelException("debug",0);
		if(!empty($this->query)){
			trigger_error("Query not empty. Don't forget to clear any unfinished query",E_USER_WARNING);
		}
		if(is_array($values)){
			foreach($values as $key => $value){
				$value = $this->prepareValue($value);

				$query = str_replace(':'.$key, $this->db->convertString($value), $query);
			}

		}
		$this->query = $query;
		return $this;
	}

	function prepareSelect($selectedFields=array()){

		if($this->hasEntity()){

			$this->query = $this->getEntity()->select()->query();




		}else{
			throw new \Exception("no entity defined");

		}
		return $this;
	}
	function where($keys=""){
		if(!empty($this->query)){
			if(method_exists($this,"_defaultFilter") && $this->_defaultFilter() && empty($keys)) {
				$keys = $this->_defaultFilter() ;
			}

			if(!is_array($keys)){
				$this->query = $this->getEntity()->whereExp($keys)->query();

			}else{
				$this->query = $this->getEntity()->where($keys)->query();
			}

		}else{
			throw new \Exception("Where used when query empty", 1);
		}
		return $this;
	}

	function executeUpdate($query=""){
		if(empty($query)){
			$query = $this->query;
		}
		$result = $this->db->executeUpdate($query);
		$this->setLastQuery($query);
		if(!$result){

			$this->setError($this->db->getErrorString(),$this->db->getErrorCode());

		}

		$this->query="";
		return $result;
	}

	function loopAndCallback($query="",$callback){
		$datas = false;
		if(empty($query)){
			$query = $this->query;
		}
		$h = md5($query);
		//var_dump($this->mc);

		$this->setLastQuery($query);
		if(!$datas){
			if( ($rs = $this->db->executeQuery($query,$timeout))){


				//*/
				$datas=array();
				while($result = $rs->fetchAssoc()){
				//var_dump($result);
					$callback($result,$rs->getNumRows());

				}
				$rs->free();

			}else{

				$this->setError($this->db->getErrorString());
			}
		}
		$this->query ="";

	}
	// fetchAll and use callback to sort datas
	function fetchAllC($query="",$callback=null,$timeout=-1,$pagesize=0,$currentpage=0){

		$datas = false;
		if(empty($query)){
			$query = $this->query;
		}
		$h = md5($query);
		//var_dump($this->mc);
		if($this->mc && $timeout>-1){
			$datas = $this->mc->get($h);
			//var_dump('get memcache');
		}
		$this->setLastQuery($query);
		if(!$datas){
			if( ($rs = $this->db->executeQuery($query,$timeout))){

			//var_dump($rs);
				///*
				if($pagesize!=0 && $currentpage != 0){
					$rs->setPageSize($pagesize);
					$rs->setCurrentPage($currentpage);
					$this->pageCount=$rs->getPagesCount();
				}
				//*/
				$datas=array();
				while($result = $rs->fetchAssoc()){
				//var_dump($result);
					if($callback){
						$datas[] = $callback($result,$rs->getNumRows());
					}else{
						$datas[] = $this->processRow($result);
					}
				}
				$rs->free();
				if($this->mc && $timeout>-1){
					$this->mc->set($h,$datas);
					//var_dump('set memcache');
				}
			}else{

				$this->setError($this->db->getErrorString());
			}
		}
		$this->query ="";
		return $datas;

	}


	//fetch all and sort by a unique key ()
	function fetchAllS($field,$query="",$timeout=-1){
		$datas = false;
		if(empty($query)){
			$query = $this->query;
		}
		$h = md5($query);
		//var_dump($this->mc);
		if($this->mc && $timeout>-1){
			$datas = $this->mc->get($h);
			//var_dump('get memcache');
		}
		$this->setLastQuery($query);
		if(!$datas){
			if( ($rs = $this->db->executeQuery($query,$timeout))){

				$datas=array();
				while($result = $rs->fetchAssoc()){

					$datas[$result[$field]] = $this->processRow($result);
				}
				$rs->free();
				if($this->mc && $timeout>-1){
					$this->mc->set($h,$datas);
					//var_dump('set memcache');
				}
			}else{

				$this->setError($this->db->getErrorString());
			}
		}
		$this->query ="";
		return $datas;

	}
	function fetchAllSortedByKey($key){
		return $this->fetchAllS($key);
	}

	function fetchAll($query="",$timeout=-1,$pagesize=0,$currentpage=0){
		$datas = false;
		if(empty($query)){
			$query = $this->query;
		}
		$h = md5($query);
		//var_dump($this->mc);
		if($this->mc && $timeout>-1){
			$datas = $this->mc->get($h);
			//var_dump('get memcache');
		}
		$this->setLastQuery($query);
		if(!$datas){
			if( ($rs = $this->db->executeQuery($query,$timeout)) && $rs->hasResult()){

			#var_dump($rs);
				///*
				if($pagesize!=0 && $currentpage != 0){
					$rs->setPageSize($pagesize);
					$rs->setCurrentPage($currentpage);
					$this->pageCount=$rs->getPagesCount();
				}
				//*/
				$datas=array();
				while($result = $rs->fetchAssoc()){
					$datas[] = $this->processRow($result);
				}
				$this->num_rows = $rs->getNumRows();
				if($this->getAutoFree()){
					$rs->free();
				}
				if($this->mc && $timeout>-1){
					$this->mc->set($h,$datas);
					//var_dump('set memcache');
				}
			}else{

				$this->setError($this->db->getErrorString());
				if(Env::getConfig('database')->get('strict')){
					throw new ModelException("Query error: ".$this->db->getErrorString(),0);
				}
			}
		}
		$this->query ="";
		return $datas;
	}

	function fetchOne($query="",$timeout=-1){
		if(empty($query)){
			$query = $this->query;
		}
	//	var_dump($query);
		$rs = $this->db->executeQuery($query);
		$this->setLastQuery($query);
		if(!$rs){
		//	var_dump($query,$rs);
		}
		$result = false;
	//	var_dump($query,$this->db->getError());
		if($rs && $rs->hasResult()){
      if($result = $rs->fetchAssoc()){
      	return $this->processRow($result);
      }
		}else{
			$this->setError($this->db->getErrorString());
			if(Env::getConfig('database')->get('strict')){
				throw new ModelException("Query error: ".$this->db->getErrorString(),0);
			}
		}
	//	var_dump($query,$result);
		$this->query = "";
		return $result;
	}



	function processRow($row){
		if($this->fieldFilter){
			$row= $row[$this->fieldFilter];

			//$this->fieldFilter='';
		}
		if($this->hasFieldSet()){ // if we have a fieldset let's do some autoconv
			$fields = $this->fs->fieldsByName;
			if(!is_array($fields)){return false;}
			foreach ($fields as $oField){
			//foreach($row as $field=>$value){
				//$oField = $this->getFieldSet()->$field;
				$type = $oField->type;

				switch ($type){
					case 'nevershown':
						$row[$oField->trueName]='';
					break;
					case 'datetime':
					//var_dump($oField->name);
						$row[$oField->trueName.'__origin'] = (!empty($row[$oField->trueName])) ? $row[$oField->trueName] : null;
						$row[$oField->trueName.'__human'] = (!empty($row[$oField->trueName])) ? strftime(Env::getConfig('formats')->get('humanDateTimeFormat'),strtotime($row[$oField->trueName])):null;
						$row[$oField->trueName.'__timestamp'] = ( strtotime($row[$oField->trueName]) ? strtotime($row[$oField->trueName]) : "" );
						$row[$oField->trueName] = (!empty($row[$oField->trueName])) ?  date(Env::getConfig('formats')->get('dateFormat').' '.Env::getConfig('formats')->get('hourFormat'),strtotime($row[$oField->trueName])):null;
					break;
					case 'date':
						$row[$oField->trueName.'__human'] = strftime(Env::getConfig('formats')->get('humanDateFormat'),strtotime($row[$oField->trueName]));
						$row[$oField->trueName.'__origin'] = $row[$oField->trueName];
						$row[$oField->trueName.'__timestamp'] = strtotime($row[$oField->trueName]);
						$row[$oField->trueName] = date(Env::getConfig('formats')->get('dateFormat'),strtotime($row[$oField->trueName]));

					break;
				}
			}
		}
		return $row;
	}

	function hasFieldSet(){
		return isset($this->fs);
	}

	function setFieldSet($fs){
		$this->fs = $fs;
		return $this;
	}

	function loadFieldSet($table){
		$this->fs = \ICE\lib\scaffolding\Fieldset::create()->loadFromTable($table);
		return $this->fs;
	}

	function fetchAllTranslation($query,$pkey,$group='i18n.default'){

		$c = Env::getConfig($group);
	//	var_dump($c);
		$showDefault = $c->get('showDefault');
		$translationLangIdentifier = $c->get('translationLangIdentifier');

		$wantsPkeyInTranslationList= $c->get('wantsPkeyInTranslationList');
	//	if(is_array($array)){
			$lang=Env::getTranslator()->lang;

			$p = $pkey;
			$rs = $this->db->executeQuery($query);
			$datas = false;
			while($row = $rs->fetchAssoc()){
			//foreach ($array as $key=>$row){

				if($showDefault){
					if(empty($sorted[$row[$p]]) || $lang == $row[$translationLangIdentifier]){
						if($wantsPkeyInTranslationList){
							$sorted[$row[$p]]=$row;
						}else{
							$sorted[]=$row;
						}
					}
				}else{
					if( $lang == $row[$translationLangIdentifier]){
						if($wantsPkeyInTranslationList){
							$sorted[$row[$p]]=$row;
						}else{
							$sorted[]=$row;
						}
					}
				}

			}
//		}
		return $sorted;
	}

	function fetchOneTranslation($query,$pkey,$group='i18n.default'){

		return $sorted;
	}

	function beginTransaction(){
		if(!$this->db->executeUpdate('START TRANSACTION;')){
			throw new \Exception("Failed to start transaction");
		}
	}

	function rollback(){
		if(!$this->db->executeUpdate('ROLLBACK;')){
			throw new \Exception("Failed to rollback transaction");
		}

	}

	function commit(){
		if(!$this->db->executeUpdate('COMMIT;')){
			throw new \Exception("Failed to commit transaction");
		}

	}

	function order($sort){
		if(is_array($sort) && $sort >0){
			$order = " ORDER BY ";

			foreach($sort as $field => $o){
			//var_dump($field,$order);
				if(empty($order)){
					$o = " asc ";
				}
				$order .= $sep.$field. " ".$o;
				$sep = ",";
			}
		}
		return $order;
	}


	function store($datas,$keys='',$table='',$check=false){
		if(empty($table)){
			$table = $this->getEntity()->getTable();
		}

		if( (!empty($keys)) &&  $r = $this->select($keys,$table)){
			//die();
			//update
			return $this->update($datas,$keys,$table,$check);
		}else{// insert
			return $this->insert($datas,$table,$check);
		}
	}

	function convertValue($field,$value,$table,$function = false){

	#var_dump($field."= ".$value);
		if($value === ':NOW'){
			$value= date("Y-m-d H:i:s");
		}else if($value === ':IP'){
			$value = $_SERVER['REMOTE_ADDR'];

		}
	#	var_dump($field."2= ".$value);

		if(!$function){
			return $this->db->convertString($value);
		}
		return $value;
	}

	function normalizeFields($datas){
		// transforming all the values;
		$new_datas = array();
		foreach($datas as $field => $value){
			$function = false;
			if(strpos($field, '#')===0){ // fields that begins with a # are a function
				$function = true;
				$field = substr($field, 1);
			}
			$new_datas[$field]=$this->convertValue($field,$value,$table,$function);
		}
		return $new_datas;
	}

	function loadFields($table){
		$result =$this->fields[$table];
		if(!is_array($result)){
			$result = $this->db->getFields($table);
			//var_dump($result);
		}
		return $result;
	}

	function delete($keys,$table='',$check=true){
		if(empty($table)){
			$table = $this->getEntity()->getTable();
		}
		$sSQL="delete from %s  where %s";
		$where ="";
		$values="";
		foreach($keys as $field => $value){
			$where .= $sep." `".$field."`=".$this->convertValue($field,$value,$table);
			$sep = " and ";
		}

		$query = sprintf($sSQL,$table,$where);
		return $this->executeUpdate($query);
	}

	function update($datas,$id,$table='',$check=true){

		if(empty($table)){
			$table = $this->getEntity()->getTable();
		}

		if($this->hasEntity()){
			if(is_array($id)){
				$query = $this->getEntity()->update($datas)->where($id)->query();
			}else{
				$query = $this->getEntity()->update($datas)->whereExp($id)->query();
			}
			return $this->executeUpdate($query);

		}else{
			#var_dump($datas);
			// transforming all the values;
			$datas= $this->normalizeFields($datas);
			$id= $this->normalizeFields($id);

			#var_dump($datas);
			if($check){
				$f = $this->loadFields($table);
				$new_datas = array();
				$present_keys = array_keys($datas);
				///var_dump($datas);
				foreach($f as $key=>$value){
					//var_dump($value);
					$field_name = $value['Field'];
					if(in_array($field_name, $present_keys)){
						$new_datas[$field_name]=$datas[$field_name];
					}
				}
				//var_dump($new_datas);
				$datas = $new_datas;
			}

			$sSQL="update %s set %s where %s";
			$where ="";
			$values="";
			foreach($id as $field => $value){

				$where .= $sep." `".$field."`=".$value;
				$sep = " and ";
			}

			$sep = "";
			foreach($datas as $field => $value){

				$values .= $sep." `".$field."`=".$value;
				$sep = ",";
			}
			$query = sprintf($sSQL,$table,$values,$where);
			//var_dump($query);
			return $this->executeUpdate($query);
		}
		return false;
	}

	function insert($datas,$table='',$check=true){
	//	var_dump($datas);
		if(empty($table)){
			$table = $this->getEntity()->getTable();
		}
		if($this->hasEntity()){
			//var_dump($datas);
		/*	$query = $this->fs->loadFromTable($table)->generateInsertStatement($datas);
			if($query){
				$result=  $this->executeUpdate($query);
				//return last primary key if apply.
				var_dump($this->db->lastid());
				if($result && ($id = $this->db->lastid())){

					return $id;
				}
				return true;
			}else{
				$this->setError($this->fs->getError());
			}*/
			$query = $this->getEntity()->insert($datas)->query();

			$result =  $this->executeUpdate($query);
			if($result && ($id = $this->db->lastid())){
				return $id;
			}
			return $result;
		}else{
			if($check){

				$f = $this->loadFields($table);
				$new_datas = array();
				//$present_keys = array_keys($datas);
				///var_dump($datas);
				$valid_keys = array();
				foreach($f as $key=>$value){
					$valid_keys [] = $value['Field'];
				}
//var_dump($valid_keys);
				foreach ($datas as $field => $value){
					if (in_array($field,$valid_keys) ||strpos($field, '#')===0){
						$new_datas[$field]=$value;
					}
				}
				//var_dump($new_datas);
				$datas = $new_datas;
			}
//			var_dump($datas);
			$sSQL="insert into %s (%s) values (%s)";
			$fields ="";
			$values="";

			foreach($datas as $field => $value){
				$function=false;
				if(strpos($field, '#')===0){ // fields that begins with a # are a function
					$function = true;
					$field = substr($field, 1);
				}
				$fields .= $sep." `".$field."`";
				$sep = ",";
			}

			$sep = "";
			foreach($datas as $field => $value){
				$function=false;
				if(strpos($field, '#')===0){ // fields that begins with a # are a function
					$function = true;
					$field = substr($field, 1);
				}

				$values .= $sep." ".$this->convertValue($field,$value,$table,$function);
				$sep = ",";
			}
			$query = sprintf($sSQL,$table,$fields,$values);
			return $this->executeUpdate($query);
		}
		return false;
	}



	function select($keys='',$multiline=false,$table=''){
		//var_dump($this->hasFieldSet());
	//	trigger_error ("Deprecated, use prepareSelect instead",E_USER_WARNING);
		if(empty($table)){
			$table = $this->getEntity()->getTable();
		}
		if($this->hasEntity()){
			if(method_exists($this,"_defaultFilter") && $this->_defaultFilter() && empty($keys)) {
				$keys = $this->_defaultFilter() ;
			}

			if(!is_array($keys)){
				$query = $this->getEntity()->select()->whereExp($keys)->query();

			}else{
				$query = $this->getEntity()->select()->where($keys)->query();
			}

		}else{
			$sSQL="Select * from `%s`  %s";
			$sep = "where";
			if($keys){
				foreach($keys as $field => $value){
					$q .= $sep." ".$field."=".$this->convertValue($field,$value,$table);
					$sep = " and ";
				}
			}

			$query = sprintf($sSQL,$table,$q);


		}

		if(!$multiline){
			$r =  $this->fetchOne($query);
		}else{

			$r =  $this->fetchAll($query);
		}
		if(!$r){
			$this->forwardError($r);
		}
		return $r;
	}

	public function getFields($table){
		if(!is_array($this->fields[$table])){
		//$this->getForeignKeys($table);
		$sSQL="SHOW FULL COLUMNS FROM ".$this->db->convertStringSQL($table);
			$fields = $this->fetchAll($sSQL);
		}else{
			$fields = $this->fields[$table];
		}
		return $fields;
	}

	function assertParams($params,$checks){
		foreach($checks as $key => $rule){
			switch($rule){
				case 'array':

				break;
				case 'number':
				break;
				case 'email':
				break;

				default:
					if(!isset($params[$key]) || empty($params[$key]) || !is_string($params[$key])){
						$this->setError("Incorrect parameter value for ".$key." given:'".var_export($params[$key],true)."'");
						return false;
					}

				breaK;
			}

		}
		return true;
	}


}
