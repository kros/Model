<?php
namespace Kros\Model;

require_once 'DbException.php';

use Kros\Model\DbException;

abstract class AbstractModel{
   private $tables = null;	
   private $connectionString=null;
   private $cn=null;
   private $user=null;
   private $password=null;

   function __construct($connectionString, $user=null, $password=null){
      $this->connectionString=$connectionString;
	  $this->user=$user;
	  $this->password=$password;
	  $this->tables=array();
   }

   public function getCN(){
      if ($this->cn==null){
         $this->cn = new \PDO($this->connectionString, $this->user, $this->password);
      }
      return $this->cn;
   }

   public function getTable($tableName){
      if (!array_key_exists($tableName, $this->tables)){
         $this->tables[$tableName]=new Table($tableName, $this);
      }
      return $this->tables[$tableName];
   }
   
   function getRecords($sql){
      $res=array();
	  $statement=$this->getCN()->query($sql);
      while ($row = $statement->fetch(\PDO::FETCH_ASSOC)){
         $res[]=$row;
      }
	  return $res;
   }

   function execute($sql){
      $count = $this->getCN()->exec($sql);
	  if ($count===false){
		throw new DbException($this->getCN()->errorInfo(), $sql);
	  }
      return $count;
   }
   function getLastInsertedId(){
         return $this->getCN()->lastInsertId();
   }
   function newRecord($tableName){
	   return new Record($tableName, $this);
   }
   function newRecordset($rows){
	   return new Recordset($rows, $this);
   }
   
   abstract public function getStructure($tableName);
   abstract public function getForeigns($tableName);
   abstract public function getSimpleType($dbType);
   abstract public function GetSQLValueString($theValue, $theType, $theDefinedValue, $theNotDefinedValue);
}
class NotSet{}

class Table{
	private $columns=array();
	private $PKeys=array();
	private $FKeys=array();
	private $tableName;
	private $model;
	public function getTableName(){
		return $this->tableName;
	}
	public function getColumns(){
		return $this->columns;
	}
	public function getColumn($columnName){
		if (array_key_exists($columnName, $this->columns))
			return $this->columns[$columnName];
		else
			throw new \Exception("No existe campo '$columnName' para la tabla '".$this->getTableName()."'");
	}
	public function __construct($tableName, $model){
		$this->model=$model;
		$this->tableName=$tableName;
		$res=$this->model->getStructure($tableName);
		foreach ($res as $linea){
			$c = new Column($linea, $this->model);
			$this->columns[$c->getField()]=$c;
			if ($c->getKey()=='PRI'){
				$this->PKeys[]=$c->getField();
			}
		}		
		$res=$this->model->getForeigns($tableName);
		foreach ($res as $linea){
			$this->FKeys[$linea['columnName']]= new FKey($linea['fTable'], $linea['fColumn'], $this->model);
		}		
	}
	public function getPKeys(){
		return $this->PKeys;
	}
	public function getFKeys(){
		return $this->FKeys;
	}
	public function getFkey($columnName){
		if (array_key_exists($columnName, $this->FKeys))
			return $this->FKeys[$columnName];
		else
			throw new \Exception("No existe campo '$columnName' para la tabla '".$this->getTableName()."'");
	}
	/*** 	
	    Busca la primera aparición
		$condition = array de pares nombres de campo y valor
		$order = array de nombres de campo y valores 'asc' o 'desc'
		Devuelve un objeto MySqlRow.
	*/
	public function seek($condition = array(), $order=array()){
		$where = array();
		$orderBy = array();
		foreach ($condition as $key=>$value){
			if ($value!=NULL && is_object($value) && get_class($value)==NotSet::class){
				// nuevo. el campo no está establecido
			}else{
				$where[] = sprintf("`%s` = %s", $key, $this->getColumn($key)->sqlVal($value));
			}
		}
		foreach ($order as $key=>$value){
			$orderBy[] = sprintf("`%s` %s", $key, $value);
		}
		if (count($where)>0){
			$sWhere = " WHERE ".implode(' and ', $where);
		}else{
			$sWhere = "";
		}
		if (count($orderBy)>0){
			$sOrder = " ORDER BY ".implode(',', $orderBy);
		}else{
			$sOrder = "";
		}
		$sSql = sprintf("SELECT * FROM `%s` %s %s LIMIT 0,1", $this->getTableName(), $sWhere, $sOrder);
		$rows = $this->model->getRecords($sSql);
		if (count($rows)>0){
			return $this->fromRowToModelRow($rows[0]);
		}else{
			//return NULL;
			throw new \Exception("Registro no localizado");
		}
	}
	/*** 	
		Busca todos los registros que cumplan una condición
		$condition = string con la condición de búsqueda
		$order = array de nombres de campo y valores 'asc' o 'desc'
		$start = número de registros donde se empieza
		$len = número de registros que se devuelven, si es 0, se devuelven todos
		Devuelve un array de objetos MySqlRow.
	*/
	public function select($condition='', $order=array(), $start=0, $len=0){
		$orderBy = array();
		foreach ($order as $key=>$value){
			$orderBy[] = sprintf("`%s` %s", $key, $value);
		}
		if (strLen($condition)>0){
			$sWhere = " WHERE ".$condition;
		}else{
			$sWhere = "";
		}
		if (count($orderBy)>0){
			$sOrder = " ORDER BY ".implode(',', $orderBy);
		}else{
			$sOrder = "";
		}
		if ($len==0){
			$limit = '';
		}else{
			$limit = sprintf("LIMIT %s, %s", $start, $len);
		}
		$sSql = sprintf("SELECT * FROM `%s` %s %s %s"
			, $this->tableName, $sWhere, $sOrder, $limit);
		//echo $sSql;	
		$rows = $this->model->getRecords($sSql);
		
		$res = $this->model->newRecordset($this->fromRowsToModelRows($rows));
		$res->setSql($sSql);
		return $res;
	}
	private function fromRowsToModelRows($rows){
		$res = array();
		foreach($rows as $row){
			$res[] = $this->fromRowToModelRow($row);
		}
		return $res;
	}
	private function fromRowToModelRow($row){
		$sr = new Record($this->tableName, $this->model);
		$sr->load($row);
		return $sr;
	}
}
class Column{
	private $field;
	private $type;
	private $null;
	private $default;
	private $key;
	private $extra;
	private $model;
	public function getField(){
		return $this->field;
	}
	public function getType(){
		return $this->type;
	}
	public function getSimpleType(){
		return $this->model->getSimpleType($this->type);
	}
	public function getNull(){
		return $this->null;
	}
	public function getDefault(){
		return $this->default;
	}
	public function getKey(){
		return $this->key;
	}
	public function getExtra(){
		return $this->extra;
	}
	public function esAutoIncremental(){
		$res=strpos($this->extra, 'auto_increment');
		return !($res===false);
	}
	public function __construct($columnAttributes, $model){
		$this->model=$model;		
		$this->field=$columnAttributes['Field'];
		$this->type=$columnAttributes['Type'];
		$this->null=$columnAttributes['Null'];
		$this->key=$columnAttributes['Key'];
		$this->default=$columnAttributes['Default'];
		$this->extra=$columnAttributes['Extra'];
		
	}
	public function toString(){
		return sprintf("Field=%s, Type=%s, Null=%s, Key=%s, Default=%s, Extra=%s"
						, $this->field, $this->type, $this->null, $this->key, $this->default, $this->extra);
	}
	public function phpVal($value){
		if ($value!=NULL && is_object($value) && get_class($value)==NotSet::class){ //nuevo.
			return $value;
		}
		switch ($this->getSimpleType()){
			case "boolean":
				$value = (int)$value!=0;
				break;
			case "int":
				$value = (int)$value;
				break;
			case "double":
				$value = (double)$value;
				break;
			case "text":
				$value = utf8_encode($value);
				break;
			case "date":
				if (is_string($value)){
					$value = new \DateTime($value);
				}else{
					if (is_int($value)){
						$value = new \DateTime(date( 'Y-m-d G:i:s', $value));
					}
				}
				break;

		}
		return $value;
	}
	public function sqlVal($value){
		return utf8_decode($this->model->GetSQLValueString($value, $this->getSimpleType()));
	}
}
class FKey{
	private $tableName;
	private $columnName;
	private $model;
	public function getTableName(){
		return $this->tableName;
	}
	public function setTableName($value){
		$this->tableName=$value;
	}
	public function getColumnName(){
		return $this->columnName;
	}
	public function setColumnName($value){
		$this->columnName=$value;
	}
	public function getTable(){
		return $this->model->getTable($this->tableName);
	}
	public function getColumn(){
		return $this->getTable()->getColumn($this->columnName);
	}
	public function __construct($tableName, $columnName, $model){
		$this->tableName=$tableName;
		$this->columnName=$columnName;
		$this->model=$model;
	}
}
class Record{
	private $table;
	private $fields = array();
	private $key = array();
	private $model;
	private $new;
	public function __construct($tableName, $model){
		$this->model=$model;
		$this->table=$this->model->getTable($tableName);
		foreach($this->table->getPkeys() as $columnName){
			$this->key[$columnName]=new NotSet();//nuevo. antes NULL
		}
		$this->new=TRUE;
	}
	public function load($dataRow){
		foreach($dataRow as $id=>$value){
			$this->setField($id, $value);
		}
		foreach($this->key as $id=>$value){
			$this->key[$id] = $dataRow[$id];
		}
		$this->new=FALSE;
	}
	public function getTable(){
		return $this->table;
	}
	public function getNew(){
		return $this->new;
	}
	public function setNew($value){
		$this->new=$value;
	}	
	public function __get($name){
		return $this->getField($name);

	}
	public function __set($name, $value){
		return $this->setField($name, $value);
	}
	public function __isset($name) {
	        return array_key_exists($name, $this->getTable()->getColumns());
	}
	public function getKey(){
		return $this->key;
	}		
	public function getField($name){
		if (array_key_exists($name, $this->getTable()->getColumns())){
			if (!array_key_exists($name, $this->fields)){
				$this->fields[$name]=new NotSet();//nuevo. antes NULL
			}
			$res=$this->getTable()->getColumn($name)->phpVal($this->fields[$name]);
			return $res;

		}else{
			throw new \Exception("No existe campo '$name' para la tabla '".$this->getTable()->getTableName()."'");
		}
	}
	public function setField($name, $value){
		if (array_key_exists($name, $this->getTable()->getColumns())){
				$this->fields[$name]=$value;
		}else
			throw new \Exception("No existe propiedad '$name' para la tabla '".$this->getTable()->getTableName()."'");		
	}
	public function getFieldsName(){
		return array_keys($this->getTable()->getColumns());
	}
	public function save(){
		//$nuevo=$this->esNuevo();
		$nuevo=$this->new;
		if (!($nuevo || $this->tieneClave())){
			throw new \Exception ("No se puede guardar. No tiene clave");
		}
		if ($nuevo){
			$sqlBase = sprintf("INSERT INTO %s (#fields#) values (#values#)",$this->table->getTableName());
		}else{
			$sqlBase = sprintf("UPDATE %s SET #fields-values# WHERE #primaryKey#",$this->table->getTableName());
		}

		$fields=$this->fieldsToSave();
		$keys=$this->keyToSave();

		foreach($fields as $key=>$value){
			$sqlBase = str_replace('#,fields#', ", `$key`#,fields#", $sqlBase);
			$sqlBase = str_replace('#fields#', "`$key`#,fields#", $sqlBase);
			$sqlBase = str_replace('#,values#', ", $value#,values#", $sqlBase);
			$sqlBase = str_replace('#values#', $value.'#,values#', $sqlBase);
			$sqlBase = str_replace('#,fields-values#', ", `$key` = $value#,fields-values#", $sqlBase);
			$sqlBase = str_replace('#fields-values#', "`$key` = $value#,fields-values#", $sqlBase);
		}
		$sqlBase = str_replace('#,fields#', '', $sqlBase);
		$sqlBase = str_replace('#fields#', '', $sqlBase);
		$sqlBase = str_replace('#,values#', '', $sqlBase);
		$sqlBase = str_replace('#values#', '', $sqlBase);
		$sqlBase = str_replace('#,fields-values#', '', $sqlBase);
		$sqlBase = str_replace('#fields-values#', '', $sqlBase);
		$sqlBase = str_replace('#primaryKey#', implode(' and ', $keys), $sqlBase);
		$newId = $this->model->execute($sqlBase);
		if ($nuevo){
			$this->asignarIdAutoIncremental($newId);
		}
	}
	public function delete(){
		//if ($this->esNuevo()){
		if ($this->new){
			throw new \Exception ("No se puede eliminar. Registro nuevo");
		}else{
			if (!$this->tieneClave()){
				throw new \Exception ("No se puede eliminar. Registro sin clave");
			}
		}
		$sqlBase = sprintf("DELETE FROM `%s` WHERE #primaryKey#", $this->getTable()->getTableName());
		$keys=$this->keyToSave();
		$sqlBase = str_replace('#primaryKey#', implode(' and ', $keys), $sqlBase);
		$this->model->execute($sqlBase);
	}
	public function esNuevo(){
		$nuevo=FALSE;
		foreach($this->table->getPkeys() as $columnName){
			//if ($this->getField($columnName)==NULL ){ 
			if ($this->getField($columnName)==NULL || (is_object($this->getField($columnName)) && get_class($this->getField($columnName))==NotSet::class)){ // nuevo.
				$nuevo=TRUE;
			}
		}
		return $nuevo;
	}
	public function tieneClave(){
		return (count($this->getKey())>0);
	}
	private function asignarIdAutoIncremental($id){
		foreach($this->getTable()->getPKeys() as $columnName){
			if ($this->getTable()->getColumn($columnName)->esAutoIncremental()){
				$this->setField($columnName, $id);
				break;
			}
		}
	}
	private function fieldsToSave(){
		$res=array();
		foreach($this->getTable()->getColumns() as $column){
			$val=$this->getField($column->getField());
			if (!$column->esAutoIncremental() && ($val==NULL || !is_object($val) || get_class($val)!=NotSet::class)){// nuevo.
				$res[$column->getField()]=$column->sqlVal($this->getField($column->getField()));
			}
		}
		return $res;
	}
	private function keyToSave(){
		$res=array();
		//foreach($this->getTable()->getPKeys() as $columnName){
		foreach($this->getKey() as $columnName=>$value){
			//$res[]= "`$columnName` = ".GetSQLValueString($value
			//		, $this->getTable()->getColumn($columnName)->getSimpleType());
			if ($value==NULL || !is_object($value) || get_class($value)!=NotSet::class){// nuevo.
				$res[]= "`$columnName` = ". $this->getTable()->getColumn($columnName)->sqlVal($value);
			}
		}
		return $res;
	}
	public function getMaestro($columnNames){
		$masterTable=NULL;
		$resCondition=array();
		if (is_string($columnNames)){
			$columnNames=array($columnNames);
		}
		foreach($columnNames as $columnName){
			if (!array_key_exists($columnName, $this->getTable()->getFKeys())){
				throw new \Exception (sprintf("Columna %s no tine maestro vinculado", $columnName));
			}
			$fk = $this->getTable()->getFKey($columnName);
			if ($masterTable==NULL){
				$masterTable = $fk->getTableName();
			}else{
				if ($masterTable != $fk->getTable()){
					throw new \Exception ("Columnas maestro hacen referencia a tablas distintas");
				}
			}
			$resCondition[$fk->getColumnName()]=$this->getField($columnName);
		}
		//$t=new MySqlTable($masterTable, $this->getTable()->getModel());
		$t=$this->model->getTable($masterTable);
		return $t->seek($resCondition);
	}
	public function getDetalle($tableName, $condition='', $order=array(), $start=0, $len=0){
		if (!$this->tieneClave()){
			throw new \Exception ("No se puede obtener el detalle. No tiene clave primaria");
		}
		//$t=new MySqlTable($masterTable, $this->getTable()->getModel());
		$t=$this->model->getTable($tableName);
		$res=array();
		foreach($this->getTable()->getPKeys() as $columnName){
			$tiene=FALSE;
			foreach($t->getFKeys() as $cn=>$fk){
				if ($fk->getTableName()==$this->getTable()->getTableName()
					&& $fk->getColumnName()==$columnName){
					if ($tiene){
						throw new \Exception ("Tabla detalle con mas 
								de una relaci�n con la tabla maestro");
					}
					$tiene=TRUE;
					$res[$cn]=$t->getColumn($cn)->sqlVal($this->getField($columnName));
				}
			}
			if (!$tiene){
				throw new \Exception ("La tabla detalle '$tableName' 
						no tiene el campo clave '$columnName'");
			}
		}
		$res2=array();
		foreach($res as $id=>$val){
			$res2[] = "`$id`= $val";
		}
		$condition = trim($condition);
		$condition = (strlen($condition)==0?'':" and (".$condition.")");
		return $t->select("(".implode(' and ', $res2).")".$condition , $order, $start, $len);

	}
	public function evaluar($s){
		return eval('return '.$s.';');
	}
}
class Recordset{
	private $sql = "";	
	private $rows = array();
	private $model = null;
	public function __construct($aRows, $model){
		$this->model=$model;
		if (is_array($aRows)){
			$this->rows = $aRows;
		}else{
			$this->sql=$aRows;
			//los registros no serán MySqlRow, sino simples arrays de clave/valor
			$this->rows = $this->model->getRecords($this->sql);
		}
	}
	public function getRecords(){
		return $this->rows;
	}
	public function recordCount(){
		return count($this->rows);
	}
	public function getSql(){
		return $this->sql;
	}
	public function setSql($value){
		$this->sql = $value;
	}	
	/***
	Devuelve un nuevo recordset resultado de evaluar un filtro
	a cada una de las filas.
	*/
	public function filter($filtro){
		$res = array();
		foreach($this->rows as $row){
			$resFiltro = $row->evaluar($filtro);
			if(is_bool($resFiltro)===TRUE){
				if ($resFiltro===TRUE){
					$res[]=$row;
				}
			}else{
				throw new \Exception ('Filtro no evalua a un valor Booleano');
			}
		}
		//return new MySqlRecordset($res);
		return $this->model->newRecordset($res);
	}
	/***
	Evalua un array a cada una de las filas del recordset
	*/
	public function each($fs){
		foreach ($this->rows as $row){
			foreach ($fs as $f){
				$row->evaluar($f);
			}
		}
	}
	/***
	Hace un echo para cada una de las filas del recordset con el resultado de evaluar el parámetro $f
	*/
	public function echoEach($f, $separador=''){
		$ini = TRUE;
		$sep = '';
		foreach ($this->rows as $row){
			if (!$ini){
				$sep=$separador;
			}
			echo $sep.$row->evaluar($f);
			$ini=FALSE;
		}
	}
}

?>
