<?php
//chdir(dirname(__DIR__));

include_once('vendor\custom\JWT.php');
include_once('config.php');
include_once('Validate.php');
include_once('DB.php');

/*
 * Get all headers from the HTTP request
 */
$request = $_REQUEST;
$headers = apache_request_headers();
$err = array();
$body = (json_decode(file_get_contents('php://input')));

//capture request to DB
$db = new DB();
if ($body){
        //Log Request
        $result = $db->incoming($body);

        //Check for Duplicate (if transId exists: reject)

        $err = Validate::valid($body);
        if (!empty($err) && $err!="" ){
                echo ('err:'.json_encode($err));
                return false;
        }
        //Verify Signature against client Public Key
        if ( Validate::verify($headers)) {
        
                $method = $body->method;
                $response = $db->transaction($body->requestParams,$method);
                print_r($response);
        }
}
else{
        print_r('invalid json format');
}
        

