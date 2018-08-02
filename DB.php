<?php
//require_once ("config.php");
require_once ("Transactions.php");
/**
 *
 * PHP version 5
 *
 * @modal DB
 * @author   Salma Lalji
 **/
 
class DB extends PDO {

    public $conn =null;
    //private static $objInstance; 
   

    public function __construct() {
        
        if(!$this->conn){
            date_default_timezone_set('Africa/Dar_es_Salaam');
            $dsn = 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST;
            $user = DB_USER;
            $pw = DB_PASS;
            try {
                $this->conn = new PDO( DB_DSN, DB_USER, DB_PASS ); 
                $this->conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
                return $this->conn;
            }
            catch(PDOException $e) {
                echo 'Connection failed: ' . $e->getMessage();;
            }            
        }
        else
        return $this->conn;


    }
    public static function getInstance() {
        if(!self::$objInstance){ 
            date_default_timezone_set('Africa/Dar_es_Salaam');
            $dsn = 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST;
            $user = DB_USER;
            $pw = DB_PASS;
            try {
                self::$objInstance = new PDO( DB_DSN, DB_USER, DB_PASS ); 
                self::$objInstance->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
                return self::$objInstance;
            }
            catch(PDOException $e) {
                echo 'Connection failed: ' . $e->getMessage();;
            }
        }
        return self::$objInstance; 


    }
    public static function getToken($length)
    {
        return substr(str_shuffle(implode("", (array_merge(range(0, 25))))), 0, $length);
    }

    public static function toDateTime($dt){
        if( $dt == date('Y-m-d H:i:s',strtotime($dt)) ){
            // date is in fact in one of the above formats
            return $dt;
        }
        else
        {
            // date is something else.
           return date('Y-m-d H:i:s');
        }
    }
    public static function toDate($dt){
        if( $dt == date('Y-m-d',strtotime($dt)) ){
            // date is in fact in one of the above formats
            return $dt;
        }
        else
        {
            // date is something else.
           return date('Y-m-d',$dt);
        }
    }
    public static function getErrorResponse($data, $err, $ref){
            $message = array();
            $message['status']="Internal Server Error";
            //$message['method']="openAccount";
            $message['data']=($err);
            $respArray = ['transid'=>$data->transid,'reference'=>$ref,'responseCode' => 500, "Message"=>($message)];

           return json_encode($respArray); 
    }
    public function incoming($data) {
       
        try{
           // $params = $this->toString($data);
            $params = json_encode((array)$data);
            //die($data->requestParams->transid);
            //$paramdatetime = $this->toDateTime($data->timestamp);           
            $method = isset($data->method)?$data->method:'';
            $transid = isset($data->requestParams->transid)?$data->requestParams->transid:'';
            //date('Y-m-d H:i:s',$data->timestamp);
            $sql= 'INSERT INTO incoming (fulltimestamp, method, transid,payload) 
                VALUES (now(),  :method, :transid, :payload)';
            $stmt = $this->conn->prepare( $sql );
            //$stmt->bindValue( "paramtimestamp", $paramdatetime, PDO::PARAM_STR );            
            $stmt->bindValue( "method", $method, PDO::PARAM_STR );
            $stmt->bindValue( "transid", $transid, PDO::PARAM_STR );
            $stmt->bindValue( "payload", $params , PDO::PARAM_STR);
            $stmt->execute();
            return true; 
        }
        catch(PDOExecption $e) { 
            return false;
            //$stmt->rollback(); 
            //print "Error!: " . $e->getMessage() . $sql."</br>".$data;            
        } 
        catch(Execption $e ) { 
            //print "Error!: " . $e->getMessage() . $sql. "</br>".$data; 
        } 
        return false;
    }
    public function transaction($data, $method){
        $transaction = new Transactions();
         
        switch($method){
            case 'openAccount':$response = ( $transaction->OpenAccount($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'updateAccount':$response = ($transaction->UpdateAccount($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'cashin': $response = ( $transaction->cashin($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'payutility': $response = ( $transaction->payutility($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'fundTransfer': $response = ( $transaction->transferFunds($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'nameLookup':$response = ($transaction->NameLookup($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'transactionLookup': $response = ($transaction->TransactionLookup($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            //case 'reserveAccount': $response = ( $transaction->reserveAccount($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$response, 3, "access_error.log"); break;
            //case 'unReserveAccount': $response = ( $transaction->unReserveAccount($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$response, 3, "access_error.log"); break;
            case 'changeStatus': $response = ( $transaction->updateAccountStatus($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'requestCard': $response = ( $transaction->requestCard($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'search': $response = ( $transaction->search($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.json_encode($data), 3, "access_error.log"); break;
            //case 'payutility': $response = ( $transaction->ExGratiaPayments($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$response, 3, "access_error.log"); break;
            case 'checkBalance': $response = ( $transaction->checkBalance($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            case 'getStatement': $response = ( $transaction->getStatement($data)); return $response; error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.$response, 3, "access_error.log"); break;
            default: $response = ["transid"=>"","reference"=>"","responseCode"=>"401","Message"=>["status"=>"ERROR","method"=>$method,"data"=>"invalid command method found: ".$method]];error_log("\r\n".date('Y-m-d H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.json_encode($response), 3, "access_error.log"); return json_encode($response);
             
            
        }

    }
}

?>