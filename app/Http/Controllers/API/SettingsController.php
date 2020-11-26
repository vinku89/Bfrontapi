<?php
namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Userinfo;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Log;
use Illuminate\Support\Facades\Validator;

class SettingsController extends BaseController 
{
	public function setPaymentFeeMode(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'payment_fee_mode' => 'required'
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 200);            
        }
        $fee_mode=request('payment_fee_mode');
        if($fee_mode == 'evr'){
        	$payment_fee_mode = 1;
        	$txt = 'EVR';
        }else{
        	$payment_fee_mode = 0;
        	$txt = 'Base Currency';
        }
        $updateData = array(
							"payment_fee_mode"=>$payment_fee_mode
							);
		$res = Userinfo::where('user_id',"=",$user_id)->update($updateData);
        $fs = DB::table('fee_settings')->first();
        $takerFees = $fs->all_bc_taker_fee;
        $makerFees = $fs->all_bc_maker_fee;

        if($fee_mode == 'evr') {
            $takerFees = $takerFees/2;
            $makerFees = $makerFees/2;
        }
		return response()->json(['status'=>'Success','Result'=>'Payment fee mode set as '.$txt.' updated successfully.', 'takerFees'=>$takerFees, 'makerFees'=>$makerFees ], 200);
	}
	
}