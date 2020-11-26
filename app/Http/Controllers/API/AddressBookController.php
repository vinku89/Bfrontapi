<?php

namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Validator;
use Illuminate\Support\Facades\Mail;
use App\Userinfo;
use DB;
use App\CoinListing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Log;
use App\AddressBook;
class AddressBookController extends BaseController 
{
	public function saveAddressBook(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'label' => 'required',
                'address' => 'required',
                'wallet_symbol' => 'required',
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 200);            
        }
        $label=request('label');
		$address=request('address');
		$wallet_symbol=request('wallet_symbol');
		$addressbook=AddressBook::where('user_id',$user_id)->where('wallet_symbol',$wallet_symbol)->where('address',$address)->first();
		if($addressbook!==null){
			return response()->json(['status'=>'Failure','Result'=>"Address is already available in address book."], 200);
		}else{
			$idata=array(
				"user_id"=>$user_id,
				"wallet_symbol"=>$wallet_symbol,
				"address"=>$address,
				"label"=>$label,
				"created_at"=>date("Y-m-d H:i:s")
			);
			AddressBook::insert($idata);
			return response()->json(['status'=>'Success','Result'=>"Address successfully saved"], 200);
		}

	}
	public function getAddressBook(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                  'wallet_symbol' => 'required',
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 200);            
        }
        $wallet_symbol=request('wallet_symbol');
		$addressbook=AddressBook::where('user_id',$user_id)->where('wallet_symbol',$wallet_symbol)->get()->toArray();
		$addressbookArr=array();
		foreach ($addressbook as $ab) {
			$addressbookArr[]=$ab;
		}
		
		return response()->json(['status'=>'Success','Result'=>$addressbookArr], 200);
	
	}
}