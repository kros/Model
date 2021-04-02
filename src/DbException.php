<?php
namespace Kros\Model;

/***
 * Excepción para fafilitar información de un error de base de datos
 */
class DbException extends \Exception
{
    private $sqlstate;
    private $driverError;
    private $driverMessage;
    private $sql;

    /***
     * Construye la excepción.
     * @param Array $errorInfo Típico array con una estructura similar a la devuelta por @link https://php.net/manual/en/pdo.errorinfo.php:
     * El elemento 0 debería tener el código de error estándard de SQL (sqlstate).
     * El elemento 1 debería tener el código de error del driver.
     * El elemento 2 debería tener la descripción del error del driver.
     * @param string $sql [optional] Sentencia SQL que ha provocado el error.
     * @return mixed
     */
    public function __construct($errorInfo, $sql=null)
    {
        parent::__construct("[SQLSTATE: $errorInfo[0], DRIVER_ERROR: $errorInfo[1], ERROR_MESSAGE: $errorInfo[2]", $errorInfo[0]);
        $this->sqlstate=$errorInfo[0];
        $this->driverError=$errorInfo[1];
        $this->driverMessage=$errorInfo[2];
        $this->sql=$sql;
    }
    public function getSqlstate(){
        return $this->sqlstate;
    }
    public function getDriverError(){
        return $this->driverError;
    }
    public function getErrorMessage(){
        return $this->driverMessage;
    }
    public function getSql(){
        return $this->sql;
    }
}
?>