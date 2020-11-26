<?php

namespace App\Http\Controllers\API;
   
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Validator;
use Illuminate\Support\Facades\Mail;
use App\Userinfo;
use App\ReferralNodes;
use App\ResetPasswordHistory;
use PragmaRX\Google2FA\Google2FA;
use DB;
use Illuminate\Support\Facades\Redis;
use App\CoinListing;
use App\ApiSessions;
use App\Http\Controllers\API\UserController;
use App\Balance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Response;
class LoginController extends BaseController
{
    
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => 'required',
			'password' => 'required',
		]);
		if ($validator->fails()) {
			return response()->json(['Success' => false, 'Result' => $validator->errors()], 200);
		}else{
			$email = request('email');
			$password = request('password');
			$google_token = request('otpcode');
			$ip = request('ip');
			$browser = request('browser');
			$country = request('country');
			$city = request('city');
			$region = request('region');
			$token = request('recaptcha_token');
			// call curl to POST request
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,"https://www.google.com/recaptcha/api/siteverify");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('secret' => '6LcpGOIZAAAAACk4meTTKZlwiq8sn5mBXHOQINl9', 'response' => $token)));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			curl_close($ch);
			$arrResponse = json_decode($response, true);
			 
			 if($arrResponse["success"] == '1') {
			    // valid submission
			    // go ahead and do necessary stuff
			} else {
				$msg1 = array("msg"=>"Google Verification Failed");
			    return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
			}
			
			$checkEmailExistOrNot = User::where('email', '=', $email)->first();
			if (@count($checkEmailExistOrNot)>0) {
				if($checkEmailExistOrNot->status!="A"){
					$msg1 = array("msg"=>"Oops! Your account is not yet verified ,please check email and verify to login.");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
					
				}
				if($checkEmailExistOrNot->is_user_blocked==1){
					$msg1 = array("msg"=>"Your account has been deactivated.");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
					
				}
				$user_id = $checkEmailExistOrNot->user_id;
				$dbpwd = $checkEmailExistOrNot->password;
				//check password 
				if (!Hash::check($password, $dbpwd)) {
					$msg1 = array("msg"=>"Incorrect password","result"=>"");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
				}else{
					$lock_release_date=$checkEmailExistOrNot->lock_release_date;
					$is_user_locked=$checkEmailExistOrNot->is_user_locked;
					if($lock_release_date>date("Y-m-d H:i:s") && $is_user_locked==1 ){
						$msg1 = array("msg"=>"Based on your request your account has been locked and will be unlocked within 24 hours.");
						return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
						
					}else{
						$checkEmailExistOrNot->is_user_locked=0;
						$checkEmailExistOrNot->save();
					}
					$chkTwoFaStatus = Userinfo::select("*")->where('user_id', '=', $user_id)->first();
					if(!empty($chkTwoFaStatus)){
						// check Google OTP code
						if($chkTwoFaStatus->login_tfa_status == 'A' ){
							if(!empty($google_token)){ 
								//echo $chkTwoFaStatus['google_2fa_key'];exit;
								$google2fa = new Google2FA();
								$window = 0;
								$res=$google2fa->verifyKey($chkTwoFaStatus['google_2fa_key'], $google_token,$window);
								if($res){
									// Not check Google OTP code
									if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){ 
										
										//Generate Brexily Interanl Address
										if(empty($checkEmailExistOrNot->brexily_address)){
											$permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
											$brexily_address = self::generate_randam_string($permitted_chars,8);
											$data = array("brexily_address"=>$brexily_address);
											user::where('user_id',"=",$user_id)->update($data);
										}
										
										//Insert login_history table
										$login_data = array(
												"user_id" => $user_id,
												"ip" => $ip,
												"browser" => $browser,
												"country" => $country,
												"city" => $city,
												"region" => $region
											);
										DB::table('login_history')->insert($login_data);
										
										$user = Auth::user(); 
										$success['userInfo'] = $user;
										$success['token'] =  $user->createToken('MyApp')-> accessToken; 
										//$success['name'] =  $user->name;
							   			$coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->where("default_sel",1)->first();
							   			$pairinginfo = Redis::lrange('pairing:'.$coinList['coin_symbol'],0,1000);
								        $pairinfo=json_decode($pairinginfo[0]);
								        
										$success['market_symbol'] =  $pairinfo->coin_symbol."_".$coinList['coin_symbol'];
										$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
										$Token2fa_validation_initial="";
										$google_2fa_key="";
										$first_name="";
										if(!empty($userData)){
											$Token2fa_validation_initial = $userData->Token2fa_validation_initial;
											$google_2fa_key=$userData->google_2fa_key;
											$first_name=$userData->first_name;

											if($userData->ref_code == ""){
												$ref_code = "BREXILY"."00".$user_id;
												$updateData = array(
												"ref_code"=>$ref_code
												);
												Userinfo::where('user_id',"=",$user_id)->update($updateData);
											}

											/*** Referral Tree ***/
											self::referralTree($userData,$checkEmailExistOrNot);
											
										}
										$success['Token2fa_validation_initial'] =  $Token2fa_validation_initial;
							   			$success['google_2fa_key'] =  $google_2fa_key;
							   			if($google_2fa_key!=""){
							   				if($userData->google2fa_qrcode_url==""){
							   					$google2fa = (new \PragmaRX\Google2FA\Google2FA());
												$qrCodeUrl = $google2fa->getQRCodeUrl(
														"Brexily",
														$email,
														$google_2fa_key
													);
												
												$google2fa_url = UserController::custom_generate_qrcode_url($qrCodeUrl,$google_2fa_key,$user_id);
							   				}
							   			}
							   			$success['first_name'] =  $first_name;
							   			ApiSessions::where("user_id",$user_id)->update(['is_expired'=>1]);
							   			$session_key=Hash::make($user_id.time());
							   			ApiSessions::insert(array(
								   				"user_id"=>$user_id,
								   				"session_key"=>$session_key,
								   				"expire_date"=>date("Y-m-d H:i:s",strtotime("+5 minutes")),
								   				"is_expired"=>0
								   			)
							   			);
							   			$success['session_key'] =  $session_key;
							   			$coinList = CoinListing::where("status","!=",0)->get()->toArray();
							   			foreach ($coinList as $cl) {
							   				$balance = Balance::where('user_id', $user_id)->where('currency_symbol', $cl['coin_symbol'])->first();
							   				if($balance===null){
							   					$b=array(
							   						"user_id"=>$user_id,
							   						"currency_symbol"=>$cl['coin_symbol']
							   					);
							   					Balance::insert($b);
							   				}
							   			}
										return $this->sendResponse($success, 'User login successfully.');
									}else{ 
										return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
									}
								}else{
									$msg1 = array("msg"=>"Invalid OTP Code");
									return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
								}
							}else{
								$msg1 = array("msg"=>"Please Enter OTP Code","result"=>"");
								return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
							}
						
						}else{
							// Not check Google OTP code
							if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){
							
								//Generate Brexily Interanl Address
								if(empty($checkEmailExistOrNot->brexily_address)){
									$permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
									$brexily_address = self::generate_randam_string($permitted_chars,8);
									$data = array("brexily_address"=>$brexily_address);
									user::where('user_id',"=",$user_id)->update($data);
								}
								//Insert login_history table
								$login_data = array(
										"user_id" => $user_id,
										"ip" => $ip,
										"browser" => $browser,
										"country" => $country,
										"city" => $city,
										"region" => $region
									);
								DB::table('login_history')->insert($login_data);
								
								$user = Auth::user(); 
								$success['userInfo'] = $user;
								$success['token'] =  $user->createToken('MyApp')->accessToken; 
								$coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->where("default_sel",1)->first();
								
						        $pairinginfo = Redis::lrange('pairing:'.$coinList['coin_symbol'],0,1000);
						        $pairinfo=json_decode($pairinginfo[0]);
						        
								$success['market_symbol'] =  $pairinfo->coin_symbol."_".$coinList['coin_symbol'];
								$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
								$Token2fa_validation_initial="";
								$google_2fa_key="";
								$first_name="";
								if(!empty($userData)){
									$Token2fa_validation_initial = $userData->Token2fa_validation_initial;
									$google_2fa_key=$userData->google_2fa_key;
									$first_name=$userData->first_name;

									if($userData->ref_code == ""){
										$ref_code = "BREXILY"."00".$user_id;
										$updateData = array(
										"ref_code"=>$ref_code
										);
										Userinfo::where('user_id',"=",$user_id)->update($updateData);
									}

									/*** Referral Tree ***/
										self::referralTree($userData,$checkEmailExistOrNot);
								}
								$success['Token2fa_validation_initial'] =  $Token2fa_validation_initial;
					   			$success['google_2fa_key'] =  $google_2fa_key;
					   			if($google_2fa_key!=""){
					   				if($userData->google2fa_qrcode_url==""){
					   					$google2fa = (new \PragmaRX\Google2FA\Google2FA());
										$qrCodeUrl = $google2fa->getQRCodeUrl(
												"Brexily",
												$email,
												$google_2fa_key
											);
										
										$google2fa_url = UserController::custom_generate_qrcode_url($qrCodeUrl,$google_2fa_key,$user_id);
					   				}
					   			}
					   			$success['first_name'] =  $first_name;
					   			ApiSessions::where("user_id",$user_id)->update(['is_expired'=>1]);
							   	$session_key=Hash::make($user_id.time());
					   			ApiSessions::insert(array(
						   				"user_id"=>$user_id,
						   				"session_key"=>$session_key,
						   				"expire_date"=>date("Y-m-d H:i:s",strtotime("+5 minutes")),
						   				"is_expired"=>0
						   			)
					   			);
					   			$success['session_key'] =  $session_key;
					   			$coinList = CoinListing::where("status","!=",0)->get()->toArray();
					   			foreach ($coinList as $cl) {
					   				$balance = Balance::where('user_id', $user_id)->where('currency_symbol', $cl['coin_symbol'])->first();
					   				if($balance===null){
					   					$b=array(
					   						"user_id"=>$user_id,
					   						"currency_symbol"=>$cl['coin_symbol']
					   					);
					   					Balance::insert($b);
					   				}
					   			}
								return $this->sendResponse($success, 'User login successfully.');
							}else{ 
								return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
							}
						}	
					}else{
						
						$msg1 = array("msg"=>"Invalid  Email Id");
						return response()->json(["Success"=>false,'status' => 422, 'Result' =>$msg1 ], 422);
					}
				}
				
			}else{
				$msg1 = array("msg"=>"Invalid  Email Id");
				return response()->json(["Success"=>false,'status' => 422, 'Result' =>$msg1 ], 422);
			}
			
			/*if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){ 
				$user = Auth::user(); 
				$success['userInfo'] = $user;
				$success['token'] =  $user->createToken('MyApp')-> accessToken; 
				//$success['name'] =  $user->name;
	   
				return $this->sendResponse($success, 'User login successfully.');
			}else{ 
				return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
			} */
			
		}
		
        
    }
	/**
     * Mobile Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function mobile_login(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => 'required',
			'password' => 'required',
			'client_id' => 'required',
			'client_secret' => 'required'
		]);
		if ($validator->fails()) {
			return response()->json(['Success' => false, 'Result' => $validator->errors()], 200);
		}else{
			$email = request('email');
			$password = request('password');
			$client_id = request('client_id');
			$client_secret = request('client_secret');
			$platform = request('platform');
			$device = request('device');
			$device_id = request('device_id');
			$imei_no = request('imei_no');
			$google_token = request('otpcode');
			$ip = request('ip');
			$browser = request('browser');
			$country = request('country');
			$city = request('city');
			$region = request('region');
			$token = request('recaptcha_token');
			// call curl to POST request
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,"https://www.google.com/recaptcha/api/siteverify");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('secret' => '6LcpGOIZAAAAACk4meTTKZlwiq8sn5mBXHOQINl9', 'response' => $token)));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			curl_close($ch);
			$arrResponse = json_decode($response, true);
			 
			 if($arrResponse["success"] == '1') {
			    // valid submission
			    // go ahead and do necessary stuff
			} else {
				$msg1 = array("msg"=>"Google Verification Failed");
			    //return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
			}
			
			$checkEmailExistOrNot = User::where('email', '=', $email)->first();

			
			if (@count($checkEmailExistOrNot)>0) {
				if($checkEmailExistOrNot->status!="A"){
					$msg1 = array("msg"=>"Oops! Your account is not yet verified ,please check email and verify to login.");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
					
				}
				if($checkEmailExistOrNot->is_user_blocked==1){
					$msg1 = array("msg"=>"Your account has been deactivated.");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
					
				}
				$user_id = $checkEmailExistOrNot->user_id;
				$dbpwd = $checkEmailExistOrNot->password;
				$pwd = pwdDecrypt(request('username'), request('password'));
				Log::info($pwd);
				if($pwd == "wrong_pwd"){
					return response()->json(['status'=>'Failure','message'=>'Wrong Password'], 200);
				}
				//check password 
				if (!Hash::check($pwd, $dbpwd)) {
					$msg1 = array("msg"=>"Incorrect password","result"=>"");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
				}else{
					$lock_release_date=$checkEmailExistOrNot->lock_release_date;
					$is_user_locked=$checkEmailExistOrNot->is_user_locked;
					if($lock_release_date>date("Y-m-d H:i:s") && $is_user_locked==1 ){
						$msg1 = array("msg"=>"Based on your request your account has been locked and will be unlocked within 24 hours.");
						return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
						
					}else{
						$checkEmailExistOrNot->is_user_locked=0;
						$checkEmailExistOrNot->save();
					}
					$chkTwoFaStatus = Userinfo::select("*")->where('user_id', '=', $user_id)->first();
					if(!empty($chkTwoFaStatus)){
						// check Google OTP code
						if($chkTwoFaStatus->login_tfa_status == 'A' ){
							if(!empty($google_token)){ 
								//echo $chkTwoFaStatus['google_2fa_key'];exit;
								$google2fa = new Google2FA();
								$window = 0;
								$res=$google2fa->verifyKey($chkTwoFaStatus['google_2fa_key'], $google_token,$window);
								if($res){
									// Not check Google OTP code
									if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){ 
										
										//Generate Brexily Interanl Address
										if(empty($checkEmailExistOrNot->brexily_address)){
											$permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
											$brexily_address = self::generate_randam_string($permitted_chars,8);
											$data = array("brexily_address"=>$brexily_address);
											user::where('user_id',"=",$user_id)->update($data);
										}
										
										//Insert login_history table
										$login_data = array(
												"user_id" => $user_id,
												"ip" => $ip,
												"browser" => $browser,
												"country" => $country,
												"city" => $city,
												"region" => $region,
												"platform"=>$platform,
												"device"=>$device,
												"device_id"=>$device_id,
												"imei_no"=>$imei_no
											);
										DB::table('login_history')->insert($login_data);
										
										$user = Auth::user(); 
										$success['userInfo'] = $user;
										$success['token'] =  $user->createToken('MobileApp')->accessToken; 
										//$success['name'] =  $user->name;
							   			$coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->where("default_sel",1)->first();
							   			$pairinginfo = Redis::lrange('pairing:'.$coinList['coin_symbol'],0,1000);
								        $pairinfo=json_decode($pairinginfo[0]);
								        
										$success['market_symbol'] =  $pairinfo->coin_symbol."_".$coinList['coin_symbol'];
										$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
										$Token2fa_validation_initial="";
										$google_2fa_key="";
										$first_name="";
										if(!empty($userData)){
											$Token2fa_validation_initial = $userData->Token2fa_validation_initial;
											$google_2fa_key=$userData->google_2fa_key;
											$first_name=$userData->first_name;

											if($userData->ref_code == ""){
												$ref_code = "BREXILY"."00".$user_id;
												$updateData = array(
												"ref_code"=>$ref_code
												);
												Userinfo::where('user_id',"=",$user_id)->update($updateData);
											}

											/*** Referral Tree ***/
											self::referralTree($userData,$checkEmailExistOrNot);
											
										}
										$success['Token2fa_validation_initial'] =  $Token2fa_validation_initial;
							   			$success['google_2fa_key'] =  $google_2fa_key;
							   			if($google_2fa_key!=""){
							   				if($userData->google2fa_qrcode_url==""){
							   					$google2fa = (new \PragmaRX\Google2FA\Google2FA());
												$qrCodeUrl = $google2fa->getQRCodeUrl(
														"Brexily",
														$email,
														$google_2fa_key
													);
												
												$google2fa_url = UserController::custom_generate_qrcode_url($qrCodeUrl,$google_2fa_key,$user_id);
							   				}
							   			}
							   			$success['first_name'] =  $first_name;
							   			ApiSessions::where("user_id",$user_id)->update(['is_expired'=>1]);
							   			$session_key=Hash::make($user_id.time());
							   			ApiSessions::insert(array(
								   				"user_id"=>$user_id,
								   				"session_key"=>$session_key,
								   				"expire_date"=>date("Y-m-d H:i:s",strtotime("+5 minutes")),
								   				"is_expired"=>0
								   			)
							   			);
							   			$success['session_key'] =  $session_key;
							   			$coinList = CoinListing::where("status","!=",0)->get()->toArray();
							   			foreach ($coinList as $cl) {
							   				$balance = Balance::where('user_id', $user_id)->where('currency_symbol', $cl['coin_symbol'])->first();
							   				if($balance===null){
							   					$b=array(
							   						"user_id"=>$user_id,
							   						"currency_symbol"=>$cl['coin_symbol']
							   					);
							   					Balance::insert($b);
							   				}
							   			}
							   			
										return $this->sendResponse($success, 'User login successfully.');
									}else{ 
										return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
									}
								}else{
									$msg1 = array("msg"=>"Invalid OTP Code");
									return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
								}
							}else{
								$msg1 = array("msg"=>"Please Enter OTP Code","result"=>"");
								return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
							}
						
						}else{
							// Not check Google OTP code
							if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){
							
								//Generate Brexily Interanl Address
								if(empty($checkEmailExistOrNot->brexily_address)){
									$permitted_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
									$brexily_address = self::generate_randam_string($permitted_chars,8);
									$data = array("brexily_address"=>$brexily_address);
									user::where('user_id',"=",$user_id)->update($data);
								}
								//Insert login_history table
								$login_data = array(
										"user_id" => $user_id,
										"ip" => $ip,
										"browser" => $browser,
										"country" => $country,
										"city" => $city,
										"region" => $region,
										"platform"=>$platform,
										"device"=>$device,
										"device_id"=>$device_id,
										"imei_no"=>$imei_no
									);
								DB::table('login_history')->insert($login_data);
								
								$user = Auth::user(); 
								$success['userInfo'] = $user;
								$success['token'] =  $user->createToken('MyApp')->accessToken; 
								$coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->where("default_sel",1)->first();
								
						        $pairinginfo = Redis::lrange('pairing:'.$coinList['coin_symbol'],0,1000);
						        $pairinfo=json_decode($pairinginfo[0]);
						        
								$success['market_symbol'] =  $pairinfo->coin_symbol."_".$coinList['coin_symbol'];
								$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
								$Token2fa_validation_initial="";
								$google_2fa_key="";
								$first_name="";
								if(!empty($userData)){
									$Token2fa_validation_initial = $userData->Token2fa_validation_initial;
									$google_2fa_key=$userData->google_2fa_key;
									$first_name=$userData->first_name;

									if($userData->ref_code == ""){
										$ref_code = "BREXILY"."00".$user_id;
										$updateData = array(
										"ref_code"=>$ref_code
										);
										Userinfo::where('user_id',"=",$user_id)->update($updateData);
									}

									/*** Referral Tree ***/
										self::referralTree($userData,$checkEmailExistOrNot);
								}
								$success['Token2fa_validation_initial'] =  $Token2fa_validation_initial;
					   			$success['google_2fa_key'] =  $google_2fa_key;
					   			if($google_2fa_key!=""){
					   				if($userData->google2fa_qrcode_url==""){
					   					$google2fa = (new \PragmaRX\Google2FA\Google2FA());
										$qrCodeUrl = $google2fa->getQRCodeUrl(
												"Brexily",
												$email,
												$google_2fa_key
											);
										
										$google2fa_url = UserController::custom_generate_qrcode_url($qrCodeUrl,$google_2fa_key,$user_id);
					   				}
					   			}
					   			$success['first_name'] =  $first_name;
					   			ApiSessions::where("user_id",$user_id)->update(['is_expired'=>1]);
							   	$session_key=Hash::make($user_id.time());
					   			ApiSessions::insert(array(
						   				"user_id"=>$user_id,
						   				"session_key"=>$session_key,
						   				"expire_date"=>date("Y-m-d H:i:s",strtotime("+5 minutes")),
						   				"is_expired"=>0
						   			)
					   			);
					   			$success['session_key'] =  $session_key;
					   			$coinList = CoinListing::where("status","!=",0)->get()->toArray();
					   			foreach ($coinList as $cl) {
					   				$balance = Balance::where('user_id', $user_id)->where('currency_symbol', $cl['coin_symbol'])->first();
					   				if($balance===null){
					   					$b=array(
					   						"user_id"=>$user_id,
					   						"currency_symbol"=>$cl['coin_symbol']
					   					);
					   					Balance::insert($b);
					   				}
					   			}
					   			
								return $this->sendResponse($success, 'User login successfully.');
							}else{ 
								return $this->sendError('Unauthorised.', ['error'=>'Unauthorised']);
							}
						}	
					}else{
						
						$msg1 = array("msg"=>"Invalid  Email Id");
						return response()->json(["Success"=>false,'status' => 422, 'Result' =>$msg1 ], 422);
					}
				}
				
			}else{
				$msg1 = array("msg"=>"Invalid  Email Id");
				return response()->json(["Success"=>false,'status' => 422, 'Result' =>$msg1 ], 422);
			}
			
			
		}
		
        
    }
	
	public static function generate_randam_string($input, $strength = 8) {
		$input_length = strlen($input);
		$random_string = '';
		for($i = 0; $i < $strength; $i++) {
			$random_character = $input[mt_rand(0, $input_length - 1)];
			$random_string .= $random_character;
		}
	 
		return "BR".$random_string;
	}
	
	// two-fa status
	
	public function twofa_status(Request $request)
	{
		
		$validator = Validator::make($request->all(), [
			'email' => 'required',
			'password' => 'required',
		]);
		if ($validator->fails()) {
			return response()->json(['Success' => false,"status"=>422 , 'Result' => $validator->errors()], 200);
		}else{
			$email = request('email');
			$password = request('password');
			
			$checkEmailExistOrNot = User::where('email', '=', $email)->first();
			if (@count($checkEmailExistOrNot)>0) {
				if($checkEmailExistOrNot->status!="A"){
					$msg1 = array("msg"=>"Oops! Your account is not yet verified ,please check email and verify to login.");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
					
				}
				if($checkEmailExistOrNot->is_user_blocked==1){
					$msg1 = array("msg"=>"Your account has been deactivated.");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
					
				}
				$user_id = $checkEmailExistOrNot->user_id;
				$dbpwd = $checkEmailExistOrNot->password;			

				//check password 
				if (!Hash::check($password, $dbpwd)) {
					$msg1 = array("msg"=>"Incorrect password","result"=>"");
					return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 500);
				}else{
					$lock_release_date=$checkEmailExistOrNot->lock_release_date;
					$is_user_locked=$checkEmailExistOrNot->is_user_locked;
					if($lock_release_date>date("Y-m-d H:i:s") && $is_user_locked==1 ){
						$msg1 = array("msg"=>"Based on your request your account has been locked and will be unlocked within 24 hours.");
						return response()->json(["Success"=>false,'status' => 422, 'Result' => $msg1], 422);
						
					}else{
						$checkEmailExistOrNot->is_user_locked=0;
						$checkEmailExistOrNot->save();
					}
					$chkTwoFaStatus = Userinfo::select("login_tfa_status")->where('user_id', '=', $user_id)->first();
					if(!empty($chkTwoFaStatus)){
						if($chkTwoFaStatus->login_tfa_status == 'A'){
							$msg1 = array("msg"=>"Twofa_Status active","result"=>"A");
							return response()->json(["Success"=>true,'status' => 200, 'Result' =>$msg1 ], 200);	
						}else{
							$msg1 = array("msg"=>"Twofa_Status Inactive","result"=>"D");
							return response()->json(["Success"=>true,'status' => 200, 'Result' =>$msg1 ], 200);
						}	
					}else{
						$msg1 = array("msg"=>"User Does Not Exist","result"=>"");
						return response()->json(["Success"=>false,'status' => 422, 'Result' =>$msg1 ], 200);
					}
				}
				
			}else{
				$msg1 = array("msg"=>"Invalid  Email Id","result"=>"");
				return response()->json(["Success"=>false,'status' => 422, 'Result' =>$msg1 ], 200);
			}
			
		}
		
        
    }
	
	
	
	// send Forgot password email to  user
	public function recoverPassword(Request $request) {
		
		$validator = Validator::make($request->all(), [
			'email' => 'required',
		]);
		if ($validator->fails()) {
			return response()->json(["Success"=>false,"status"=>422 ,'Result' => $validator->errors()], 200);
		}

		$email = request('email');
		$platform = request('platform'); // for mobile
		
		$res = User::where('email', '=', $email)->first();
		if ($res != null) {
			$user_id = $res['user_id'];
			$userInfo = Userinfo::where('user_id', '=', $user_id)->first();
			$fullname = $userInfo['first_name'] . ' ' . $userInfo['last_name'];
			$mobileno = $userInfo['mobile_no'];

			
			$APPLICATION_URl = config('constants.APPLICATION_URL');
			$url = $APPLICATION_URl . '/recover/' . encrypt($user_id);
			$data['user'] = array('fullname'=> ucfirst($fullname),'toemail'=> $email,'url' => $url); 
			$emailid = array('toemail' => $email);
			//send email
			Mail::send(['html'=>'email_templates.recoverpassword-email'], $data, function($message) use ($emailid) {
				$message->to($emailid['toemail'], 'Brexily Forgot Password E-mail')->subject
				('Brexily Forgot Password E-mail');
				$message->from('support@brexily.com','Brexily');
			});

			ResetPasswordHistory::create([
				'user_id' => $res['user_id'],
				'status' => 0,
				'timestamp' => date('Y-m-d H:i:s'),
				'req_from' => ($platform == '') ? 'web' : $platform,
			]);

			return response()->json(["Success"=>true,"status"=>200 ,'Result' => 'Dear user we sent reset password link to your registered E-mail. Please verify'], 200);
		} else {
			return response()->json(["Success"=>false,"status"=>422 ,'Result' => 'Please provide registered E-mail'], 200);
		}
		
	}
	
	public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'password' => 'required',
		   'confirmedpassword' => 'required|same:password',
		   'encrypt_user_id' => 'required',
		]);
   
        if($validator->fails()){
            return response()->json(["Success"=>false,'Status' => 422,"Message"=>"failure", 'Result' => $validator->errors()], 200);      
        }else{
			
			$password = request('password');
			$confirmedpassword = request('confirmedpassword');
			
			$encrypt_user_id = request('encrypt_user_id');
			$decrypt_user_id = decrypt($encrypt_user_id);
			
			$qs = ResetPasswordHistory::where('user_id', $decrypt_user_id)->orderBy('rec_id', 'desc')->first();
			$expirelinkStatus = $qs->status;
			
			if (strlen($confirmedpassword) < 8) {
					return response()->json(['Success' => false, 'Result' => "Password Length minimum 8 characters"], 200);
			} else if (!preg_match("#.*^(?=.{8,20})(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).*$#", $confirmedpassword)) {
				return response()->json(['Success' => false, 'Result' => "Password should include lowercase, uppercase, numbers and special characters(?!@)"], 200);
			} else if ($confirmedpassword != $password) {
				return response()->json(['Success' => false, 'Result' => "Confirm Password should be equal to New Password"], 200);
			}else if ($expirelinkStatus == 1) {
					return response()->json(['Success' => false, 'Result' => "Reset Password Link Expired"], 200);
			}else{
			
				$updata = array(
						"password"=> \Hash::make($confirmedpassword),
						);
				$res = User::where('user_id',"=",$decrypt_user_id)->update($updata);
				
				// update ResetPasswordHistory table status column
				if ($qs !== null) {
					$qs->status = 1;
					$qs->save();
				}
				
				
				return response()->json(["Success"=>true,'status' => 200, 'Result' => "Password Updated Successfully"], 200);
				
			}
			
		}
   
        
    }

    public static function referralTree($userData,$checkEmailExistOrNot){

    	$res = ReferralNodes::select("*")->where("descendant_id","=",$userData->user_id)->first();
											
		if(empty($res)){
			if($userData->applied_ref_code != ""){
				$descendant_id = $userData->user_id;
				$refResult = Userinfo::select("*")->where("ref_code","=",$userData->applied_ref_code)->groupBy('user_id')->get();
				if(@count($refResult)>0){
					$ancestor_id = $refResult[0]->user_id;
					$insertObj = array(
						"ancestor_id"=> $ancestor_id,
						"descendant_id"=> $descendant_id
					);
					
					$resrefNodes = DB::table('referrals_nodes')->insert($insertObj);

					// Start Airdrops Registred user in september month need to add referral bonus
					$cur_date = date('Y-m-d');
					if($checkEmailExistOrNot->created_at >= '2020-09-01' && $checkEmailExistOrNot->created_at < '2020-10-01'){

						if($cur_date <= '2020-10-30'){

							$idata = array('user_id'=>$userData->user_id,'from_id'=>$userData->user_id,'evr'=>60);
							DB::table('referral_bonus')->insert($idata);

							$idata = array('user_id'=>$ancestor_id,'from_id'=>$userData->user_id,'evr'=>3);
							DB::table('referral_bonus')->insert($idata);

							$query = DB::table('reward_incentives')
							  ->where('user_id',$ancestor_id)->where('reward_id',1);
							$query->increment('total_qty',3);							
							$query->increment('balance',3);
						}
					}
					//end of Airdrops

				}else{
					$insertObj = array(
						"ancestor_id"=>$userData->user_id,
						"descendant_id"=>$userData->user_id
					);
					
					$resrefNodes = DB::table('referrals_nodes')->insert($insertObj);
				}
				
			}else{						
				$insertObj = array(
						"ancestor_id"=>$userData->user_id,
						"descendant_id"=>$userData->user_id
					);
					
				$resrefNodes = DB::table('referrals_nodes')->insert($insertObj);
			}
		}
    }
	
	
	
}
