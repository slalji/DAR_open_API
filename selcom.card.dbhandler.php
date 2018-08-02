<?php

/**
* Class to handle all db operations
* The class has CRUD methods for database tables
*/
//require_once  dirname(__FILE__).'./utils/utils.php';
require_once ("config.php");
//require_once dirname(__FILE__).'./config.php';

class DbHandler{
	private $conn;
	private $pdo_db;

	static function getVerificationCode(){
		$original_string = array_merge( range(0,9));
		$original_string = implode("", $original_string);
		return substr(str_shuffle($original_string), 0, 4);
	}

	function __construct(){
		/*include_once dirname(__FILE__) . './Config.php';
		require_once dirname(__FILE__) . './Utils.php';
		require_once dirname(__FILE__) . './DB.php';
		include_once dirname(__FILE__) . './Firebase.php';
		include_once dirname(__FILE__) . './ANS.php';
		*/

		$this->pdo_db = DB::getInstance();
		
	}

	//getting a specified token to send push to selected device
	function getDeviceDetails($msisdn){
		if(strlen($msisdn) == 10)
			$msisdn = '255'.substr($msisdn,1);
		$query = "SELECT token,type  FROM card.device_log WHERE msisdn='$msisdn' and active=1";
		$row = $this->pdo_db->query($query)->fetch();
		return array(
			'token'=>$row['token'],
			'type'=>$row['type']
			);
	}

	// function sendFirebaseNotification($token,$msg)
	// {
	//	 $res = array();
	//	 $res['title'] = "";
	//	 $res['body'] = $msg;

	//	 $firebase = new Firebase();
	//	 $ret = $firebase->send($token, $res);
	//	 return $ret;
	// }

	function sendNotification($msisdn,$msg,$id,$action="INFO")
	{
		$deviceDtls = $this->getDeviceDetails($msisdn);

		$type=$deviceDtls['type'];
		$token = $deviceDtls['token'];

		if($type=='android'){
			// $this->sendFirebaseNotification($token,$msg);
			$firebase = new Firebase();

			$res = array();
			$res['title'] = "";
			$res['body'] = $msg;

			$firebase->send($token,$res,$id);

		}elseif($type="ios"){
			$ans = new ANS();
			if(strlen($msg)>50)
				$msg = substr($msg,0,50)."...";
			$ans->send($token,$msg,$id,$action);
		}
	}

	function getPendingNotifications($msisdn)
	{
		$query = "SELECT COUNT(id) as notifs FROM notification WHERE  `read`='0' AND msisdn='$msisdn'";
		$row = $this->pdo_db->query($query)->fetch();
		$count = $row['notifs'];

		return $count;

	}

	private function _get_card_settings(){
		$query = "SELECT dailylimit, failcount, minp2plimit, maxp2plimit, minbplimit, maxbplimit, minb2climit, maxb2climit, minc2blimit, maxc2blimit FROM settings";
		$row = $this->pdo_db->query($query)->fetch();

		$settings['setting_dailylimit'] = $row['dailylimit'];
		$settings['setting_failcount'] = $row['failcount'];
		$settings['setting_minp2plimit'] = $row['minp2plimit'];
		$settings['setting_maxp2plimit'] = $row['maxp2plimit'];
		$settings['setting_minbplimit'] = $row['minbplimit'];
		$settings['setting_maxbplimit'] = $row['maxbplimit'];
		$settings['setting_minb2climit'] = $row['minb2climit'];
		$settings['setting_maxb2climit'] = $row['maxb2climit'];
		$settings['setting_minc2blimit'] = $row['minc2blimit'];
		$settings['$setting_maxc2blimit'] = $row['maxc2blimit'];

		return $settings;
	}

	private function _get_card_info($id){

		$query = "SELECT balance, msisdn, id, name, language, status, state, accountNo, card, pin, failcount, active, stolen, alert, dealer, suspense, dailytrans, last_transaction, holdinglimit, dailylimit, tier, fuel_scheme, fuel_scheme_name, fuel_balance, fuel_last_transaction, fuel_client FROM card WHERE ";

		if(($id)==16){
			$query .= "card='$uid'";
		}else{
			$query .= "msisdn='$id'";
		}
		
		
		$row = $this->pdo_db->query($query)->fetch();

		if($row) {
			$info=array();

			$info['preload'] = 0;
			$info['obal'] = $row['balance'];
			$info['obal_format'] = number_format($row['balance'], 0);
			$info['msisdn'] = $row['msisdn'];
			$info['id'] = $row['id'];
			$info['name'] = $row['name'];
			$info['language'] = $row['language'];

			$ts = date('Y-m-d H:i:s');

			$info['ts'] = $ts;
			$info['status'] = $row['status'];
			$info['state'] = $row['state'];
			$info['accountNo'] = $row['accountNo'];
			$cardnum = $row['card'];

			$info['cardnum'] = $cardnum;
			$info['masked_card'] = "XXXX XXXX XXXX " . substr($cardnum, 12);
			$info['cardpin'] = $row['pin'];
			$info['failcount'] = $row['failcount'];
			$info['active'] = $row['active'];

			$info['stolen'] = $row['stolen'];
			$info['alert'] = $row['alert'];
			$info['dealer'] = $row['dealer'];
			$info['suspense'] = $row['suspense'];
			$info['dailytrans'] = $row['dailytrans'];
			$info['lasttrans'] = substr($row['last_transaction'], 0, 10);
			$info['now'] = substr($ts, 0, 10);
			$info['holdinglimit'] = $row['holdinglimit'];
			$info['dailylimit'] = $row['dailylimit'];
			$info['tier'] = $row['tier'];

			$info['fuel_scheme'] = $row['fuel_scheme'];
			$info['fuel_scheme_name'] = $row['fuel_scheme_name'];
			$info['fuel_balance'] = $row['fuel_balance'];
			$info['fuel_last_transaction'] = $row['fuel_last_transaction'];
			$info['fuel_client'] = $row['fuel_client'];

			// build response with card info
			$resp["resultcode"] = "000";
			$resp["info"] = $info;
		}else{
			$resp["resultcode"] = "056";
			$resp["result"] = "Card not found";
		}

		return $resp;
	}

	private function _checkTransaction($msisdn,$transid){
		$query = "SELECT id FROM transaction WHERE transid='$transid' and msisdn='$msisdn'";
		$row = $this->pdo_db->query($query)->fetch();

		if($row){
			$response['resultcode']="002";
			$response['result'] = "Duplicate request";
		}else{
			$response['resultcode'] = "000";
		}
		return $response;
	}


	function registerDevice($msisdn, $deviceInfo){
		$type = $deviceInfo['device_type'];
		$token = $deviceInfo['device_token'];
		$version = $deviceInfo['version'];
		$cardnum = $deviceInfo['card_number'];
		$latitude = $deviceInfo['latitude'];
		$longitude = $deviceInfo['longitude'];

		# deactivate any pre existing device
		// if($Verify){
		$query = "UPDATE device_log SET date_deactivated=NOW(), active=0 WHERE msisdn='$msisdn' AND active='1'";// AND token_hash=MD5('$cardnum')";
		$this->pdo_db->query($query);
		// }
		$query = "INSERT INTO device_log (msisdn, type, token,lng, lat,active,date_activated,version,card_hash,token_hash) VALUES ('$msisdn', '$type', '$token','$longitude','$latitude','0' ,now(),'$version',MD5('$cardnum'), MD5('$token')) ON DUPLICATE KEY UPDATE token='$token',date_activated=now(), type='$type'";
		$this->pdo_db->query($query);

	}

	function initSession($msisdn, $pin,$deviceInfo){

		$token = generateToken();
		$session_timeout = SESSION_TIMEOUT;
		$query = "INSERT INTO app_auth (msisdn, pin, token , expiry) VALUES ('$msisdn', MD5('$pin'), '$token', now()+ INTERVAL $session_timeout MINUTE) ON DUPLICATE KEY UPDATE pin=MD5('$pin'), token='$token', expiry=NOW()+INTERVAL $session_timeout MINUTE";
		$this->pdo_db->query($query);

		$lat = $deviceInfo['latitude'];
		$lng = $deviceInfo['longitude'];
		$version = $deviceInfo['version'];
		$id = $deviceInfo['id'];

		$query1 = "UPDATE device_log set lng='$lng', lat='$lat', version='$version' WHERE id='$id'";

		$this->pdo_db->query($query1);

		return $token;
	}

	function validateDevice($msisdn, $token, $type){
		$query = "SELECT id,token FROM device_log WHERE msisdn='$msisdn' AND active=1 AND type='$type'";

		 $row = $this->pdo_db->query($query)->fetch();

		if($row){
			$_token = $row['token'];
			if($_token == $token){
				$response['resultcode'] = "000";
				$response['result'] = $row['id'];

			}else{
				$response['resultcode'] = "1009";
				$response['result'] = "Invalid device token";
			}
		}else{
			$response['resultcode'] = '1010';
			$response['result']="Device not registered";
		}
		return $response;
	}

	function isValidSession($msisdn, $token){

		$pin_hash  = '';

		$sth = $this->pdo_db->prepare('SELECT pin, token FROM app_auth WHERE msisdn= ? AND expiry>=NOW()');
		$sth->execute(array($msisdn));
		$row = $sth->fetchAll();
		if(sizeof($row)>0){
			$_token = $row[0]['token'];
			if($_token == $token){
				$pin_hash =  $row[0]['pin'];
			}
		}
		return $pin_hash;
	}

	function invalidateSession($msisdn){
		$query = "UPDATE app_auth SET expiry=now() ,token='' where msisdn='$msisdn'";

		$this->pdo_db->query($query);
	}

	function updateSession($msisdn){
		$this->pdo_db->query("UPDATE app_auth set expiry=NOW()+ INTERVAL 3 MINUTE WHERE msisdn='$msisdn'");
	}

	function registerCard($msisdn,$cardnum,$pin,$deviceInfo){

		// Do we have active existing data in device_log
		// Yes, fail the process, he/she need to deactivate that (what if user uninstalled the app?)
		// No, if its a new device, register it else update it,

		// $query = "SELECT id,type,token,active,card_hash FROM device_log WHERE msisdn='$msisdn' AND card_hash = MD5('$cardnum') AND active=1";

		// $row = $this->pdo_db->query($query)->fetch();
		// if($row && $row['']){
		//	 $response['resultcode'] ="1005";
		//	 $response['result']="Device has active registration";
		// }else
		// {
			// $query = "SELECT id,type,token,active,card_hash FROM device_log WHERE msisdn='$msisdn' AND card_hash = MD5('$cardnum') ORDER BY id DESC LIMIT 1";

			// $row = $this->pdo_db->query($query)->fetch();
			if($msisdn!="NA"){
				$msisdn_or_card = $msisdn;
			}elseif($cardnum!="NA"){
				$msisdn_or_card = $cardnum;
			}

			$card_info = $this->_get_card_info($msisdn_or_card);

			$info = $card_info['info'];



			$token = getVerificationCode(); // select * from card where card='6376630000521762';

			if($cardnum!="NA"){
				$msisdn = $info['msisdn'];
				if($cardnum=='6376630000931920' || $cardnum =="6376630000521762" ){//|| $cardnum == "6376630000555083" ||$cardnum=="6376630000931425" || $cardnum=="6376630001100467" || $cardnum =="6376630000521762" || $cardnum == "6376630000557345" ){
					$token = "2580";
				}
			}else{
				if($msisdn =="255767670921" || $msisdn=="255786190091" || $msisdn == "255682852528" ){//|| $msisdn =="255627870073" || $msisdn=="255742273024" || $msisdn =="255798814154"  || $msisdn=="255682852528"){
					$token = "2580";
				}
			}

			$expiry = $new_time = date("Y-m-d H:i:s", strtotime('+300 seconds'));

			$deviceInfo['card_number']=$cardnum;
			$this->registerDevice($msisdn, $deviceInfo);


			$query2 = "INSERT INTO app_auth(msisdn, pin, token, expiry, verification_token) VALUES ('$msisdn',MD5('$pin'), '','$expiry', '$token') ON DUPLICATE KEY UPDATE pin=MD5('$pin') ,msisdn='$msisdn' , verification_token='$token', expiry='$expiry'";

			$this->pdo_db->query($query2);

			$msg = "$token is your Selcom Card App verification code.";

			send_sms($msisdn,$msg);
		// }


		$response['resultcode'] ="000";
		$response['result'] = "Verification code sent to finish registration";
		$response['msisdn'] = $msisdn;


		#old
		// review active=1 here, we need to update once user has verified
		// $query = "UPDATE card SET msisdn='$msisdn', status='1', fulltimestamp=NOW(),registeredby='SYSTEM', confirmedby='SYSTEM',registertimestamp = NOW(), confirmtimestamp=NOW(), active='0' WHERE card='$cardnum'";

		// $this->pdo_db->query($query);

		// generate sms code


		return $response;
	}

	function activateCard($appcardnum,$appmsisdn,$appname,$apppin,$confirmpin){
		 $msisdn =  $appmsisdn;

		if(strlen($msisdn)==9){
			$msisdn = "255".$msisdn;
		}elseif (strlen($msisdn)==10) {
			$msisdn = "255".substr($msisdn, 1);
		}
		if(strlen($msisdn)!=12){
			$response['resultcode'] = "1006";
			$response['result'] = "Invalid phone number";
			return $response;
		}

		if($appcardnum=="NA"){

			$pin= $apppin;


			$resp = $this->_get_card_info($msisdn);
			
			if($resp['resultcode']=="000"){
				$info = $resp['info'];
				if($info['status']=="0"){
					$token = getVerificationCode();

					if($msisdn =="255767670921"){//} || $msisdn =="255627870073" || $msisdn=="255742273024" || $msisdn=="255798814154"){
						$token = "2580";
					}

					$expiry = $new_time = date("Y-m-d H:i:s", strtotime('+300 seconds'));

					$query2 = "INSERT INTO app_auth(msisdn, pin, token, expiry, verification_token) VALUES ('$msisdn',MD5('$pin'), '','$expiry', '$token') ON DUPLICATE KEY UPDATE pin=MD5('$pin') ,msisdn='$msisdn' , verification_token='$token', expiry='$expiry'";

					$this->pdo_db->query($query2);

					// send token to sms
					$msg = "$token is your Selcom Card App verification code.";

					$response['resultcode']="000";
					$response['result']="Verification code sent your number";

					send_sms($msisdn,$msg);
				}else if ($info['status']=="1" && $info['card']=''){
					$response["resultcode"] = "1005";
					$response["result"] = "Failed. Phone number is active but no card, TPay";
				}
				else {
					$response["resultcode"] = "1004";
					$response["result"] = "Failed. Phone number is active";
				}
			}else{
				if($apppin==$confirmpin){
					$query = "INSERT INTO  card(name, msisdn, active , fulltimestamp, registeredby, registertimestamp, pin ) VALUES ('$appname', '$msisdn', '0', NOW(), 'SYSTEM', NOW(), '$apppin' )";

					$this->pdo_db->exec($query);
					// $trans_id = $this->pdo_db->lastInsertId();

					// $token = getVerificationCode();
					$token = getVerificationCode();

					$expiry = $new_time = date("Y-m-d H:i:s", strtotime('+300 seconds'));

					$query2 = "INSERT INTO app_auth(msisdn, pin, token, expiry, verification_token) VALUES ('$msisdn',MD5('$pin'), '','$expiry', '$token') ON DUPLICATE KEY UPDATE pin=MD5('$pin') ,msisdn='$msisdn' , verification_token='$token', expiry='$expiry'";

					$this->pdo_db->query($query2);

					// send token to sms
					$msg = "$token this is your Selcom Card App verification code.";

					$response['resultcode']="000";
					$response['result']=$msg;//"Verification code sent your number";

					//send_sms($msisdn,$msg);

				}else{
					$response['resultcode'] = "1003";
					$response['result'] = "PIN didn't match";
				}
			}
		}
		else if ($appcardnum=="PALMPAY"){
			
			$resp = $this->_get_card_info($appmsisdn);
			

			if($resp['resultcode']=="000") {
				$info = $resp['info'];
				$active = $info['active'];
				$preload = $info['preload'];
				$id = $info['id'];
				
				if($active=="0"){ // confirm with Sameer/Rosario
					# check if pin was confirmed
					if($apppin==$confirmpin){
						$pin = $apppin;
						$name = $appname;
						//$msisdn = $appmsisdn;

						$query = "UPDATE card SET name='$name',msisdn='$msisdn',status='1', active='0',fulltimestamp=NOW(),registeredby='SYSTEM',registertimestamp=NOW(),confirmtimestamp=NOW(), balance=balance+$preload, pin=$pin WHERE id=$id";

						$this->pdo_db->query($query);

						// generate sms code

						$token = self::getVerificationCode();

						//$masked_card = "XXXX XXXX XXXX " . substr($cardnum, 12);
						$cardnum = $appcardnum;
						

						if($cardnum=='6376630000499522'){//} || $cardnum == "6376630000555083" ||$cardnum=="6376630000931425" || $cardnum=="6376630001100467" || $cardnum =="6376630000521762" || $cardnum == "6376630000557345" ){
							$token = "2580";
						}

						// $query2 = "UPDATE app_auth SET verification_token=$token WHERE vendor=$msisdn";
						$expiry = $new_time = date("Y-m-d H:i:s", strtotime('+300 seconds'));

						$query2 = "INSERT INTO app_auth(msisdn, pin, token, expiry, verification_token) VALUES ('$msisdn',MD5('$pin'), '','$expiry', '$token') ON DUPLICATE KEY UPDATE pin=MD5('$pin') ,msisdn='$msisdn' , verification_token='$token', expiry='$expiry'";

						$this->pdo_db->query($query2);

						// send token to sms/ gms
						// $msg = "You have successful activated your Selcom Card $masked_card, use this code to verify- $token";
						$msg = "$token is your Selcom Card App verification code.";

						$response['resultcode']="000";
						$response['result']=$msg;//"Verification code sent your number";

						//send_sms($msisdn,$msg);

					}else{
						$response['resultcode'] = "1003";
						$response['result'] = "PIN didn't match";
					}
				}else{
					// confirm this with Sameer/Rosario
					$response["resultcode"]="1004";
					$response["result"]="Please use 'Register existing card option to activate the app'";//"Activation failed. Your card is active.";
				}
			}else{
				$response["resultcode"] = "056";
				$response["result"] = "Card not found";
			}
		}
		else{
			
			$resp = $this->_get_card_info($appcardnum);
			 

			if($resp['resultcode']=="000") {
				$info = $resp['info'];
				$active = $info['active'];
				$preload = $info['preload'];
				$id = $info['id'];

				if($active=="0"){ // confirm with Sameer/Rosario
					# check if pin was confirmed
					if($apppin==$confirmpin){
						$pin = $apppin;
						$name = $appname;
						//$msisdn = $appmsisdn;

						$query = "UPDATE card SET name='$name',msisdn='$msisdn',status='1', active='0',fulltimestamp=NOW(),registeredby='SYSTEM',registertimestamp=NOW(),confirmtimestamp=NOW(), balance=balance+$preload, pin=$pin WHERE id=$id";

						$this->pdo_db->query($query);

						// generate sms code

						$token = getVerificationCode();

						//$masked_card = "XXXX XXXX XXXX " . substr($cardnum, 12);
						$cardnum = $appcardnum;

						if($cardnum=='6376630000499522'){//} || $cardnum == "6376630000555083" ||$cardnum=="6376630000931425" || $cardnum=="6376630001100467" || $cardnum =="6376630000521762" || $cardnum == "6376630000557345" ){
							$token = "2580";
						}

						// $query2 = "UPDATE app_auth SET verification_token=$token WHERE vendor=$msisdn";
						$expiry = $new_time = date("Y-m-d H:i:s", strtotime('+300 seconds'));

						$query2 = "INSERT INTO app_auth(msisdn, pin, token, expiry, verification_token) VALUES ('$msisdn',MD5('$pin'), '','$expiry', '$token') ON DUPLICATE KEY UPDATE pin=MD5('$pin') ,msisdn='$msisdn' , verification_token='$token', expiry='$expiry'";

						$this->pdo_db->query($query2);

						// send token to sms/ gms
						// $msg = "You have successful activated your Selcom Card $masked_card, use this code to verify- $token";
						$msg = "$token is your Selcom Card App verification code.";

						$response['resultcode']="000";
						$response['result']="Verification code sent your number";

						send_sms($msisdn,$msg);

					}else{
						$response['resultcode'] = "1003";
						$response['result'] = "PIN didn't match";
					}
				}else{
					// confirm this with Sameer/Rosario
					$response["resultcode"]="1004";
					$response["result"]="Please use 'Register existing card option to activate the app'";//"Activation failed. Your card is active.";
				}
			}else{
				$response["resultcode"] = "056";
				$response["result"] = "Card not found";
			}
		}
		return $response;

	}

	function validateCard($msisdn_or_card,$pin){

		$settings = $this->_get_card_settings();
		$card_info = $this->_get_card_info($msisdn_or_card);

		$setting_failcount = $settings['setting_failcount'];

		if($card_info['resultcode']=="000"){

			$info = $card_info['info'];

			$failcount = $info['failcount'];
			$cardpin = $info['cardpin'];
			$id = $info['id'];
			$status = $info['status'];
			$stolen = $info['stolen'];
			$active = $info['active'];
			// $card = $info['card'];
			// $masked_card = $info['masked_card'];
			if($active==0){
				$response['resultcode'] = "076";
				$response['result'] = "Card not activated";
			}elseif($failcount>=$setting_failcount){
				$response['resultcode'] = "075";
				$response['result'] = "PIN tries exceeded";
			}elseif($status!=1){
				$response['resultcode'] = "045";
				$response['result'] = "Card blocked";
			}elseif($stolen=="1"){
				$response['resultcode'] = "057";
				$response['result'] = "Lost or stolen card";
			}elseif(md5($cardpin)!==trim($pin)){
				$response['resultcode'] = "055";
				$response['result'] = "Incorrect PIN";
				$query = "UPDATE card SET failcount=failcount+1 WHERE id=$id";
				$this->pdo_db->query($query);
			}else{
				$response['resultcode'] = "000";
				$response['result']= "success";
			}
		}else{
			$response = $card_info;
		}

		return $response;

	}

	function verifyAccount($msisdn, $verification_code,$device_token, $device_type){
		// query app_auth, review expiry time
		$query = "SELECT verification_token FROM app_auth where msisdn='$msisdn'";// AND expiry<=NOW()";
		$row = $this->pdo_db->query($query)->fetch();

		if($row){
			$_verification_code = $row['verification_token'];
			if($_verification_code == $verification_code){
				// Update card  to active

				$query = "UPDATE card SET active=1, status=1, confirmtimestamp=NOW() WHERE msisdn='$msisdn'";
				$rs1 =$this->pdo_db->query($query);

				$query2 = "UPDATE device_log SET active=1 WHERE msisdn='$msisdn' AND token='$device_token' AND type='$device_type'";
				$rs2=$this->pdo_db->query($query2);

				if($rs1 && $rs2){
					$response['resultcode'] = "000";
					$response['result'] = "Verified successful";
				}else{
					$response['resultcode'] = "1010";
					$response['result'] = 'Verification failed';
				}

			}else{
				$response['resultcode'] = "1009";
				$response['result'] = "Invalid verification code";
			}
		}else{
			$response['resultcode'] = '1008';
			$response['result']="Verification code not found or expired";
		}

		return $response;
	}

	/**
	 * @param $pin
	 * @param $new_pin
	 * @param $confirm_pin
	 */
	function changePIN($msisdn, $new_pin,$confirm_pin,$deviceInfo){
		if($new_pin==$confirm_pin){

			$query = "UPDATE card SET pin='$new_pin' WHERE msisdn='$msisdn'";
			$this->pdo_db->query($query);

			$token = $this->initSession($msisdn,$new_pin,$deviceInfo);
			$response['resultcode'] = "000";
			$response['result'] = "PIN was changed successful";
			$response['token'] = $token;
		}else{
			$response['resultcode']="1003";
			$response['result'] = "PIN did not match";
		}

		return $response;
	}

	/**
	 * @param $msisdn
	 */
	function deactivateCard($msisdn){
		// set active=0 on card.card
		$query = "UPDATE card.card SET active='0' where msisdn='$msisdn'";
		$this->pdo_db->query($query);
		//
	}

	function deactivateAccount($msisdn){
		$card_info = $this->_get_card_info($msisdn);

		$cardnum=$card_info['info']['cardnum'];
		$query = "UPDATE device_log SET date_deactivated=now(), active=0 WHERE msisdn='$msisdn' AND card_hash=md5('$cardnum')";
		$this->pdo_db->query($query);
	}


	/**
	 * @param $msisdn
	 */
	function switchOffCard($msisdn){
		//$query = "UPDATE card SET active='0' where msisdn='$msisdn'";

		//$this->pdo_db->query($query);
	}

	function createNotification($msisdn,$msg,$action,$amount=0,$utilityref='',$remarks='')
	{
		$query = "INSERT INTO notification(msisdn,msg,action,amount,utilityref,remarks) VALUES ('$msisdn','$msg','$action',$amount,'$utilityref','$remarks')";

		$this->pdo_db->exec($query);
		$trans_id = $this->pdo_db->lastInsertId();
		return $trans_id;
	}

	function loadNotification($id,$msisdn)
	{
		$query = "SELECT id,msg, action, amount, utilityref, remarks, created_at, `read` FROM notification where id=$id  and msisdn='$msisdn'";
		$row = $this->pdo_db->query($query)->fetch();
		if($row){
			$msg = $row['msg'];
			$action = $row['action'];
			$amount = $row['amount'];
			$utilityref = $row['utilityref'];
			$remarks = $row['remarks'];


			if($action!="INFO")
			{
				// $data(
				//	 'amount'=> $amount,
				//	 'utilityref' => $utilityref,
				//	 'description' => $remarks
				// );
				$data['amount'] = $amount;
				$data['utilityref'] = $utilityref;
				$data['description'] = $remarks;
			}else{
				$data = array();
			}

			$response['resultcode'] = "000";
			$response['result'] = "SUCCESS";
			$response['msg'] = $msg;
			$response['action'] = $action;
			$response['data'] = $data;
			$response['read'] = $row['read'];
			$response['createdat'] = $row['created_at'];
			$response['id'] = $row['id'];
		}else{
			$response['msg']  = "Notification not found";
			$response['resultcode'] = "412";
			$response['result'] = "FAIL";
		}
		return $response;
	}

	function updateNotification($id)
	{
		$query = "UPDATE notification SET `read` = 1 WHERE id='$id'";
		$this->pdo_db->query($query);

		$response['result'] = "SUCCESS";
		$response['resultcode'] = "000";
		$response['msg'] = "Notification updated";
		return $response;
	}

	function loadNotifications($msisdn)
	{
		$query = "SELECT id, msg, action, amount, utilityref, remarks, created_at, `read` FROM notification where msisdn='$msisdn' ORDER BY id DESC LIMIT 10";
		$rows = $this->pdo_db->query($query)->fetchAll();
		if($rows){

			$response['resultcode'] = "000";
			$response['result'] = "SUCCESS";

			$notifs = array();

			foreach($rows as $row){
				$tmp = array();
				// $msg = $row['msg'];
				$msg = substr($row['msg'],0,50)."...";
				$action = $row['action'];
				$amount = $row['amount'];
				$utilityref = $row['utilityref'];
				$remarks = $row['remarks'];

				$data = array();

				if($action!="INFO")
				{
					$data = array(
						'amount'=> $amount,
						'utilityref' => $utilityref,
						'description' => $remarks
					);
					// $data['amount'] = $amount;
					// $data['utilityref'] = $utilityref;
					// $data['description'] = $remarks;
				}
				//else{
				//	$data = array();
				//}

				$tmp['msg'] = $msg;
				$tmp['action'] = $action;
				$tmp['data'] = $data;

				$tmp['read'] = $row['read'];
				$tmp['createdat'] = $row['created_at'];
				$tmp['id'] = $row['id'];
				array_push($notifs, $tmp);
			}
			$response['notifications']=$notifs;
		}else{
			$response['msg']  = "Notifications not found";
			$response['resultcode'] = "412";
			$response['result'] = "FAIL";
		}
		return $response;
	}


	function withdraw($msisdn){
		$token = zeropad(rand(1, 999999), 6);
		// link and store the token with reference
		$query = "INSERT INTO smpos.cashout (fulltimestamp, reference, transid, operator, transtype, msisdn, amount, token, balance, expiry, messageid) VALUES (NOW(), '$reference', '$reference', 'SELCOMCARD', 'CASHOUT', '$msisdn', '0', '$token', '0', DATE_ADD(NOW(), INTERVAL+5 MINUTE), '$messageid')";

		$this->pdo_db->exec($query);

		$response['result']='SUCCESS';
		$response['resultcode']='000';
		$response['token']=$token;
		// check language profile from card then parse appropriate sms
		$response['msg']="Use this token within 5 minutes|Token can be used to withdraw cash at any Selcom Agent, Umoja ATM or Selcom Paypoint|You will be required to enter token, phone number and amount to be withdrawn";

		return $response;
	}


	function balanceEnquiry($utilityref){
		$query = "SELECT balance, msisdn, id, name, language, NOW(), status, state, card, active, stolen, dailytrans, last_transaction, holdinglimit, dailylimit, tier FROM card WHERE (msisdn='$utilityref' OR card='$utilityref')";

		$row = $this->pdo_db->query($query)->fetch();
		
		$status = $row['status'];
		$stolen = $row['stolen'];
		$balance = $row['balance'];

		if($status!=1){
			
			$response['resultcode'] = "045";
			$response['result'] = "Card blocked";
		}elseif($stolen=="1"){
			$response['resultcode'] = "057";
			$response['result'] = "Lost or stolen card";
		}else{
			$response['resultcode'] ="000";
			$response['balance'] = $balance;
			$response['reserved'] = 0.0;
			$response['available']  = $balance;
		}
		
		return $response;
	}


	# for balance page
	function statementEnquiryBalance($utilityref){
		$query = "SELECT date_format(fulltimestamp,'%Y%m%d%H%i%s') as ts, reference, amount, transtype, charge,utilitycode from ledger WHERE msisdn='$utilityref' ORDER BY fulltimestamp DESC LIMIT 5";

		$result = $this->statementQuery($query);
		return $result;
	}

	# default fetch for statement page
	function statementEnquiry($utilityref, $days=3){
		$query1 = "SELECT date_format(fulltimestamp,'%Y%m%d%H%i%s') as ts, reference, amount, transtype, charge,utilitycode from ledger WHERE msisdn='$utilityref' and fulltimestamp between now() - INTERVAL $days DAY and now() ORDER BY fulltimestamp DESC";
		$query = "SELECT date_format(fulltimestamp,'%Y%m%d%H%i%s') as ts, reference, amount, transtype, charge,utilitycode from ledger WHERE msisdn='$utilityref'  ORDER BY fulltimestamp DESC LIMIT 7";


		$result = $this->statementQuery($query);
		return $result;
	}

	# for a specific date
	function statementEnquiryDay($utilityref,$date){
		$query = "SELECT date_format(fulltimestamp,'%Y%m%d%H%i%s') as ts,reference, amount, transtype, charge,utilitycode from ledger WHERE msisdn = '$utilityref' and date_format(fulltimestamp,'%Y%m%d')=$date ORDER BY fulltimestamp DESC";

		$result = $this->statementQuery($query);
		return $result;
	}

	# fetch between two days
	function statementEnquiryRange($utilityref, $start, $end, $limit=40){
		$query = "SELECT date_format(fulltimestamp,'%Y%m%d%H%i%s') as ts, reference, amount, transtype, charge,utilitycode FROM ledger WHERE msisdn='$utilityref' AND fulltimestamp BETWEEN '$start' AND '$end' ORDER BY fulltimestamp LIMIT $limit";
		$result = $this->statementQuery($query);
		return $result;
	}

	private function statementQuery($query){
		$response = array();
		$rows = $this->pdo_db->query($query)->fetchAll();

		foreach ($rows as $row) {
				$tmp = array();
				$tmp["date"] = $row["ts"];
				$tmp["biller"] = $row["utilitycode"]; //  company name
				$tmp["amount"] = $row['amount'];
				$tmp["charge"] = $row["charge"];
				$tmp["type"] = $row["transtype"]; // paybill, top up et al

				array_push($response, $tmp);
		}
		return $response;
	}

	 function p2pRequestMoney($transid,$utilityref, $msisdn, $amount, $description){

		$proceed = "OK";

		$card_resp = $this->_get_card_info($msisdn);
		
		if($card_resp['resultcode']=="000"){
			$info = $card_resp['info'];

			$masked_card = $info['masked_card'];
			$name = $info['name'];
			$cardnum = $info['cardnum'];
			$failcount = $info['failcount'];
			$active = $info['active'];
			$status=$info['status'];
			$stolen=$info['stolen'];
			$ts = $info['ts'];
			$id = $info['id'];

			$settings = $this->_get_card_settings();

			$setting_minbplimit= $settings['setting_minbplimit'];
			$setting_maxbplimit= $settings['setting_maxbplimit'];
			$setting_failcount = $settings['setting_failcount'];
			$setting_minp2plimit = $settings['setting_minp2plimit'];
			$setting_maxp2plimit = $settings['setting_maxp2plimit'];

		  if ($failcount>=$setting_failcount) {
				$result = "075";
				$reply = "PIN tries exceeded";
				$proceed="NOK";
			}elseif($active==0){
				$result = "076";
				$reply = "Card not activated";
				$proceed="NOK";
			}elseif($status!=1){
				$result = "045";
				$reply = "Card blocked!";
				$proceed="NOK";
			}elseif($stolen=="1"){
				$result = "057";
				$reply = "Lost or stolen card";
				$proceed="NOK";
			}

			if($proceed=="NOK"){
				$resp['resultcode'] = $result;
				$resp['result'] = $reply;

				return $resp;
			}

			if(strlen($utilityref)!=16){
				if(strlen($utilityref)!=12){
					if(strlen($utilityref)==10){
						$utilityref = "255" . substr($utilityref, 1);
					}
				}
			}

			if(($cardnum==$utilityref) || ($msisdn==$utilityref)) {
				$result = "057";
				$reply = "Invalid destination card";
				$proceed = "NOK";

				$resp['resultcode'] = $result;
				$resp['result'] = $reply;

				return $resp;
			}

			$dest_card_resp = $this->_get_card_info($utilityref);
			if($dest_card_resp['resultcode']!="000"){
				$result= "056";
				$reply = "Destination card not found";
				$proceed = "NOK";

			}else{
				$dest_info = $dest_card_resp['info'];
				$dest_msisdn = $dest_info['msisdn'];
				$dest_name = $dest_info['name'];
				$dest_language = $dest_info['language'];
				$dest_status = $dest_info['status'];
				$dest_state = $dest_info['state'];
				$dest_cardnum = $dest_info['cardnum'];
				$dest_masked_card = $dest_info['masked_card'];
				$dest_active = $dest_info['active'];

				$dest_stolen = $dest_info['stolen'];

				if($dest_status!="1"){
					$result = "045";
					$reply = "Card $dest_masked_card blocked";
					$proceed = "NOK";
				}elseif($dest_stolen=="1"){
					$result = "057";
					$reply = "Lost or stolen card $dest_masked_card";
					$proceed = "NOK";
				}

			}

			if($proceed=="OK"){
				$result = "000";
				$reply = "Request Money was sent to $dest_masked_card";
				$reply2 = "$msisdn requests to send him/her TZS $amount on Selcom Card";
				if(strlen($description)>0){
					$reply2 .=" for $description";
				}
				$response['resultcode'] = $result;
				$response['result'] = $reply;

				$id = $this->createNotification($msisdn,$reply,"INFO");
				$this->sendNotification($msisdn,$reply,$id);
				$id2 = $this->createNotification($dest_msisdn,$reply2,"P2P",$amount,$msisdn,$description);
				$this->sendNotification($dest_msisdn,$reply2,$id,"P2P");


				// send_sms($dest_msisdn,$reply2);
				// send_sms($msisdn, $reply);
			}else{
				$response['resultcode'] = $result;
				$response['result'] = $reply;
			}

		} else {
			$response = $card_resp;
		}
		return $response;
	}

function checkMeter($transid, $reference, $amount, $meter, $seqnum, $vendor) {
	$message = $transid . "|" . $reference . "|" . $amount . "|" . $meter . "|" . $seqnum . "|" . $vendor . "\n";

	if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		return("elec999|LUKU service temporarily unavailable. Please try again later. ($errorcode)");
	}

	$timeout = array('sec'=>40, 'usec'=>40000);
	socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, $timeout);

	if(!@socket_connect($sock, "192.168.22.62", 48602)) {
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		return("elec999|LUKU service temporarily unavailable. Please try again later. ($errorcode)");
	}

	if(!@socket_send($sock, $message, strlen($message), 0)) {
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		return("elec999|LUKU service temporarily unavailable. Please try again later. ($errorcode)");
	}

	if(@socket_recv($sock, $buf, 2045, MSG_WAITALL)===FALSE) {
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		return("elec999|LUKU service temporarily unavailable. Please try again later. ($errorcode)");
	}

	return $buf;
}

function lookup($utilitycode,$utilityref, $amount, $msisdn) {
	$charge = 0;

	if ($utilitycode=="SELCOMPAY") {
		if (substr($utilityref, 0, 1)=="5") {
			$query = "SELECT status, name1 FROM selcompay.pseudo_till WHERE pseudo_id='$utilityref'";
			$row = $this->pdo_db->query($query)->fetch();

			if($row && $row['status']=="1"){

				$lookup_res = array();

				$nameArray = array('label'=>'Name','value'=>$row['name1']);
				array_push($lookup_res, $nameArray);

				$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
				array_push($lookup_res, $amountArray);

				//$chargeArray = array('label'=>'Charge TZS','value'=>$charge);
				//array_push($lookup_res, $chargeArray);

				//$totalArray = array('label'=>'Total TZS','value'=>$charge+$amount);
				//array_push($lookup_res, $totalArray);

				// $lookup_res['name']=  $row['name'];
				// $lookup_res['charge']= $charge;
				// $lookup_res['total'] =$charge+$amount;
				// $lookup_res['amount'] = $amount;

				$response['resultcode'] ="000";
				$response['result'] = 'success';
				$response['txn'] = $lookup_res;
			}else{
				$response['resultcode'] = '989';
				$response['result'] = "Till $utilityref is invalid or does not exist"; //"Vendor not active. Contact Selcom for assistance.";
			}
		} else {
			$query = "SELECT till_status, name FROM smpos.terminal WHERE (till_number='$utilityref' OR till_number_alias='$utilityref')";
			$row = $this->pdo_db->query($query)->fetch();

			if($row && $row['till_status']=="1"){
				$lookup_res = array();

				$nameArray = array('label'=>'Name','value'=>$row['name']);
				array_push($lookup_res, $nameArray);

				$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
				array_push($lookup_res, $amountArray);

				//$chargeArray = array('label'=>'Charge TZS','value'=>$charge);
				//array_push($lookup_res, $chargeArray);

				//$totalArray = array('label'=>'Total TZS','value'=>$charge+$amount);
				//array_push($lookup_res, $totalArray);

				// $lookup_res['name']=  $row['name'];
				// $lookup_res['charge']= $charge;
				// $lookup_res['total'] =$charge+$amount;
				// $lookup_res['amount'] = $amount;

				$response['resultcode'] ="000";
				$response['result'] = 'success';
				$response['txn'] = $lookup_res;
			} else if(strlen($utilityref) ==8) {
				$first_digit = substr($utilityref, 0, 1);

				$lookup_res = array();

				if ($first_digit=="9") {
					$nameArray = array('label'=>'Name','value'=>'Masterpass Payment Reference');
				} else {
					$nameArray = array('label'=>'Name','value'=>'Masterpass Merchant');
				}

				array_push($lookup_res, $nameArray);
				$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
				array_push($lookup_res, $amountArray);

				$response['resultcode'] = '000';
				$response['result'] = "success";
				$response['txn'] = $lookup_res;

			} else {

				$response['resultcode'] = '989';
				$response['result'] = "Till $utilityref is invalid or does not exist"; //"Vendor not active. Contact Selcom for assistance.";
			}
		}

	} elseif ($utilitycode=="LUKU") {
		$query = "SELECT balance, status, c2b, b2c, id, tariff, name FROM card.account WHERE utilitycode='$utilitycode'";

		$row = $this->pdo_db->query($query)->fetch();

		$account_bal = $row['balance'];
		$account_status = $row['status'];
		$account_c2b = $row['c2b'];
		$account_id = $row['id'];
		$account_tariff = $row['tariff'];
		$account_name = $row['name'];

		// get charges
		$charge = 0;
		$query = "SELECT rate, type FROM card.tariff WHERE ($amount BETWEEN lower AND upper) AND code='$account_tariff'";
		$row = $this->pdo_db->query($query)->fetch();

		if ($row) {
			$rate = $row['rate'];
			$type = $row['type'];

			if ($type=="PERCENT") {
				$charge = floor($rate*$amount);
			} else {
				$charge = $rate;
			}
		}

		if ( !is_numeric($utilityref) OR (strlen($utilityref)!=11) ) {
			$result = "996";
			$reply = "Invalid meter number.";

			$response["result"] = substr($reply, 0);
			$response["resultcode"] = substr($result, 0);

			return $response;
		}

		$vendor = "SELCOMCARD";
		$transid = reference();


		$luku_url = "http://192.168.22.93/crdb-gepg/crdb.luku.client.php";

		$reply = file_get_contents($luku_url."?utilityref=$utilityref&reference=$reference&amount=$amount&vendor=SELCOM&service=LOOKUP&msisdn=$msisdn&transid=$transid");


		if (stripos(" ".$reply, "|")>0) {
			$data = explode("|", $reply);
			$result_code = $data[0];
			$reply = trim($data[1]);

			if ( $result_code == "000") {
				$response["resultcode"] = substr("000", 0);
				$response["result"] = "success";

				$lookup_res = array();

				$nameArray = array('label'=>'Name','value'=>$reply);
				array_push($lookup_res, $nameArray);

				$amountArray = array('label'=>'Amount','value'=>'TZS ' . number_format($amount, 0));
				array_push($lookup_res, $amountArray);

				//$chargeArray = array('label'=>'Charge TZS','value'=>$charge);
				//array_push($lookup_res, $chargeArray);

				//$totalArray = array('label'=>'Total TZS','value'=>$charge+$amount);
				//array_push($lookup_res, $totalArray);

				$response['txn'] = $lookup_res;
			} else {
				$response["resultcode"] = substr($result_code, 4, 3);

				$response["result"] = substr($reply, 0);
			}
		} else {
			$message = "LUKU service temporarily unavailable. Please try again later";

			$response["resultcode"] = substr("999", 0);
			$response["result"] = substr($message, 0);
		}
	} elseif ($utilitycode=="PSPF") {
		$url = "http://192.168.22.56/pspfbridge/?service=BE&account=$utilityref";
		$output = file_get_contents($url);

		$status = getElementData("status", $output);
		$description = getElementData("description", $output);

		if ($status=="100") {
			$name = strtoupper(getElementData("name", $output));
		} else {
			$response["resultcode"] = substr($status, 0);
			$response["result"] = substr($description, 0);

			return $response;
		}

		$query = "SELECT type, discount, standard, noshare, tariff FROM smpos.discount WHERE utilitytype='$utilitycode'";
		$row = $this->pdo_db->query($query)->fetch();

		if ($row['type']=="TIERED") {
			$tariff = $row['tariff'];

			$query1 = "SELECT rate FROM smpos.tiered_rates WHERE ($amount BETWEEN lower AND upper) AND code='$tariff'";
			$row = $this->pdo_db->query($query1)->fetch();
			$charge = $row['rate'];
		}

		$total = $amount + $charge;

		$lookup_res = array();

		$nameArray = array('label'=>'Name','value'=>$name);
		array_push($lookup_res, $nameArray);

		$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
		array_push($lookup_res, $amountArray);

		$chargeArray = array('label'=>'Charge TZS','value'=>$charge);
		array_push($lookup_res, $chargeArray);

		$totalArray = array('label'=>'Total TZS','value'=>$total);
		array_push($lookup_res, $totalArray);

		$response['resultcode'] ="000";
		$response['result'] = 'success';
		$response['txn'] = $lookup_res;

		return $response;

	} elseif($utilitycode=="MAGARI") {
		$reference = reference();
		$transid = date('YmdHis');

		$url = "http://192.168.14.10/traapi.php?service=VAR&ref=$utilityref";
		$output = file_get_contents($url);

		$filename2 = "../logs/debug/response_" . $reference . ".txt";
		$fh = fopen($filename2, 'w') or die("can't open file");
		fwrite($fh, $url . "\r\n---\r\n" . $output);
		fclose($fh);

		$resultcode = getElementData("code", $output);
		$description = getElementData("message", $output);
		$startdate = getElementData("startdate", $output);
		if(strlen($startdate)>0){
			$startdate = date_create(substr($startdate, 6, 4) . "-" . substr($startdate, 3, 2) . "-" . substr($startdate, 0, 2));
			$startdate = date_format($startdate, "j M Y");
		}
		if ($resultcode=="100") {
			$resultcode = "000";
			$control_no = getElementData("control_no", $output);
			$amount = getElementData("amount", $output);
			$vendor = $msisdn;
			$query = "INSERT INTO smpos.tra_lookup (fulltimestamp, vendor, cnumber, transid, reference, amount, type, utilityref, utilitycode) VALUES (NOW(), 'SELCOMCARD', '$control_no', '$transid', '$reference', '$amount', 'DOM', '$utilityref', '$utilitycode')";
			$this->pdo_db->exec($query);
		} else {
			if ($resultcode=="") {
				$resultcode = "999";
				$description = "No response from TRA service. Please try again later.";
			}

			if ($resultcode=="203") {
				$resultcode = "999";
				$description = "Premature relicensing not allowed.";
				if(strlen($startdate)>0){
					$description = $description. " Current license expires on $startdate";
				}
			}

			// $response["transid"] = substr($transid, 0);
			// $response["reference"] = substr($reference, 0);
			// $response["message"] = substr($description, 0);
			// $response["resultcode"] = substr($resultcode, 0);
			// $response["result"] = substr("FAIL", 0);

			$response["resultcode"] = substr($resultcode, 0);
			$response["result"] = substr($description, 0);

			return $response;
		}

		$query = "SELECT type, discount, standard, noshare, tariff FROM smpos.discount WHERE utilitytype='$utilitycode'";
		$row = $this->pdo_db->query($query)->fetch();

		if ($row['type']=="TIERED") {
			$tariff = $row['tariff'];

			$query = "SELECT rate FROM smpos.tiered_rates WHERE ($amount BETWEEN lower AND upper) AND code='$tariff'";
			$row = $this->pdo_db->query($query)->fetch();

			if ($row['rate']=="") {
				$resultcode = "999";
				$description = "The amount due TZS " . number_format($amount, 0) . " is more than the transaction limit allowed.";

				// $response["transid"] = substr($transid, 0);
				// $response["reference"] = substr($reference, 0);
				// $response["message"] = substr($description, 0);
				// $response["resultcode"] = substr($resultcode, 0);
				// $response["result"] = substr("FAIL", 0);

				$response["resultcode"] = substr($status, 0);
				$response["result"] = substr($description, 0);

				return $response;
			} else {
				$charge = $row['rate'];
			}
		}

		$total = $amount + $charge;

		$lookup_res = array();

		// $nameArray = array('label'=>'Control #','value'=>$control_no);
		// array_push($lookup_res, $nameArray);

		$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
		array_push($lookup_res, $amountArray);

		$chargeArray = array('label'=>'Charge TZS','value'=>$charge);
		array_push($lookup_res, $chargeArray);

		$totalArray = array('label'=>'Total TZS','value'=>$total);
		array_push($lookup_res, $totalArray);

		$response['resultcode'] ="000";
		$response['result'] = 'success';
		$response['txn'] = $lookup_res;
	} elseif ($utilitycode=="TRAPAYMENT") {

		$reference = reference();
		$transid = date('YmdHis');

		$url = "http://192.168.14.10/traapi.php?service=VX&controlno=$utilityref&serial=$reference";
		$output = file_get_contents($url);

		$filename2 = "../logs/debug/response_" . $reference . ".txt";
		$fh = fopen($filename2, 'w') or die("can't open file");
		fwrite($fh, $url . "\r\n---\r\n" . $output);
		fclose($fh);

		$resultcode = getElementData("code", $output);
		$description = getElementData("description", $output);

		if ($resultcode=="100") {
			$name = trim(strtoupper(getElementData("taxpayer_name", $output)));
			$tin = getElementData("tin", $output);
			$amount = getElementData("amount", $output);
			$tax_department = getElementData("dept_code", $output);

			$vendor = $msisdn;

			$query = "INSERT INTO smpos.tra_lookup (fulltimestamp, vendor, cnumber, transid, reference, amount, type, utilityref, utilitycode, tin, name) VALUES (NOW(), 'SELCOMCARD', '$utilityref', '$transid', '$reference', '$amount', '$tax_department', '$utilityref', '$utilitycode', '$tin', '$name')";
			$this->pdo_db->exec($query);
		} else {
			if ($resultcode=="") {
				$resultcode = "999";
				$description = "No response from TRA service. Please try again later.";
			}

			// $response["transid"] = substr($transid, 0);
			// $response["reference"] = substr($reference, 0);
			// $response["message"] = substr($description, 0);
			// $response["resultcode"] = substr($resultcode, 0);
			// $response["result"] = substr("FAIL", 0);

			$response["resultcode"] = substr($resultcode, 0);
			$response["result"] = substr($description, 0);

			return $response;
		}

		$query = "SELECT type, discount, standard, noshare, tariff FROM smpos.discount WHERE utilitytype='$utilitycode'";
		$row = $this->pdo_db->query($query)->fetch();

		if ($row['type']=="TIERED") {
			$tariff = $row['tariff'];

			$query = "SELECT rate FROM smpos.tiered_rates WHERE ($amount BETWEEN lower AND upper) AND code='$tariff'";
			$row = $this->pdo_db->query($query)->fetch();

			if ($row['rate']=="") {
				$resultcode = "999";
				$description = "The amount due TZS " . number_format($amount, 0) . " is more than the transaction limit allowed.";

				// $response["transid"] = substr($transid, 0);
				// $response["reference"] = substr($reference, 0);
				// $response["message"] = substr($description, 0);
				// $response["resultcode"] = substr($resultcode, 0);
				// $response["result"] = substr("FAIL", 0);

				$response["resultcode"] = substr($status, 0);
				$response["result"] = substr($description, 0);

				return $response;
			} else {
				$charge = $row[0];
			}
		}

		$total = $amount + $charge;

		$lookup_res = array();

		$nameArray = array('label'=>'Name','value'=> $name);
		array_push($lookup_res, $nameArray);

		$tinArray = array('label'=>'TIN','value'=> $tin);
		array_push($lookup_res, $tinArray);

		// $controlArray = array('label'=>'Control #','value'=>$control_no);
		// array_push($lookup_res, $controlArray);

		$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
		array_push($lookup_res, $amountArray);

		$chargeArray = array('label'=>'Charge TZS','value'=>$charge);
		array_push($lookup_res, $chargeArray);

		$totalArray = array('label'=>'Total TZS','value'=>$total);
		array_push($lookup_res, $totalArray);

		$response['resultcode'] ="000";
		$response['result'] = 'success';
		$response['txn'] = $lookup_res;

	}elseif ($utilitycode=="DAWASCO" || $utilitycode=="GEPGCR") {

		if($utilitycode == 'DAWASCO')
			$utilitycode = 'GEPGCR';
		$reference = reference();
		$transid = date('YmdHis');

		$url = "http://192.168.22.93/crdb-gepg/billpay.client.php?utilityref=$utilityref&reference=$reference&transid=$transid&amount=$amount&vendor=$vendor&service=LOOKUP";
		$output = file_get_contents($url);




		$result_arr = explode("|", $output);
		$resultcode = $result_arr[0];
		$description = $result_arr[1];



		if ($resultcode=="000") {
			$customerName = strtoupper($result_arr[1]);
			$dept =  $result_arr[2];
			$paymentOption = $result_arr[4];
			$currency = $result_arr[6];
			$due_amount = $result_arr[7];

			$vendor = $msisdn;


		} else {
			if ($resultcode=="") {
				$resultcode = "999";
				$description = "No response from GEPG service. Please try again later.";
			}

			$response["resultcode"] = substr($resultcode, 0);
			$response["result"] = substr($description, 0);

			return $response;
		}

		$query = "SELECT type, discount, standard, noshare, tariff FROM smpos.discount WHERE utilitytype='$utilitycode'";
		$row = $this->pdo_db->query($query)->fetch();

		if ($row['type']=="C2B") {
			$tariff = $row['tariff'];

			$query = "SELECT rate FROM smpos.tiered_rates WHERE ($amount BETWEEN lower AND upper) AND code='$tariff'";
			$row = $this->pdo_db->query($query)->fetch();

			if ($row['rate']=="") {
				$resultcode = "999";
				$description = "The amount due TZS " . number_format($amount, 0) . " is more than the transaction limit allowed.";

				// $response["transid"] = substr($transid, 0);
				// $response["reference"] = substr($reference, 0);
				// $response["message"] = substr($description, 0);
				// $response["resultcode"] = substr($resultcode, 0);
				// $response["result"] = substr("FAIL", 0);

				$response["resultcode"] = substr($resultcode, 0);
				$response["result"] = substr($description, 0);

				return $response;
			} else {
				$charge_rate = $row[0];
				$charge = floor($charge_rate*$amount);
			}
		}

		$total = $amount + $charge;

		$lookup_res = array();

		$nameArray = array('label'=>'Name','value'=> $customerName);
		array_push($lookup_res, $nameArray);

		$tinArray = array('label'=>'Dept','value'=> $dept);
		array_push($lookup_res, $tinArray);

		// $controlArray = array('label'=>'Control #','value'=>$control_no);
		// array_push($lookup_res, $controlArray);

		$amountArray = array('label'=>'Due TZS','value'=>number_format($due_amount, 0));
		array_push($lookup_res, $amountArray);

		$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
		array_push($lookup_res, $amountArray);

		$chargeArray = array('label'=>'Charge TZS','value'=>$charge);
		array_push($lookup_res, $chargeArray);

		//$totalArray = array('label'=>'Total TZS','value'=>$total);
		//array_push($lookup_res, $totalArray);

		$response['resultcode'] ="000";
		$response['result'] = 'success';
		$response['txn'] = $lookup_res;

	} elseif ($utilitycode=="PPF") {
		$reference = reference();
		$transid = date('YmdHis');

		$url = "http://192.168.22.70/ppf/selcom.ppf.server.php?utilityref=$utilityref&type=VALIDATE&transid=$transid&reference=$reference&msisdn=$msisdn&amount=$amount&vendor=$vendor";
		$output = file_get_contents($url);

		$data = explode("|", $output);

		$resultcode = $data[0];
		$description = $data[1];

		if ($resultcode=="000") {
			$name = $data[2];
		}else{
			$response["resultcode"] = substr($resultcode, 0);
			$response["result"] = substr($description, 0);

			return $response;
		}

		$total = $amount + $charge;

		$lookup_res = array();

		$nameArray = array('label'=>'Name','value'=> $name);
		array_push($lookup_res, $nameArray);

		$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
		array_push($lookup_res, $amountArray);

		$chargeArray = array('label'=>'Charge TZS','value'=>$charge);
		array_push($lookup_res, $chargeArray);

		$totalArray = array('label'=>'Total TZS','value'=>$total);
		array_push($lookup_res, $totalArray);

		$response['resultcode'] ="000";
		$response['result'] = 'success';
		$response['txn'] = $lookup_res;
	} elseif ($utilitycode=="BNMQR") {
		$transid = date('YmdHis');

		$vendor = "SELCOMCARD";
		$pin = "0883";

		$host = "127.0.0.1";
		$uri = "/wwwssl/api/selcom.pos.server.php";

		$request["vendor"] = $vendor;
		$request["pin"] = $pin;
		$request["transid"] = $transid;
		$request["utilitycode"] = "BNMQRLOOKUP";
		$request["utilityref"] = "NA";
		$request["amount"] = $amount;

		$result = xu_rpc_http_concise(
			array(
				"method" => "SELCOM.utilityPayment",
				"args" => array( $request ),
				"host" => $host,
				"uri" => $uri,
				"port" => 8008 ) );

		$member_count = substr_count($result, "<member>");
		$xml = simplexml_load_string($result) or die("feed not loading");

		for($i=0; $i<$member_count; $i++) {
			$element_name = $xml->params->param->value->struct->member[$i]->name;
			$element_data = $xml->params->param->value->struct->member[$i]->value->string;

			if ($element_name=="result") {
				$result = zeropad($element_data, 3);
			}

			if ($element_name=="message") {
				$message = $element_data;
			}
			if ($element_name=="reference") {
				$reference = $element_data;
			}
			if ($element_name=="resultcode") {
				$resultcode = $element_data;
			}
			if ($element_name== "event_desc"){
				$desc = $$element_data ;
			}
			if($element_name=="event_title"){
				$title = $element_data;
			}
			if($element_name=="amount"){
				$amount = $element_data;
			}
			if($element_name=="event_time"){
				$event_time = $element_data;
			}

			if($element_name=="event_venue"){
				$event_venue = $element_data;
			}
			if($element_name=="seating_class" ){
				$seating_class= $element_data;
			}
		}

		$transid = substr($transid, 0);
		$reference = substr($reference, 0);
		$result = substr($result, 0);
		$resultcode = substr($resultcode, 0);
		$message= substr($message, 0);

		if($resultcode==""){
			$resp = array("resultcode"=>"999",
				"result"=>"Error, please try again later");
			return $resp;
		}

		if($resultcode!="000"){
			return array(
				"resultcode"=>$resultcode,
				"result"=>$message);
		}

		$query = "SELECT type, discount, standard, noshare, tariff FROM smpos.discount WHERE utilitytype='$utilitycode'";
		 $row = $this->pdo_db->query($query)->fetch();

		if ($row['type']=="TIERED") {
			$tariff = $row['tariff'];

			$query = "SELECT rate FROM smpos.tiered_rates WHERE ($amount BETWEEN lower AND upper) AND code='$tariff'";
			$row = $this->pdo_db->query($query)->fetch();

			if ($row['rate']=="") {
				$resultcode = "999";
				$description = "The amount due TZS " . number_format($amount, 0) . " is more than the transaction limit allowed.";

				$response["resultcode"] = substr($status, 0);
				$response["result"] = substr($description, 0);

				return $response;
			} else {
				$charge = $row['rate'];
			}
		}

		$total = $amount + $charge;


		// its a success
		//
		//

		$title = substr($title, 0);
		$event_desc = substr($desc, 0);
		$amount = substr($amount, 0);
		$event_time = substr($event_time, 0);
		$event_venue = substr($event_venue, 0);
		$seating_class = substr($seating_class, 0);

		$lookup_res = array();

		$titleArray = array('label'=>'Title','value'=>$title);
		array_push($lookup_res, $titleArray);

		// $descArray = array('label'=>'Description','value'=>$event_desc);
		// array_push($lookup_res, $descArray);

		$amountArray = array('label'=>'Amount TZS','value'=>number_format($amount, 0));
		array_push($lookup_res, $amountArray);

		$timeArray = array('label'=>'Time','value'=>$event_time);
		array_push($lookup_res, $timeArray);

		$venueArray = array('label'=>'Venue','value'=>$event_venue);
		array_push($lookup_res, $venueArray);

		if($seating_class){
			$classArray = array('label'=>'Seating Class','value'=>$seating_class);
			array_push($lookup_res, $classArray);
		}

		$response['resultcode'] ="000";
		$response['result'] = 'success';
		$response['txn'] = $lookup_res;
	}else {
		$response['resultcode'] = '412';
		$response['result'] = 'Not implemented';
	}

	return $response;
}
//fundTransfer
function fundTransfer($transid,$reference,$utilityref,$msisdn,$amount){
		$proceed = "OK";
		$vendor = "TRANSSNET";
		$service = "fundTransfer";
		$channel = "TPAY";
		
		// check if transaction exists
		$chk_resp = $this->_checkTransaction($msisdn,$transid);
		if($chk_resp['resultcode']=="000"){
			//$reference = $reference;

			$card_resp = $this->_get_card_info($msisdn);
			
			if($card_resp['resultcode']=="000"){
				$info = $card_resp['info'];
				
				$masked_card = $info['masked_card'];
				$name = $info['name'];
				$dealer = 'Transsnet';//$info['dealer'];//check if Transsnet
				$cardnum = $info['cardnum'];

				/*$settings = $this->_get_card_settings();

				$setting_minbplimit= $settings['setting_minbplimit'];
				$setting_maxbplimit= $settings['setting_maxbplimit'];
				$setting_failcount = $settings['setting_failcount'];
				$setting_minp2plimit = $settings['setting_minp2plimit'];
				$setting_maxp2plimit = $settings['setting_maxp2plimit'];
*/
				// save the transaction
				$query = "INSERT INTO transaction (fulltimestamp, transid, reference, vendor, card, amount, initiate_ts, channel, utilitycode, name, msisdn, dealer, type, utilityref) VALUES (NOW(), '$transid', '$reference', '$vendor', '$masked_card', '0', NOW(), '$channel', '$service', '$name', '$msisdn', '$dealer', 'DEBIT', '$msisdn')";
				$this->pdo_db->exec($query);
				$trans_id = $this->pdo_db->lastInsertId();
				

				// load account settings
				//$query = "SELECT balance, status, c2b, b2c, id, tariff, name FROM card.account WHERE utilitycode='P2P'";
				$query = "SELECT id, balance, status, accountNo, /*c2b, b2c, id, tariff,*/ CONCAT(firstname, ' ', lastname) as name FROM accountprofile WHERE msisdn=$msisdn";
				
				$row = $this->pdo_db->query($query)->fetch();
				if (empty($row)){
					$result = "057";
					$reply = "Invalid account";
					$proceed = "NOK";
					$resp['resultcode'] = $result;
					$resp['result'] = $reply;
					$query = "UPDATE transaction SET result='$result', message='$reply', complete_ts=NOW(), amount='$amount' WHERE id=$trans_id";
					$this->pdo_db->query($query);
					return $resp;
				}
				 
				$account_bal = $row['balance'];
				$account_status = (int)$row['status'];
				 
				//$account_c2b = $row['c2b'];
				$account_id = $row['id'];
				$account_no = $row['accountNo'];
				//$account_tariff = $row['tariff'];
				$account_name = $row['name'];
			 

				 // get charges
				$charge = 0;
				/*$query = "SELECT rate, type FROM card.tariff WHERE ($amount BETWEEN lower AND upper) AND code='$account_tariff'";
				$row = $this->pdo_db->query($query)->fetch();

				if ($row) {
					$rate = $row['rate'];
					$type = $row['type'];

					if ($type=="PERCENT") {
						$charge = floor($rate*$amount);
					} else {
						$charge = $rate;
					}
				}
				*/

				$obal = $info['obal'];
				
				$failcount = $info['failcount'];
				$active = $info['active'];
				$status=$info['status'];
				$stolen=$info['stolen'];
				$ts = $info['ts'];
				$id = $info['id'];
				
				$now = $info['now'];
				$lasttrans = $info['lasttrans'];
				$dailytrans = $info['dailytrans'];
				$dailylimit = $info['dailylimit'];
				
				

				//if (($account_status!="1") OR ($account_c2b!="1")) {
				if (($account_status != 1) ) {
					 
					$result = "045";
					$reply = "Account $account_name is closed";
					$proceed="NOK";
				}/*elseif ($failcount>=$setting_failcount) {
					$result = "075";
					$reply = "PIN tries exceeded";
					$proceed="NOK";
				}*/elseif($active!=0){
					 
					$result = "076";
					$reply = "Card not activated";
					$proceed="NOK";
				}elseif($status!=1){
					 			
					$result = "045";
					$reply = "Card blocked";
					$proceed="NOK";
				}/*elseif($stolen=="1"){
					$result = "057";
					$reply = "Lost or stolen card";
					$proceed="NOK";
				}elseif(($amount < $setting_minp2plimit) OR ($amount > $setting_maxp2plimit)) {
					$reply = "Invalid amount";
					$result = "013";
					$proceed="NOK";
				}*/elseif(($amount + $charge) > $obal) {
					 					
					$result = '051';
					$reply = "Insufficient funds";
					$proceed="NOK";
				}

				if($proceed=="NOK"){
					$resp['resultcode'] = $result;
					$resp['result'] = $reply;

					$query = "UPDATE transaction SET result='$result', message='$reply', complete_ts=NOW(), amount='$amount' WHERE id=$trans_id";
					$this->pdo_db->query($query);
					

					return $resp;
				}
				
				$running = "0";

				if($now==$lasttrans){
					$running = $dailytrans + $amount + $charge;

					if($running>$dailylimit){
						$result = '065';
						$reply = 'Transaction exceeds daily transaction limit';
						$proceed = "NOK";
					}
				}else{
					$running = $amount + $charge;
				}
//transfer to msisdn

				if (strlen($utilityref)!=16){
					if(strlen($utilityref)!=12){
						if(strlen($utilityref)==10){
							$utilityref = "255" . substr($utilityref, 1);
						}
					}
				}
				//cannot have same card number
				if(($cardnum==$utilityref) || ($msisdn==$utilityref)) {
					$result = "057";
					$reply = "Invalid destination card";
					$proceed = "NOK";
				}
			
				if($proceed=="NOK"){
					$resp['resultcode'] = $result;
					$resp['result'] = $reply;

					$query = "UPDATE transaction SET result='$result', message='$reply', complete_ts=NOW(), amount='$amount' WHERE id=$trans_id";
					$this->pdo_db->query($query);

					return $resp;
				}

				// get destination card details
			   $dest_card_resp = $this->_get_card_info($utilityref);
			  
			   
				if($dest_card_resp['resultcode']!="000"){
					$result= "056";
					$reply = "Destination card not found";
					$proceed = "NOK";
					$resp['resultcode'] = $result;
					$resp['result'] = $reply;

					return $resp;

				}
				else{
					
					$dest_info = $dest_card_resp['info'];
 
					$dest_obal = $dest_info['obal'];
					$dest_obal_format = $dest_info['obal_format'];
					$dest_msisdn = $dest_info['msisdn'];
					$dest_id = $dest_info['id'];
					$dest_name = $dest_info['name'];
					$dest_language = $dest_info['language'];
					$dest_ts = $dest_info['ts'];
					$dest_status = $dest_info['status'];
					$dest_state = $dest_info['state'];
					$dest_cardnum = $dest_info['cardnum'];
					$dest_masked_card = $dest_info['masked_card'];
					$dest_active = $dest_info['active'];

					$dest_stolen = $dest_info['stolen'];
					$dest_dailytrans = $dest_info['dailytrans'];
					$dest_lasttrans = $dest_info['lasttrans'];
					$dest_now = $dest_info['now'];
					$dest_holdinglimit = $dest_info['holdinglimit'];
					$dest_dailylimit = $dest_info['dailylimit'];
					$dest_tier = $dest_info['tier'];
					$dest_account_no = $dest_info['accountNo'];
					

					if($dest_status!=="1"){
						$result = "045";
						$reply = "Card ".$dest_masked_card." blocked";
						$proceed = "NOK";
					}elseif($dest_stolen=="1"){
						$result = "057";
						$reply = "Lost or stolen card $dest_masked_card";
						$proceed = "NOK";
					}

					if($proceed=="NOK"){
						$resp['resultcode'] = $result;
						$resp['result'] = $reply;
						
						$query = "UPDATE transaction SET result='$result', message='$reply', complete_ts=NOW(), amount='$amount' WHERE id=$trans_id";
						$this->pdo_db->query($query);

						return $resp;
					}
					
					$dest_running = "0";

					if($dest_now==$dest_lasttrans){
						$dest_running = $dest_dailytrans + $amount;

						if($dest_running>$dest_dailylimit){
							$result = '065';
							$reply = 'Transaction exceeds daily transaction limit for $dest_masked_card';
							$proceed = "NOK";
						}
					}else{
						$dest_running = $amount;
					}
				}

				if($proceed=="NOK"){
					$response['resultcode'] = $result;
					$response['result'] = $reply;

					$query = "UPDATE card.transaction SET result='$result', message='$reply', complete_ts=NOW(),amount='$amount' WHERE id=$trans_id";
					$this->pdo_db->query($query);
				}else{

					// --Debit sender --
					$cbal = $obal - $amount - $charge;

					$amount_format = number_format($amount, 0);
					$charge_format = number_format($charge, 0);
					$cbal_format = number_format($cbal, 0);

					$reply = "Successfully sent funds to $dest_name. Your Account has been debited TZS $amount_format on $ts.";
					
					if($charge>0){
						$reply .= " Transaction charge $charge_format.";
					}

					$reply .=" Updated balance TZS $cbal_format. Reference $reference";
					
					$reply_arr=array();
					$reply_arr['accountNo']=$account_no;
					$reply_arr['balance']=$cbal;
					$reply_arr['reference']=$reference;
					$reply_arr['amount']=$amount;
					$reply_arr['dated']=$ts;
					$reply_arr['description']=$reply;
					

					$query = "UPDATE card SET obal=balance, cbal=balance-$amount-$charge, balance=balance-$amount-$charge, reference='$reference', last_transaction=NOW(), dailytrans='$running' WHERE id=$id";
					$this->pdo_db->query($query);

					$query = "UPDATE transaction SET result='000', message='$reply', complete_ts=NOW(), amount='$amount', obal='$obal', cbal='$cbal', charge='$charge', utilitycode='$service', utilityref='$utilityref' WHERE id=$trans_id";
					$this->pdo_db->query($query);

					$query = "INSERT INTO ledger (fulltimestamp, vendor, card, transtype, transid, reference, amount, obal, cbal, charge, msisdn, channel, utilitycode, utilityref, name) VALUES (NOW(), '$vendor', '$masked_card', 'DEBIT', '$transid', '$reference', '$amount', '$obal', '$cbal', '$charge', '$msisdn', 'PALMPAY', '$service', '$utilityref', '$name')";
					$this->pdo_db->query($query);
					// no charges for TPAY
					//$query = "UPDATE card.account SET charge=charge+$charge WHERE id=$account_id"; /*update accountprofile? but there is no charges*/
					//$this->pdo_db->query($query);

					//update sender profile
					$query = "UPDATE accountprofile SET balance=balance-$amount-$charge, lastupdated=NOW()  WHERE accountNo='$account_no'";
					
					$this->pdo_db->query($query);
					 
					
					//---

					// --- Credit receiver ---
					$dest_cbal = $dest_obal + $amount;

					$amout_format = number_format($amount, 0);
					$dest_cbal_format = number_format($dest_cbal, 0);

					$dest_reply = "You have received funds from $name. Your account has been credited TZS $amount_format on $ts. Updated balance TZS $dest_cbal_format. Reference $reference";

					$query = "UPDATE card SET obal=balance, cbal=balance+$amount, balance=balance+$amount, last_transaction=NOW(), dailytrans='$dest_running' WHERE id=$dest_id";
					$this->pdo_db->query($query);

					$query = "INSERT INTO ledger (fulltimestamp, vendor, card, transtype, transid, reference, amount, obal, cbal, charge, msisdn, channel, utilitycode, utilityref, name) VALUES (NOW(), '$vendor', '$dest_masked_card', 'CREDIT', '$transid', '$reference', '$amount', '$dest_obal', '$dest_cbal', '0', '$dest_msisdn', 'USSD', '$service', '$utilityref', '$name')";
					$this->pdo_db->query($query);

					

					//update destination profile
					$dest_query = "UPDATE accountprofile SET balance=balance+$amount, lastupdated=NOW()  WHERE accountNo='$dest_account_no'";
					
					$this->pdo_db->query($dest_query);
 
					// send sms (if has app, send notification)
					// send_sms( $msisdn, $reply);
					// send_sms( $dest_msisdn, $dest_reply);

					/*$id = $this->createNotification($msisdn,$reply,"INFO");
					$this->sendNotification($msisdn,$reply, $id);
					$this->sendNotification($dest_msisdn,$dest_reply, $id);
					//--
					*/
					$dest_reply_arr=array();
					$dest_reply_arr['accountNo']=$dest_account_no;
					$dest_reply_arr['balance']=$dest_cbal;
					$dest_reply_arr['reference']=$reference;
					$dest_reply_arr['amount']=$amount;
					$dest_reply_arr['dated']=$ts;
					$dest_reply_arr['description']=$dest_reply;
				 
					$resp['sender']=$reply_arr;
					$resp['receiver']=$dest_reply_arr;

					$tmp["date"] = $ts;
					$tmp["biller"] = $service; //  company name
					$tmp["amount"] = $amount;
					$tmp["charge"] = $charge;
					$response['result']=$resp;
					$response['txn'] = $tmp;
					$response['resultcode'] = "000";
					 
					return $response;

				}


			} else {
				$response = $card_resp;
			}
		}
		else {
			$response = $chk_resp;
		}

		return $response;

	}

	function utilityPayment($transid,$service,$utilityref,$msisdn,$amount, $ref) {
		$debit  = "OK";
		$charge = "0";
		$vendor = "SELCOMCARD";

		$proceed = "OK";

		// check if transaction exists
		$chk_resp = $this->_checkTransaction($msisdn, $transid);
		$org_utilityref = $utilityref;

		if(($service == 'SELCOMPAY' OR $service == 'MASTERPASS') && strlen($utilityref)>20){
			$qrarray = parseQRString($utilityref);
			$pan = $qrarray['04'];
			$utilityref = maskPan($pan,  5, 4 );

			$query = "INSERT INTO smpos.mpqr_session (fulltimestamp, vendor, msisdn, transid, qrdata, masked_pan) VALUES (NOW(), '$vendor', '$msisdn','$transid', '$org_utilityref', '$utilityref')";
			$this->pdo_db->exec($query);
		}

		if ($chk_resp['resultcode']=="000") {
			//$reference = reference();
			$reference = $ref;

			$card_resp = $this->_get_card_info($msisdn);

			if($card_resp['resultcode']=="000"){
				$info = $card_resp['info'];

				$masked_card = $info['masked_card'];
				$name = $info['name'];
				$dealer = $info['dealer'];

				/*$settings = $this->_get_card_settings();

				$setting_minbplimit= $settings['setting_minbplimit'];
				$setting_maxbplimit= $settings['setting_maxbplimit'];
				$setting_failcount = $settings['setting_failcount'];
				*/

				// save the transaction
				$query = "INSERT INTO transaction (fulltimestamp, transid, reference, vendor, card, amount, initiate_ts, channel, utilitycode, name, msisdn, dealer, type, utilityref) VALUES (NOW(), '$transid', '$reference', '$vendor', '$masked_card', '0', NOW(), 'APP', '$service', '$name', '$msisdn', '$dealer', 'DEBIT', '$utilityref')";
				$this->pdo_db->exec($query);
				$trans_id = $this->pdo_db->lastInsertId();

				// load account settings
				$query = "SELECT balance, status, c2b, b2c, id, tariff, name FROM account WHERE utilitycode=?";

				if($service=="ATOP" || $service=="VTOP" || $service=="HTOP" || $service=="STOP" || $service=="HTOP" || $service=="TTCLPPS" || $service=="TTOP" ){
					$service = "TOP";
				}

				$sth = $this->pdo_db->prepare($query);
				$sth->execute(array($service));

				$row = $sth->fetch();

				$account_bal = $row['balance'];
				$account_status = $row['status'];
				$account_c2b = $row['c2b'];
				$account_id = $row['id'];
				$account_tariff = $row['tariff'];
				$account_name = $row['name'];

				// get charges
				$charge = 0;
				$query = "SELECT rate, type FROM card.tariff WHERE ($amount BETWEEN lower AND upper) AND code='$account_tariff'";
				$row = $this->pdo_db->query($query)->fetch();

				if ($row) {
					$rate = $row['rate'];
					$type = $row['type'];

					if ($type=="PERCENT") {
						$charge = floor($rate*$amount);
					} else {
						$charge = $rate;
					}
				}

				$obal = $info['obal'];
				$failcount = $info['failcount'];
				$active = $info['active'];
				$status=$info['status'];
				$stolen=$info['stolen'];
				$ts = $info['ts'];
				$id = $info['id'];

				if (($account_status!=="1") OR ($account_c2b!=="1")) {
					$result = "045";
					$reply = "Account $account_name is closed";
					$proceed="NOK";
				}elseif ($failcount>=$setting_failcount) {
					$result = "075";
					$reply = "PIN tries exceeded";
					$proceed="NOK";
				}elseif($active==0){
					$result = "076";
					$reply = "Card not activated";
					$proceed="NOK";
				}elseif($failcount>=$setting_failcount){
					$result = "075";
					$reply = "PIN tries exceeded";
					$proceed="NOK";
				}elseif($status!=1){
					$result = "045";
					$reply = "Card blocked";
					$proceed="NOK";
				}elseif($stolen=="1"){
					$result = "057";
					$reply = "Lost or stolen card";
					$proceed="NOK";
				}elseif(($amount < $setting_minbplimit) OR ($amount > $setting_maxbplimit)) {
					$reply = "Invalid amount";
					$result = "013";
					$proceed="NOK";
				}elseif(($amount + $charge) > $obal) {
					$result = '051';
					$reply = "Insufficient funds";
					$proceed="NOK";
				}


				if($proceed=="NOK"){
					$response['resultcode'] = $result;
					$response['result'] = $reply;

					$query = "UPDATE card.transaction SET result='$result', message='$reply', complete_ts=NOW() WHERE id=$trans_id";
					$this->pdo_db->query($query);
				}else{
					$billpaydata = $this->_billPay($transid,$reference, $service, $org_utilityref, $msisdn, $amount);

					// $billpaydata = explode("|", $billpayresponse);

					$billpayresult = $billpaydata['resultcode'];
					$billpayresponse = $billpaydata['message'];

					if ($billpayresult=="000") {
						//SUCCESSFUL
					} elseif (($billpayresult=="111") OR ($billpayresult=="")) {
						//AMBIGUOUS
					} else {
						$debit = "NOK";
					}

					if ($debit=="OK") {
						$response['resultcode'] = '000';
						$cbal = $obal - $amount - $charge;

						$amount_format = number_format($amount, 0);
						$charge_format = number_format($charge, 0);
						$cbal_format = number_format($cbal, 0);
						$reply_arr= array();
						

						$reply = "Your PALMPAY account has been debited TZS $amount_format for $account_name on $ts.";
						

						if ($charge>0) {
							$reply .= " Transaction charge TZS $charge_format.";
							$reply_arr['charges']=$charge;
						}

						$reply .= " Updated balance TZS $cbal_format. Reference $reference";
						$reply_arr['balance']=$cbal_format;
						$reply_arr['reference']=$reference;
						$reply_arr['description']=$reply;
						 

						$query = "UPDATE card.card SET obal=balance, cbal=balance-$amount-$charge, balance=balance-$amount-$charge, reference='$reference', last_transaction=NOW() WHERE id=$id";
						$this->pdo_db->query($query);

						$query = "UPDATE card.transaction SET result='000', message='$reply', complete_ts=NOW(), amount='$amount', obal='$obal', cbal='$cbal', charge='$charge', utilitycode='$service', utilityref='$utilityref' WHERE id=$trans_id";
						$this->pdo_db->query($query);

						$query = "INSERT INTO card.ledger (fulltimestamp, vendor, card, transtype, transid, reference, amount, obal, cbal, charge, msisdn, channel, utilitycode, utilityref, name) VALUES (NOW(), '$vendor', '$masked_card', 'DEBIT', '$transid', '$reference', '$amount', '$obal', '$cbal', '$charge', '$msisdn', 'USSD', '$service', '$utilityref', '$name')";
						$this->pdo_db->query($query);

						$query = "UPDATE card.account SET obal=balance, cbal=balance+$amount, balance=balance+$amount, charge=charge+$charge WHERE id=$account_id";
						$this->pdo_db->query($query);

						//create new transaction object
						$tmp["date"] = $ts;
						$tmp["biller"] = $service; //  company name
						$tmp["amount"] = $amount;
						$tmp["charge"] = $charge;

						$response['result'] = $reply."\n---\n".$billpayresponse;
						$response['txn'] = $tmp;
						$id = $this->createNotification($msisdn,$reply,"INFO");
						$id2= $this->createNotification($msisdn,$billpayresponse,"INFO");
						$this->sendNotification($msisdn,$reply, $id);
						$this->sendNotification($msisdn,$billpayresponse,$id2);

						// send_sms($msisdn, $reply);

					} else {
						$amount_format = number_format($amount, 0);
						$response['resultcode'] = $billpayresult;

						$reply = "Your TPAY transaction for TZS $amount_format to $account_name was unsuccessful. Reference $reference " . $billpayresponse;

						$query = "UPDATE card.transaction SET result='$billpayresult', message='$reply', complete_ts=NOW(), amount='$amount', obal='$obal', cbal='$obal', charge='0', utilitycode='$service', utilityref='$utilityref' WHERE id=$trans_id";
						$this->pdo_db->query($query);
						$response['result'] = $reply;
					}
					// send GCM to customer
				}
			} else {
				$response = $card_resp;
			}
		} else {
			$response = $chk_resp;
		}

		return $response;
	}

	private function _billPay($transid,$reference, $utilitycode,$utilityref,$msisdn,$amount){
		// require_once dirname(__FILE__) . './XMLUtils.php';
		// require_once dirname(__FILE__) . './utils/utils.php';
		// include(dirname(__FILE__)."/utils/utils.php"));

		$vendor = "SELCOMCARD";
		$pin = "0883";

		if($utilitycode=="BNMQR"){
			if(strlen($utilityref) == 10) $utilityref= '255'.substr($utilityref,1);
			$request["utilityref"] = $utilityref; //"NA";
			$host = "192.168.22.63";
			$uri = "/telepin/api/selcom.pos.server.tigopesa.mysqli.php";
		}else if($utilitycode=='SELCOMPAY' || $utilitycode=='MASTERPASS'){
			$request["utilityref"] = $utilityref;
			$host = "127.0.0.1";
			$uri = "/wwwssl/api/selcom.pos.server.mpqr.test.php";

		}else{
			$request["utilityref"] = $utilityref;
			$host = "127.0.0.1";
			$uri = "/wwwssl/api/selcom.pos.server.php";
		}

		$request["vendor"] = $vendor;
		$request["pin"] = $pin;
		$request["transid"] = $transid;
		$request["utilitycode"] = $utilitycode;
		// $request["utilityref"] = $utilityref;
		$request["msisdn"] = $msisdn;
		$request["amount"] = $amount;
		$request["reference"] = $reference;

		$result = xu_rpc_http_concise(
			array(
				"method" => "SELCOM.utilityPayment",
				"args" => array( $request ),
				"host" => $host,
				"uri" => $uri,
				"port" => 8008 ) );

		$member_count = substr_count($result, "<member>");

		/*
		$filename2 = "../logs/debug/smpos_response_" . $reference . ".txt";
		$fh = fopen($filename2, 'w') or die("can't open file");
		fwrite($fh, $url . "\r\n---\r\n" . $result);
		fclose($fh);
		*/


		$xml = simplexml_load_string($result) or die("feed not loading");

		for($i=0; $i<$member_count; $i++) {
			$element_name = $xml->params->param->value->struct->member[$i]->name;
			$element_data = $xml->params->param->value->struct->member[$i]->value->string;

			if ($element_name=="result") {
				$result = zeropad($element_data, 3);
			}

			if ($element_name=="message") {
				$message = $element_data;
			}
			if ($element_name=="reference") {
				$reference = $element_data;
			}
			if ($element_name=="resultcode") {
				$resultcode = $element_data;
			}
		}

		$response["transid"] = substr($transid, 0);
		$response["reference"] = substr($reference, 0);
		$response["result"] = substr($result, 0);
		$response["resultcode"] = substr($resultcode, 0);
		$response["message"] = substr($message, 0);

		return $response;
	}
	function _check_account($account_no){

		$query = "SELECT id, balance, status, accountNo, active, status, /*c2b, b2c, id, tariff,*/ CONCAT(firstname, ' ', lastname) as name FROM accountprofile WHERE accountNo=$account_no";
		
		$row = $this->pdo_db->query($query)->fetch();
	
		$account_bal = $row['balance'];
		$account_status = $row['status'];
		$account_id = $row['id'];
		$account_no = $row['accountNo'];
		$account_name = $row['name'];
		$active = $row['active'];
		$status = $row['status'];
		$result = 'OK';
		$err = array();
		$charge=0;
		
		

		//if (($account_status!="1") OR ($account_c2b!="1")) {
			if (($account_status != 1) ) {
				
				$err["result"] = "045";
				$err["reply"] = "Account $account_name is closed";
				$err["proceed"]="NOK";
				
			}/*elseif ($failcount>=$setting_failcount) {
				$result = "075";
				$reply = "PIN tries exceeded";
				$proceed="NOK";
			}*/elseif($active!=0){
					
				$err["result"] = "076";
				$err["reply"] = "Card not activated";
				$err["proceed"]="NOK";
			}elseif($status!=1){
								
				$err["result"] = "045";
				$err["reply"] = "Card blocked";
				$err["proceed"]="NOK";
			}/*elseif($stolen=="1"){
				$result = "057";
				$reply = "Lost or stolen card";
				$proceed="NOK";
			}elseif(($amount < $setting_minp2plimit) OR ($amount > $setting_maxp2plimit)) {
				$reply = "Invalid amount";
				$result = "013";
				$proceed="NOK";
			}*/
			
			
			return $err;
	}
	//reserveFunds
	function reserveAccount($transid,$reference,$msisdn,$amount){
		 
		$proceed = "OK";
		$vendor = "TRANSSNET";
		$service = "reserveAccount";
		$channel = "TPAY";
		//$reference = $reference;

		// check if transaction exists
		$chk_resp = $this->_checkTransaction($msisdn,$transid);
		
		if($chk_resp['resultcode'] !="000"){
			return $chk_resp;
		}

		$card_resp = $this->_get_card_info($msisdn);		
			
		if($card_resp['resultcode'] !="000"){
			return $card_resp;
		}

		$card_info = $card_resp['info'];
	
		$masked_card = $card_info['masked_card'];
		$name = $card_info['name'];
		$dealer = 'Transsnet';//$info['dealer'];//check if Transsnet
		//$cardnum = $info['cardnum'];
		$charge=0;
		$account_no=$card_info['accountNo'];
				 
		// save the transaction
		$query = "INSERT INTO transaction (fulltimestamp, transid, reference, vendor, card, amount, initiate_ts, channel, utilitycode, name, msisdn, dealer, type, utilityref) VALUES (NOW(), '$transid', '$reference', '$vendor', '$masked_card', '0', NOW(), '$channel', '$service', '$name', '$msisdn', '$dealer', 'DEBIT', '$msisdn')";
		$this->pdo_db->exec($query);
		$trans_id = $this->pdo_db->lastInsertId();
				

		// check account settings
		//$query = "SELECT balance, status, c2b, b2c, id, tariff, name FROM card.account WHERE utilitycode='P2P'";
		
		$proceed = $this->_check_account($account_no);
		

		$obal = $card_info['obal'];
		
		//$failcount = $info['failcount'];
		$active = $card_info['active'];
		$status=$card_info['status'];
		//$stolen=$info['stolen'];
		$ts = $card_info['ts'];
		$id = $card_info['id'];
		
		$now = $card_info['now'];
		$lasttrans = $card_info['lasttrans'];
		$dailytrans = $card_info['dailytrans'];
		$dailylimit = $card_info['dailylimit'];
		
		if(($amount + $charge) > $obal) {
									
			$proceed["result"] = '051';
			$proceed["reply"] =  "Insufficient funds";
			$proceed["proceed"]= "NOK";
		}
		$running = "0";

		if($now==$lasttrans){
			$running = $dailytrans + $amount + $charge;

			if($running>$dailylimit){
				$proceed['result'] = '065';
				$proceed['reply'] = 'Transaction exceeds daily transaction limit';
				$proceed['proceed']="NOK";
			}
		}else{
			$running = $amount + $charge;
		}		
	
		
		if($proceed=="NOK"){
			
			$result = $proceed['reply'];
			$resultcode = $proceed['result'];
			$resp['resultcode'] = $resultcode;
			$resp['result'] = $result;

			$query = "UPDATE transaction SET result='$resultcode', message='$result', complete_ts=NOW(), amount='$amount' WHERE id=$trans_id";
			
			$this->pdo_db->query($query);
			
			return $resp;
		}
				
		
		// --Debit suspense --
		$cbal = $obal - $amount - $charge;

		$amount_format = number_format($amount, 0);
		$charge_format = number_format($charge, 0);
		$cbal_format = number_format($cbal, 0);

		try{
			$reply = "Your Account has been reserved of amount TZS $amount_format on $ts.";
			
			$query = "UPDATE card SET obal=balance, cbal=balance-$amount-$charge, balance=balance-$amount-$charge, suspense=suspense+$amount, reference='$reference', last_transaction=NOW(), dailytrans='$running' WHERE accountNo='$account_no'";
			$stmt = $this->pdo_db->prepare($query);
			$stmt->execute();

			$query = "select balance, suspense, reference from card where accountNo='$account_no'";
			$stmt = $this->pdo_db->prepare($query);
			$stmt->execute();
			$result = $stmt->fetchAll();
			
			//update account profile
			$query = "UPDATE accountprofile SET balance=balance-$amount-$charge, lastupdated=NOW()  WHERE accountNo='$account_no'";
			$stmt = $this->pdo_db->prepare($query);
			$stmt->execute();

			$query = "UPDATE transaction SET result='000', message='$reply', complete_ts=NOW(), amount='$amount', obal='$obal', cbal='$cbal', charge='$charge', utilitycode='$service', utilityref='$msisdn' WHERE id=$trans_id";
			$stmt = $this->pdo_db->prepare($query);
			$stmt->execute();
			$response['resultcode'] ='000';
				$response['result'] = $reply;
			
			return $response;

		}catch (Exception $e) {
			$response['resultcode'] ='002';
				$response['result'] = $e;
			return $response;
		}
			

	}

	//unReserveFunds
	function unReserveAccount($transid,$reference,$transref,$msisdn,$amount){
		 
		$proceed = "OK";
		$vendor = "TRANSSNET";
		$service = "unReserveAccount";
		$channel = "TPAY";
		//$reference = $reference;

		// check if transaction exists
		$chk_resp = $this->_checkTransaction($msisdn,$transid);
		
		if($chk_resp['resultcode'] !="000"){
			return $chk_resp;
		}

		$card_resp = $this->_get_card_info($msisdn);		
			
		if($card_resp['resultcode'] !="000"){
			return $card_resp;
		}

		$card_info = $card_resp['info'];
	

		$masked_card = $card_info['masked_card'];
		$name = $card_info['name'];
		$dealer = 'Transsnet';//$info['dealer'];//check if Transsnet
		//$cardnum = $info['cardnum'];
		$charge=0;
		$account_no=$card_info['accountNo'];
				 
		// save the transaction
		//$query = "INSERT INTO transaction (fulltimestamp, transid, reference, vendor, card, amount, initiate_ts, channel, utilitycode, name, msisdn, dealer, type, utilityref) VALUES (NOW(), '$transid', '$reference', '$vendor', '$masked_card', '0', NOW(), '$channel', '$service', '$name', '$msisdn', '$dealer', 'DEBIT', '$msisdn' where reference = '$transref')";
		//$this->pdo_db->exec($query);
		//$trans_id = $this->pdo_db->lastInsertId();
				

		// check account settings
		//$query = "SELECT balance, status, c2b, b2c, id, tariff, name FROM card.account WHERE utilitycode='P2P'";
		
		$proceed = $this->_check_account($account_no);
		//die(print_r($proceed));
		$obal = $card_info['obal'];
		
		//$failcount = $info['failcount'];
		$active = $card_info['active'];
		$status=$card_info['status'];
		//$stolen=$info['stolen'];
		$ts = $card_info['ts'];
		$id = $card_info['id'];
		
		$now = $card_info['now'];
		$lasttrans = $card_info['lasttrans'];
		$dailytrans = $card_info['dailytrans'];
		$dailylimit = $card_info['dailylimit'];
		
		if(($amount + $charge) > $obal) {
									
			$proceed["result"] = '051';
			$proceed["reply"] =  "Insufficient funds";
			$proceed["proceed"]= "NOK";
		}
		$running = "0";

		if($now==$lasttrans){
			$running = $dailytrans + $amount + $charge;

			if($running>$dailylimit){
				$proceed['result'] = '065';
				$proceed['reply'] = 'Transaction exceeds daily transaction limit';
				$proceed['proceed']="NOK";
			}
		}else{
			$running = $amount + $charge;
		}		
	
		
		if($proceed=="NOK"){
			
			$result = $proceed['reply'];
			$resultcode = $proceed['result'];
			$resp['resultcode'] = $resultcode;
			$resp['result'] = $result;

			$query = "UPDATE transaction SET result='$resultcode', message='$result', complete_ts=NOW(), amount='$amount' WHERE reference=$utilityref";
			
			$this->pdo_db->query($query);
			$result = $proceed['reply'];
			$resultcode = $proceed['result'];
			$resp['resultcode'] = $resultcode;
			$resp['result'] = $result;
			return $resp;
		}
				
		
		// --Debit suspense --
		$cbal = $obal + $amount - $charge;

		$amount_format = number_format($amount, 0);
		$charge_format = number_format($charge, 0);
		$cbal_format = number_format($cbal, 0);
		$response = array();
		try{
			$reply = "Your Account has been reversed of amount TZS $amount_format on $ts. New balance is now ".$cbal;
			
			$query = "UPDATE card SET obal=balance, cbal=balance+$amount-$charge, balance=balance+$amount-$charge, suspense=suspense-$amount, reference='$reference', last_transaction=NOW(), dailytrans='$running' WHERE accountNo='$account_no'";
			$stmt = $this->pdo_db->prepare($query);
			$result = $stmt->execute();
			//die(print_r($result));
			$query = "select balance, suspense, reference from card where accountNo='$account_no'";
			$stmt = $this->pdo_db->prepare($query);
			$stmt->execute();
			$result = $stmt->fetchAll();
			
			//update account profile
			$query = "UPDATE accountprofile SET balance=balance+$amount-$charge, lastupdated=NOW()  WHERE accountNo='$account_no'";
			$stmt = $this->pdo_db->prepare($query);
			$stmt->execute();

			$query = "UPDATE transaction SET result='000', message='$reply', complete_ts=NOW(), amount='$amount', obal='$obal', cbal='$cbal', charge='$charge', utilitycode='$service', utilityref='$msisdn' WHERE reference='$transref'";
			$stmt = $this->pdo_db->prepare($query);
			$stmt->execute();
			$response['resultcode']='000';
			$response['reply']=$reply;
			return $response;
		}catch (Exception $e) {
			$response['resultcode']='002';
			$response['reply']= "unReverseAccount ".$e;
			return $response;
		}
			

	}
}
?>