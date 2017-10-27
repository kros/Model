<?php
namespace Kros\Model;
require_once "Model.php";
class MySqlModel extends Model{
	
   /**
   * 
   * Obtiene la estructura de una tabla como un array de claves=>valor.
   * @param $tableName Nombre de la tabla.
   */
   function getStructure($tableName){
      return $this->getCN()->query("describe $tableName")->fetchAll();
   }
   
   function getForeigns($tableName){
      $dbName=$this->getDbName();
      $sql = sprintf("select column_name as columnName, referenced_table_name as fTable
                  , referenced_column_name as fColumn
                  from information_schema.key_column_usage
                  where table_schema=%s and table_name=%s 
                  and referenced_table_name is not null"
                  , $this->GetSQLValueString($dbName,"text")
                  , $this->GetSQLValueString($tableName,"text"));

      $rs=$this->getCN()->query($sql);
      $res=$rs->fetchAll();

      return $res;
   }
   
   function GetSQLValueString($theValue, $theType, $theDefinedValue = "", $theNotDefinedValue = ""){
      if (is_string($theValue)){
         $theValue = (!get_magic_quotes_gpc()) ? addslashes($theValue) : $theValue;
      }

      switch ($theType) {
         case "text":
            $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
            break;    
         case "long":
         case "int":
            $theValue = ($theValue != "" || is_int($theValue)) ? intval($theValue) : "NULL";
            break;
         case "double":
            $theValue = ($theValue != "") ? "'" . doubleval($theValue) . "'" : "NULL";
            break;
         case "date":
         case "time":
            if (is_string($theValue)){	      
               $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
            }else{
               if(is_int($theValue)){
                  $theValue = "'".date( 'Y-m-d G:i:s', $theValue)."'";
               }else{
                  if (is_object($theValue) && get_class($theValue)=='DateTime'){
                     $theValue="'".$theValue->format('Y-m-d G:i:s')."'";
                  }else{
                     if(gettype($theValue)=='NULL'){
                        $theValue="NULL";
                     }else{// a partir de aquí no debería darse
                        if(get_class($theValue)==Kros\Model\NotSet::class){
                           $theValue="NULL";
                        }
                     }
                  }
               }
            }
            break;
         case "boolean":
            $theValue = ((int)$theValue == 0) ? 0 : 1;
            break;
         case "defined":
            $theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
            break;
      }
      return $theValue;
   }
   
   function getDbName(){
      return $this->getCN()->query('select database()')->fetchColumn();	   
   }
   
   function getSimpleType($dbType){
		$dbType = str_replace('tinyint(1)','boolean',$dbType);
		$dbType = str_replace('bit(1)','boolean',$dbType);
		$pos = strPos($dbType,'(');
		if($pos){
			$dbType=substr($dbType, 0, $pos);
		}

		switch ($dbType) {
		    	case "char":
			case "varchar":
			case "text":
			case "longtext":
			case "mediumtext":
			case "blob":
			case "longblob":
				$theValue = "text";
				break;    
			case "long":
			case "tinyint":
			case "bigint":
			case "smallint":
			case "int":
				$theValue = "int";
				break;
			case "decimal":
			case "real":
			case "double":
			case "float":
				$theValue = "double";
				break;
			case "time":
			case "timestamp":
			case "date":
			case "datetime":
				$theValue = "date";
				break;
			case "bool":
			case "boolean":
				$theValue = "boolean";
		}
		return $theValue;
	   
   }
}

?>