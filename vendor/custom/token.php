<?php
//chdir(dirname(__DIR__));
/*
$l_sPrivateKey = 'something returned by database when user loged in';
$l_aData = array();

foreach($_POST as $key => $value){
 if($key == 'signature') continue;
 $l_aData[$key] = $value;
}

//This should then be the same as $_POST['signature'];
hash_hmac('sha256',serialize($l_aData),$l_sPrivateKey, false); 
*/
include_once('vendor\custom\JWT.php');
include_once('Client.php');
include_once('config.php');

/**
 *
 * PHP version 5
 *
 * @modal DB
 * @author   Salma Lalji
 **/
 
class Token {
    
    public static function sign($txtPayload){
       
        if (isset($txtPayload)) {
        
            $txtHeader = array();
            $txtHeader["alg"]="HS256";
            $txtHeader["typ"]="JWT";
            $txtHeader["iss"]="Selcom API";
            $txtHeader["sub"]="api@selcom.net";
            $txtHeader["aud"]="selcom";
            $txtHeader["exp"]="3600";
            
            $mem = new Client();
            $secretKey = $mem->_getSecret(getenv('CLIENT_ID'));
            die(print_r($secretKey));
            $jwt = JWT::encode($txtPayload, $privateKey, "HS256",$txtHeader);
            die(print_r($jwt));
            return $jwt;

            
        }
        else{
            header('HTTP/1.0 400 Bad Request');
            echo('HTTP/1.0 400 Bad Request'.($txtPayload) );
        } 
    }
    public static function verify($bearer){
       
        if (isset($txtPayload)) {
        
                      
            $mem = new Members();
            $secretKey = $mem->_getSecret(getenv('CLIENT_ID'));
            die(print_r($secretKey));
            $state = JWT::decode($bearer, $secretKey, array('HS256'));
            //return true;
            die(print_r($state));        

            
        }
        else{
            header('HTTP/1.0 400 Bad Request');
            echo('HTTP/1.0 400 Bad Request' );
        } 
    }
}

?>
