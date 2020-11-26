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
use DB;
use App\Balance;

class RegisterController extends BaseController
{ 
	
    /**
     * Register api
     *
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);
   
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());       
        }
   
        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);
        $success['token'] =  $user->createToken('MyApp')->accessToken;
        $success['name'] =  $user->name;
   
        return $this->sendResponse($success, 'User register successfully.');
    }
	
	
	public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'email' => 'required|email',
           'terms_of_use' => 'required',
        ]);
   
        if($validator->fails()){
            $res = array_combine($validator->messages()->keys(), $validator->messages()->all());
			$result = implode($res, ',');
			//$msg = array("msg"=>$result);
			return response()->json(["Success"=>false,'Status' => 422,"Message"=>"failure", 'Result' => $result], 200);      
        }else{
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
			    return response()->json(["Success"=>false,'status' => 422, 'Result' => "Google Verification Failed"], 422);
			}
			
			$email = request('email');
			$terms_of_use = request('terms_of_use');
			$referral_code = request('referral');
			// check E-maial exist or not
			$checkEmailExistOrNot = User::where('email', '=', $email)->first();
			
			if (@count($checkEmailExistOrNot)==0) {
				
				// check referral code valid or not 
				if(!empty($referral_code)){
					$checkValidReferralcode = Userinfo::where('ref_code', '=', $referral_code)->first();
					if(@count($checkValidReferralcode)>0){
						$isValidReferral = "yes";
					}else{
						$isValidReferral = "no";
					}
				}else{
					$isValidReferral = "yes";
				}
				
				if($isValidReferral == "yes"){
				
					//insert record in User Table
					$data = array(
							"email"=>$email,
							"created_at"=>date("Y-m-d H:i:s"),
							"terms_and_conditions"=>$terms_of_use
							);
					$res = User::create($data);
					$lastInsertId = $res->user_id;
					
					//insert record in userinfo table
					$ref_code = "BREXILY"."00".$lastInsertId;
					$data2 = array(
							"user_id"=>$lastInsertId,
							"ref_code"=>$ref_code,
							"applied_ref_code"=>($referral_code)?$referral_code:""
							);
					$res = Userinfo::insert($data2);
					$APPLICATION_URl = config('constants.APPLICATION_URL');
					$url = $APPLICATION_URl . '/verify/' . encrypt($lastInsertId);
					$data['user'] = array('toemail'=> $email,'url' => $url); 
					$emailid = array('toemail' => $email);
					//send email
					Mail::send(['html'=>'email_templates.verify-email'], $data, function($message) use ($emailid) {
						$message->to($emailid['toemail'], 'Brexily Verification')->subject
						('Brexily Verification');
						$message->from('support@brexily.com','Brexily');
					});
					
					return response()->json(["Success"=>true,'status' => 200, 'Result' => "Congrats Sign-Up Successful Please Check Your Email For Further Verification "], 200);
					
				}else{
					return response()->json(["Success"=>false,'Status' => 422,"Message"=>"failure",'Result'=>"Invalid Referral code"], 200);
				}
				
			}else{
				return response()->json(["Success"=>false,'Status' => 422,"Message"=>"failure",'Result'=>"Email Already Exist"], 200);
			}
			
		}
   
        
    }
	
	// verification status API
	public function verificationStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'encrypt_user_id' => 'required',
		]);
   
        if($validator->fails()){
            //$res = array_combine($validator->messages()->keys(), $validator->messages()->all());
			//$result = implode($res, ',');
			//$msg = array("msg"=>$result);
			return response()->json(["Success"=>false,'Status' => 422,"Message"=>"failure", 'Result' =>$validator->errors()], 200);      
        }else{
			
			$encrypt_user_id = request('encrypt_user_id');
			$decrypt_user_id = decrypt($encrypt_user_id);
			
			
			$res = User::where('user_id',"=",$decrypt_user_id)->where("status","=", "A")->first();
			if(@count($res)>0){
				$msg1 = array("msg"=>"Your Verification Already Completed !","status"=>"A");
				return response()->json(["Success"=>true,'status' => 200, 'Result' =>$msg1 ], 200);	
			}else{
				$msg1 = array("msg"=>"Your Verification Is Pending","status"=>"D");
				return response()->json(["Success"=>true,'status' => 200, 'Result' =>$msg1 ], 200);
			}
				
			
			
		}
   
        
    }
	
	
	
	public function createPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'password' => 'required',
		   'confirmedpassword' => 'required|same:password',
		   'encrypt_user_id' => 'required',
		   'email_verification_status' => 'required',
        ]);
   
        if($validator->fails()){
            //$res = array_combine($validator->messages()->keys(), $validator->messages()->all());
			//$result = implode($res, ',');
			//$msg = array("msg"=>$result);
			return response()->json(["Success"=>false,'Status' => 422,"Message"=>"failure", 'Result' =>$validator->errors()], 200);      
        }else{
			
			$password = request('password');
			$confirmedpassword = request('confirmedpassword');
			$email_verification_status = request('email_verification_status');
			$encrypt_user_id = request('encrypt_user_id');
			$decrypt_user_id = decrypt($encrypt_user_id);
			
			if (strlen($confirmedpassword) < 8) {
					return response()->json(['Success' => false, 'Result' => "Password Length minimum 8 characters"], 200);
			} else if (!preg_match("#.*^(?=.{8,20})(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).*$#", $confirmedpassword)) {
				return response()->json(['Success' => false, 'Result' => "Password should include lowercase, uppercase, numbers and special characters(?!@)"], 200);
			} else if ($confirmedpassword != $password) {
				return response()->json(['Success' => false, 'Result' => "Confirm Password should be equal to New Password"], 200);
			}else{
			
				$updata = array(
						"password"=> \Hash::make($confirmedpassword), //bcrypt($confirmedpassword),
						"status"=>$email_verification_status,
						);
				$res = User::where('user_id',"=",$decrypt_user_id)->update($updata);

				//Welcome bonus from october 10th, in USDT
				$userData = User::where('user_id',"=",$decrypt_user_id)->first();
				if($userData->created_at > '2020-10-09'){

					$result = DB::table('referral_bonus_constant')->first();
	        		if($result->welcome_bonus_status == 1){ 
	        			$res = DB::table('reward_incentives')->where('user_id',$decrypt_user_id)->where('reward_id',2)->first();
	        			if(empty($res)){ 

		   				$bonus = $result->welcome_bonus;
		   				$insertObj = array(
									"user_id"=>$decrypt_user_id,
									"reward_id"=>2,
									"currency_symbol"=>'USDT',
									"total_qty"=>$bonus,
									"used"=>0,
									"balance"=>$bonus,
									"sufficient_evr"=>1,
								);
											
						$cron_id = DB::table('reward_incentives')->insertGetId($insertObj);

						$insertObj1 = array(
											"user_id"=>$decrypt_user_id,
											"reward_id"=>2,
											"currency_symbol"=>'USDT',
											"total_qty"=>$bonus,
											"used"=>0,
											"balance"=>$bonus,
											"sufficient_evr"=>1,
											"job_name"=>'Welcome Bonus',
										);
						$cron_id = DB::table('reward_incentives_audit')->insertGetId($insertObj1);

						}
	        		}
	        	}
				
				return response()->json(["Success"=>true,'status' => 200, 'Result' => "Password Created Successfully"], 200);
				
			}
			
		}
   
        
    }
	
	
   
   
}
