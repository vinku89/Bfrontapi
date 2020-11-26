<?php
namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Userinfo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Log;
use Illuminate\Support\Facades\Validator;
use App\KycVerification;
class KycController extends BaseController 
{
	public function save_kyc(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		
		//return response()->json(['status'=>'Success','Result'=>$request->all()], 200);            
		$validator = Validator::make($request->all(), [
                'first_name' => 'required',
                'last_name' => 'required',
                'birth_date' => 'required',
                'gender' => 'required',
                'country' => 'required',
                'city' => 'required',
                'street_address' => 'required',
                'pin_code' => 'required',
                'proof' => 'required'
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 200);            
        }
        $first_name=request('first_name');
		$middle_name=request('middle_name');
		$last_name=request('last_name');
		$birth_date=request('birth_date');
		$gender=request('gender');
		$country=request('country');
		$city=request('city');
		$street_address=request('street_address');
		$pin_code=request('pin_code');
		$proof=request('proof');

        $proof_path_1="";
        $proof_path_2="";
        $selfie_path="";
		if ($request->has('proof_path_1')) {
			$image1 = $request->file('proof_path_1');
			$org_name1 = $image1->getClientOriginalName();
            $exts1 = explode('.', $org_name1);
            if (count($exts1) == 2) {
                $fileType1 = $image1->getClientOriginalExtension();
                $fileTyp1 = strtolower($fileType1);
                $fileData1 = array('proof_path_1' => $image1);
                $allowedTypes1 = array("jpeg", "jpg", "png");
                if (in_array($fileTyp1, $allowedTypes1)) {
                    $rules1 = array('proof_path_1' => 'required|max:100000|mimes:png,jpg,jpeg');
                    $validator = Validator::make($fileData1, $rules1);
                    if ($validator->fails()) {
                    	return response()->json(['status'=>'Failure','message'=>'Upload only JPEG,JPG,PNG images only with lessthan 10MB.'], 200);
                        
                    }else{
                    	$proof_path_1 = time().rand().'.'.$image1->getClientOriginalExtension();
						$destinationPath1 = base_path('/public/kycimages');
						$image1->move($destinationPath1, $proof_path_1);
						//return response()->json(['status'=>'Success','message'=>'File uploaded.'], 200);
					}
				}
			}
		}else{
			return response()->json(['status'=>'Failure','message'=>'Please upload ID proof.'], 200);
		}
		if ($request->has('proof_path_2')) {
			$image2 = $request->file('proof_path_2');
			if($image2!=""){

				$org_name2 = $image2->getClientOriginalName();
	            $exts2 = explode('.', $org_name2);
	            if (count($exts2) == 2) {
	                $fileType2 = $image2->getClientOriginalExtension();
	                $fileTyp2 = strtolower($fileType2);
	                $fileData2 = array('proof_path_2' => $image2);
	                $allowedTypes2 = array("jpeg", "jpg", "png");
	                if (in_array($fileTyp2, $allowedTypes2)) {
	                    $rules2 = array('proof_path_2' => 'required|max:100000|mimes:png,jpg,jpeg');
	                    $validator = Validator::make($fileData2, $rules2);
	                    if ($validator->fails()) {
	                    	return response()->json(['status'=>'Failure','message'=>'Upload only JPEG,JPG,PNG images only with lessthan 10MB.'], 200);
	                        
	                    }else{
	                    	$proof_path_2 = time().rand().'.'.$image2->getClientOriginalExtension();
							$destinationPath2 = base_path('/public/kycimages');
							$image2->move($destinationPath2, $proof_path_2);
							
						}
					}
				}
			}
		}
		if ($request->has('selfie_path')) {
			$image3 = $request->file('selfie_path');
			$org_name3 = $image3->getClientOriginalName();
            $exts3 = explode('.', $org_name3);
            if (count($exts3) == 2) {
                $fileType3 = $image3->getClientOriginalExtension();
                $fileTyp3 = strtolower($fileType3);
                $fileData3 = array('selfie_path' => $image3);
                $allowedTypes3 = array("jpeg", "jpg", "png");
                if (in_array($fileTyp3, $allowedTypes3)) {
                    $rules3 = array('selfie_path' => 'required|max:100000|mimes:png,jpg,jpeg');
                    $validator = Validator::make($fileData3, $rules3);
                    if ($validator->fails()) {
                    	return response()->json(['status'=>'Failure','message'=>'Upload only JPEG,JPG,PNG images only with lessthan 10MB.'], 200);
                        
                    }else{
                    	$selfie_path = time().rand().'.'.$image3->getClientOriginalExtension();
						$destinationPath3 = base_path('/public/kycimages');
						$image3->move($destinationPath3, $selfie_path);
						
					}
				}
			}
		}else{
			return response()->json(['status'=>'Failure','message'=>'Please upload selfie image.'], 200);
		}
		$idata=array(
			"user_id"=>$user_id,
			"first_name"=>$first_name,
			"middle_name"=>$middle_name,
			"last_name"=>$last_name,
			"birth_date"=>$birth_date,
			"gender"=>$gender,
			"country"=>$country,
			"city"=>$city,
			"street_address"=>$street_address,
			"pin_code"=>$pin_code,
			"proof"=>$proof,
			"proof_path_1"=>$proof_path_1,
			"proof_path_2"=>$proof_path_2,
			"selfie_path"=>$selfie_path,
			"status"=>'pending',
			"created_by"=>$user_id
		);
		KycVerification::updateOrCreate(['user_id'=> $user_id], $idata);
		// $userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
		// if($userData->ref_code != ""){
			$updateData = array(
							"first_name"=>$first_name,
							"last_name"=>$last_name,
							"birth_date"=>$birth_date,
							"gender"=>$gender,
							"nationality"=>$country
							);
			$res = Userinfo::where('user_id',"=",$user_id)->update($updateData);
		// }else{
		// 	//generate Brexily referral code
		// 	$name = substr($first_name,0,2);
			
		// 	$ref_code = strtoupper($name)."BREXILY"."00".$user_id;
		// 	$updateData = array(
		// 					"first_name"=>$first_name,
		// 					"last_name"=>$last_name,
		// 					"birth_date"=>$birth_date,
		// 					"gender"=>$gender,
		// 					"nationality"=>$country,
		// 					"ref_code"=>$ref_code
		// 					);
		// 	$res = Userinfo::where('user_id',"=",$user_id)->update($updateData);
		// }
		return response()->json(['status'=>'Success','message'=>'Thank You. Your verification information has been submitted. You will receive an email confirming your successful application for KYC verification. '], 200);
	}
	public function get_kyc_status(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		$kycdet=KycVerification::where('user_id',$user_id)->first();
		if($kycdet!==null){
			return response()->json(['status'=>'Success','message'=>"",'Result'=>$kycdet->status], 200);
		}else{
			return response()->json(['status'=>'Success','message'=>"",'Result'=>"not submitted"], 200);
		}
		

	}
}