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
include_once('Members.php');
include_once('config.php');

/**
 *
 * PHP version 5
 *
 * @modal DB
 * @author   Salma Lalji
 **/
 
$txtPayload = $_REQUEST; 
       
        if (isset($txtPayload)) {
        
            $txtHeader = array();
            $txtHeader["alg"]="HS256";
            $txtHeader["typ"]="JWT";
            $txtHeader["iss"]="Selcom API";
            
        
            $secretKey = '$2y$10$jOzDA1saNtPl5hji30iUQOjydhEl8VcJeIKDKZ9UyAijvHHQjv1XW';          
            
        
            $jwt = JWT::encode($txtPayload, $secretKey, "HS256",$txtHeader);
            print_r($jwt);
            return $jwt;
            
        }
        else{
            header('HTTP/1.0 400 Bad Request');
            echo('HTTP/1.0 400 Bad Request'.($txtPayload) );
        } 



?>
