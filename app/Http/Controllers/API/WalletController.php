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
use App\BaseCurrency;
use App\BaseCurrencyPairing;
use App\Balance;
use App\BalanceLog;
use App\Biding;
use App\Withdraw;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Log;
use App\Coinhistory;
use PragmaRX\Google2FA\Google2FA;
use App\ShortLinks;
use App\SelfTransactionsHistory;
use App\InternalTransactionHistory;
use App\KycVerification;
use App\AddressBook;
use App\UserAddresses;
use App\Http\Controllers\API\DepositController;
use App\Library\NodeApiCalls;
class WalletController extends BaseController 
{
	public function walletlist(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'page' => 'required'
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 200);            
        }
        $page=request('page');
        $search=request('search');
		$currencyList = array();
		
		$usd_amount=0;
		Log::info("wallet list start");
		//$coinList = CoinListing::where("status","!=",0)->orderBy("coin_symbol", "ASC")->skip(0)->take(10)->get();
		$page=$page*15;
		$coin_arr=array();

		$main_bal_sum = CoinListing::select(DB::raw('SUM(main_balance*coin_price) as main_bal'))->leftJoin('dbt_balance','dbt_balance.currency_symbol','=','coin_listing.coin_symbol')->where("coin_listing.status","!=",0)->where('dbt_balance.user_id',$user_id)->get();
		$total_main_account_bal=$main_bal_sum[0]->main_bal;
		$tradeAccsum = CoinListing::select(DB::raw('SUM(balance*coin_price) as total_trade_bal'))->leftJoin('dbt_balance','dbt_balance.currency_symbol','=','coin_listing.coin_symbol')->where("coin_listing.status","!=",0)->where('dbt_balance.user_id',$user_id)->get();
		$total_trade_account_bal=$tradeAccsum[0]->total_trade_bal;
		$coinList = CoinListing::leftJoin('dbt_balance','dbt_balance.currency_symbol','=','coin_listing.coin_symbol')->where("coin_listing.status","!=",0)->where('dbt_balance.user_id',$user_id)->where('coin_listing.coin_name','like',"%".$search."%")->orderBy("dbt_balance.main_balance", "DESC")->skip($page)->take(12)->get()->toArray();
		$totalInordersUsd = 0;
		foreach ($coinList as $cl) {
			Log::info("wallet list start looop");
			$coin_arr[]=$cl['coin_symbol'];
			$main_balance=0;
			$trading_balance=0;
			$amount_available=0;
			$buy_amount_available=0;
				$amount_available = Biding::where("status","=",2)->where("bid_type","SELL")->where("market_symbol","like",$cl['coin_symbol']."\_%")->where('user_id',$user_id)->sum('bid_qty_available');
				$buy_amount_available = Biding::where("status","=",2)->where("bid_type","BUY")->where("market_symbol","like","%\_".$cl['coin_symbol'])->where('user_id',$user_id)->sum('amount_available');
				/*$amount_available_res =DB::select(
		          	'call brexily_normal_crypto_in_orders_amt_proc(?, ?,?)',
		          	[
		              	2,$user_id,$cl['coin_symbol']."\_%"
		        
		          	]
		      	);
		      	$buy_amount_available_res =DB::select(
		          	'call brexily_base_crypto_in_orders_amt_proc(?, ?,?)',
		          	[
		              	2,$user_id,"%\_".$cl['coin_symbol']
		        
		          	]
		      	);*/
		      	/*foreach ($amount_available_res as $av) {
		      		$buy_amount_available=$av->bid_qty_available;
		      		
		      	}
		      	foreach ($buy_amount_available_res as $ba) {
		      		$amount_available=$ba->amount_available;
		      	}
		      	
		      	if($amount_available==null){
		      		$amount_available=0;
		      	}
		      	if($buy_amount_available==null){
		      		$buy_amount_available=0;
		      	}*/
		      	/*$amount_available = Biding::where("status","=",2)->where("bid_type","SELL")->where("market_symbol","like",$cl['coin_symbol']."\_%")->where('user_id',$user_id)->sum('bid_qty_available');
				$buy_amount_available = Biding::where("status","=",2)->where("bid_type","BUY")->where("market_symbol","like","%\_".$cl['coin_symbol'])->where('user_id',$user_id)->sum('amount_available');*/
				$totalInorders=$amount_available+$buy_amount_available;
				$usd_price = CoinListing::select('coin_price')->where('coin_symbol',$cl['coin_symbol'])->first();
				$totalInordersUsd += $totalInorders*$usd_price->coin_price;
				$main_balance=$cl['main_balance'];
				$trading_balance=$cl['balance'];
				
				$coinUsdVal=floatval($main_balance)*floatval($cl['coin_price']);
				$usd_amount=$usd_amount+$coinUsdVal;

			if($cl['status']==1){
				
				$statusArr=array(
					"status_symbol"=>"A",
					"status_text"=>"Active"
				);
				$transfer_internally=$cl['transfer_internally'];
				$deposit=$cl['deposit'];
				$withdraw=$cl['withdraw'];
				$earncrypto=$cl['earncrypto_status'];
			}else{
				if($cl['status']==2){
					$statusArr=array(
						"status_symbol"=>"C",
						"status_text"=>"Coming Soon"
						
					);
					$transfer_internally=0;
					$deposit=0;
					$withdraw=0;
					$earncrypto = 0;
				}else{
					$statusArr=array(
						"status_symbol"=>"M",
						"status_text"=>"Maintanance"
						
					);
					$transfer_internally=$cl['transfer_internally'];
					$deposit=0;
					$withdraw=0;
					$earncrypto = 0;
				}
			}
			$advance_perc="50";
			if($cl['is_stablecoin']==1){
				$advance_perc="80";
			}

			$currencyList[] = array(
								"id"=>$cl['id'],
								"wallet_name"=>$cl['coin_name'],
								"wallet_symbol"=>$cl['coin_symbol'],
								"wallet_image"=>$cl['coin_image'],
								"staking"=>$cl['staking'],
								"advance"=>$cl['advance'],
								"is_stablecoin"=>$cl['is_stablecoin'],
								"advance_perc"=>$advance_perc,
								"deposit"=>$deposit,
								"withdraw"=>$withdraw,
								"transfer_internally"=>$transfer_internally,
								"earncrypto"=>$earncrypto,
								"deposit_fee"=>$cl['deposit_fee'],
								"withdrawal_fee"=>$cl['withdrawal_fee'],
								"minimum_withdrawal_amt"=>$cl['minimum_withdrawal_amt'],
								"main_balance"=>number_format_eight_dec($main_balance),
								"m_balance"=>$main_balance,
								"trading_balance"=>number_format_eight_dec($trading_balance),
								"in_orders"=>number_format_eight_dec($totalInorders),
								"fees"=>0,
								"wallet_usd_value"=>$cl['coin_price'],
								"coin_status"=>$statusArr,
								"is_erc20"=>$cl['is_erc20']
								);
		}
		
		usort($currencyList, "walletMbal_compare");
		$totalAssetsUsd = $total_main_account_bal+$total_trade_account_bal+$totalInordersUsd;
		$res=array("totalAssetsUsd"=>number_format_two_dec($totalAssetsUsd),"total_main_account_bal"=>number_format_two_dec($total_main_account_bal),"total_trade_account_bal"=>number_format_two_dec($total_trade_account_bal),"totalInordersUsd"=>number_format_two_dec($totalInordersUsd),"wallets_list"=>$currencyList);
		return response()->json(['status'=>'Success','Result'=>$res], 200);
		
	}

	
	public function withdrawRequest(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'wallet_symbol' => 'required',
                'amount' => 'required',
                'wallet_address' => 'required',
                'description' => 'required',
                'is_two_fa_enabled'=>'required'

                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 200);            
        }
        $wallet_symbol=request('wallet_symbol');
		$amount=request('amount');
		$wallet_address=request('wallet_address');
		$description=request('description');
		$label=request('label');
		$usd_amount=request('usd_amount');
		$fees=request('fees');
		$two_fa=request('two_fa');
		$is_two_fa_enabled=request('is_two_fa_enabled');
		$total_usd_amount=request('total_usd_amount');
		$totalMainAccountAmount=request('totalMainAccountAmount');

		$useraddress=UserAddresses::where('user_id',$user_id)->where('wallet_address',$wallet_address)->first();
		if($useraddress!==null){
			return response()->json(['status'=>'Failure','Result'=>'Cannot transfer amount to self address.',"type"=>"self"], 400);
		}

		$coinList = CoinListing::where("status","=",1)->where("coin_symbol",$wallet_symbol)->first();
		//echo $amount." ".$coinList['minimum_withdrawal_amt'];exit;
		if($coinList!==null){
			if($amount<$coinList['minimum_withdrawal_amt']){
				return response()->json(['status'=>'Failure','msg'=>'Minimum withdrawal amount is '.$coinList['minimum_withdrawal_amt']], 400);
			}
		}else{
			return response()->json(['status'=>'Failure','msg'=>'Wallet not available.'], 400);
		}
		$userinfo=Userinfo::where("user_id",$user_id)->first();
		if($is_two_fa_enabled=="A"){
			$google2fa = new Google2FA();
	        $window = 0;
	        $res=$google2fa->verifyKey($userinfo['google_2fa_key'], $two_fa,$window);
	        if(!$res){
	        	return response()->json(['status'=>'Failure','msg'=>'Please enter correct verification code'], 400);
	          
	        }
		}
		// $userkyc=KycVerification::where('user_id',$user_id)->first();
		// if($userkyc!==null){
		// 	if($userkyc['status']=='reject'){
		// 		return response()->json(['status'=>'Failure','Result'=>"Your KYC is rejected"], 401);
		// 	}else if($userkyc['status']=='pending'){
		// 		return response()->json(['status'=>'Failure','Result'=>"Your KYC is pending for approval."], 401);
		// 	}
			
		// }else{
		// 	return response()->json(['status'=>'Failure','Result'=>"You are not submitted the KYC."], 401);
		// }
		$session_key=DepositController::create_api_session_at_withdraw($user_id);
		$authRes=NodeApiCalls::auth_at_withdraw($user_id,$session_key);
		
		$authtoken=$authRes->data->token;
		
		$walletRes=NodeApiCalls::wallet_creation_at_withdraw($user_id,$authtoken,$wallet_symbol,$coinList['is_erc20']);
		
		Log::info("wallet creation ".json_encode($walletRes));
		if($walletRes->success){
			$user_wallet_address=$walletRes->data->address;
			$idata=array(
					"user_id"=>$user_id,
					"wallet_symbol"=>$wallet_symbol,
					"wallet_address"=>$user_wallet_address,
					"status"=>1
				);
			UserAddresses::updateOrCreate(["user_id" => $user_id,"wallet_symbol"=>$wallet_symbol], $idata);
				
			$qrcode=DepositController::address_qrcode_generate($user_wallet_address,$user_id,$wallet_symbol);
		}else{
			return response()->json(['status'=>'Failure','Result'=>"Failed to create wallet address."], 401);
		}

		$net_amount=floatval($amount)+floatval($coinList['withdrawal_fee']);
       	$balance = Balance::where('user_id', $user_id)->where('currency_symbol', $wallet_symbol)->first();
       	$coindet=CoinListing::where("coin_symbol",$wallet_symbol)->first();
       	if($balance['main_balance']>=$net_amount){
       		$withdraw_id=rand(100,10000).time();
       		$wdata=array(
       			"user_id"=>$user_id,
       			"withdraw_id"=>"WR".$withdraw_id,
       			"crypto_type_id"=>@$coindet['id'],
       			"currency_symbol"=>$wallet_symbol,
       			"withdraw_address"=>$wallet_address,
       			"amount"=>$amount,
       			"usd_amount"=>$usd_amount,
       			"fees_amount"=>$coinList['withdrawal_fee'],
       			"net_amount"=>$net_amount,
       			"request_date"=>date("Y-m-d H:i:s"),
       			"status"=>0,
       			"description"=>$description
       		);
       		Withdraw::insert($wdata);
       		$deducted_amount=$balance['main_balance']-$net_amount;
       		$balance->main_balance=$deducted_amount;
	       	$balance->save();
	       	$total_usd_amount=str_replace(",", "", $total_usd_amount);
	       	$coinUsdVal=floatval($net_amount)*floatval($coindet['coin_price']);
			$total_usd_amount=floatval($total_usd_amount)-$coinUsdVal;
			$total_usd_amount = ($total_usd_amount <= -0 ? '0.00' : $total_usd_amount);
			$totalMainAccountAmount=str_replace(",", "", $totalMainAccountAmount);
			$totalMainAccountAmount = floatval($totalMainAccountAmount)-$coinUsdVal;
			$totalMainAccountAmount = ($totalMainAccountAmount <= -0 ? '0.00' : $totalMainAccountAmount);
       		$addressbook=AddressBook::where('user_id',$user_id)->where('wallet_symbol',$wallet_symbol)->where('address',$wallet_address)->first();
			if($addressbook!==null){
				if($label!=""){
					$addressbook->label=$label;
					$addressbook->updated_at=date("Y-m-d H:i:s");
					$addressbook->save();
				}
				
			}else{
				if($label!=""){
					$idata=array(
						"user_id"=>$user_id,
						"wallet_symbol"=>$wallet_symbol,
						"address"=>$wallet_address,
						"label"=>$label,
						"created_at"=>date("Y-m-d H:i:s")
					);
					AddressBook::insert($idata);
				}
				
			}
       		/*$balance['main_balance']=$balance['main_balance']-$amount;
       		$balance->save();*/
       		$linkkey=randomstring(12);
			ShortLinks::insert(array(
				"link_key"=>$linkkey,
				"link_value"=>"WR".$withdraw_id
			));
			$email=$data['email'];
       		$edata['useremail'] = array( 'first_name' => $userinfo['first_name'], 'last_name' => $userinfo['last_name'], 'email' => $email,'crypto_amount'=>number_format_eight_dec($amount),"crypto_type"=>strtoupper($wallet_symbol),"dtId"=>$linkkey,"website_url"=>config('constants.APPLICATION_URL'));

			Mail::send(['html'=>'email_templates.withdraw_confirmation'], $edata, function($message) use ($userinfo,$email) {
				$message->to($email, $userinfo['first_name']." ".$userinfo['last_name'])->subject('Brexily Action required : Withdrawal Request');
					$message->from('support@brexily.com ','Brexily');
				});
       		return response()->json(['status'=>'Success','Result'=>"We have sent an email verification to confirm your withdrawal. Please click the link in that email to continue.","main_balance"=>number_format_eight_dec($deducted_amount),"m_balance"=>$deducted_amount,"totalUsdAmount"=>number_format_two_dec($total_usd_amount),"totalMainAccountAmount"=>number_format_two_dec($totalMainAccountAmount)], 200);
       	}else{
       		return response()->json(['status'=>'Failure','Result'=>"Insufficient funds."], 400);
       	}
	}

	public function confirm_withdraw_transaction(Request $request){
		$dtId = request('dtId');
		$sl=ShortLinks::where('link_key',$dtId)->first();
		
		$dtrans=Withdraw::where('withdraw_id',$sl['link_value'])->first();
		if($dtrans!==null){
			if($dtrans['status']==1){
				return response()->json(['status'=>'Failure','Result'=>"Already confirmed the transaction."], 400);
				
			}else if($dtrans['status']==2){
				return response()->json(['status'=>'Failure','Result'=>"Already cancelled the transaction."], 400);
				
			}else if($dtrans['status']==3){
				return response()->json(['status'=>'Failure','Result'=>"Already approved from admin."], 400);
				
			}else if($dtrans['status']==4){
				if($dtrans['is_expired']==1){
					return response()->json(['status'=>'Failure','Result'=>"Your withdraw request is expired."], 400);
				}else{
					return response()->json(['status'=>'Failure','Result'=>"Already rejected from admin."], 400);
				}
				
				
			}else if($dtrans['status']==5) {
				return response()->json(['status'=>'Failure','Result'=>"Approval pending from admin"], 400);
				
			}
			//$balance = Balance::where('user_id', $dtrans['user_id'])->where('currency_symbol', $dtrans['currency_symbol'])->first();
			//if($balance['main_balance']>=$dtrans['net_amount']){
				$dtrans->status=1;
				$dtrans->updated_at=date("Y-m-d H:i:s");
				$dtrans->save();
				
	       		return response()->json(['status'=>'Success','Result'=>"Transaction has been successfully confirmed."], 200);
			/*}else{
				return response()->json(['status'=>'Failure','Result'=>"Insufficient funds."], 400);
			}*/
			
			
		}else{
			return response()->json(['status'=>'Failure','Result'=>"Transaction not available."], 400);
			
		}
		
	}
	public function cancel_withdraw_transaction(Request $request){

		$dtId = request('dtId');
		$sl=ShortLinks::where('link_key',$dtId)->first();
		
		$dtrans=Withdraw::where('withdraw_id',$sl['link_value'])->first();
		if($dtrans!==null){
			if($dtrans['status']==1){
				return response()->json(['status'=>'Failure','Result'=>"Already confirmed the transaction."], 400);
				
			}else if($dtrans['status']==2){
				return response()->json(['status'=>'Failure','Result'=>"Already cancelled the transaction."], 400);
				
			}else if($dtrans['status']==3){
				return response()->json(['status'=>'Failure','Result'=>"Already approved from admin."], 400);
				
			}else if($dtrans['status']==4){
				if($dtrans['is_expired']==1){
					return response()->json(['status'=>'Failure','Result'=>"Your withdraw request is expired."], 400);
				}else{
					return response()->json(['status'=>'Failure','Result'=>"Already rejected from admin."], 400);
				}
				
			}else if($dtrans['status']==5){
				return response()->json(['status'=>'Failure','Result'=>"Approval pending from admin"], 400);
				
			}
			$dtrans->status=2;
			$dtrans->updated_at=date("Y-m-d H:i:s");
			$dtrans->save();
			$balance = Balance::where('user_id', $dtrans['user_id'])->where('currency_symbol', $dtrans['currency_symbol'])->first();
			$balance->main_balance=$balance['main_balance']+$dtrans['net_amount'];
			$balance->save();
			
			return response()->json(['status'=>'Success','Result'=>"Transaction has been successfully cancelled."], 200);
			
		}else{
			return response()->json(['status'=>'Failure','Result'=>"Transaction not available."], 400);
			
		}
	}

	public function lockacc_withdraw_transaction(Request $request){
		$dtId = request('dtId');
		$sl=ShortLinks::where('link_key',$dtId)->first();
		
		$dtrans=Withdraw::where('withdraw_id',$sl['link_value'])->first();
		if($dtrans!==null){
			$user=User::where('user_id',$dtrans['user_id'])->first();
			$user->is_user_locked=1;
			$user->lock_release_date=date("Y-m-d H:i:s",strtotime("1 day"));
			$user->save();
			return response()->json(['status'=>'Success','Result'=>"Account locked successfully."], 200);
		}else{
			return response()->json(['status'=>'Failure','Result'=>"Transaction not available."], 400);
			
		}
	}

	public function transfer_main_to_trading_acc(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'wallet_symbol' => 'required',
                'amount' => 'required'
                
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 400);            
        }
        $wallet_symbol=request('wallet_symbol');
		$amount=request('amount');
		$usd_amount=request('usd_amount');
		$totalMainAccountAmount=request('totalMainAccountAmount');
		$totalTradeAccountAmount=request('totalTradeAccountAmount');
		$balance = Balance::where('user_id', $user_id)->where('currency_symbol', $wallet_symbol)->first();
		if($amount>0){
			if(round($amount,8)>round($balance['main_balance'],8)){
				return response()->json(['status'=>'Failure','Result'=>"Insufficient Main balance"], 400);
			}else{
				// $userkyc=KycVerification::where('user_id',$user_id)->first();
				// if($userkyc!==null){
				// 	if($userkyc['status']=='reject'){
				// 		return response()->json(['status'=>'Failure','Result'=>"Your KYC is rejected"], 401);
				// 	}else if($userkyc['status']=='pending'){
				// 		return response()->json(['status'=>'Failure','Result'=>"Your KYC is pending for approval."], 401);
				// 	}
					
				// }else{
				// 	return response()->json(['status'=>'Failure','Result'=>"You are not submitted the KYC."], 401);
				// }
				$afterTradeBal=floatval($amount)+floatval($balance->balance);
				$afterMainBal=floatval($balance->main_balance)-floatval($amount);
				$balance->balance=$afterTradeBal;
				$balance->main_balance=$afterMainBal;
				$balance->save();

				// $coindet=CoinListing::where("coin_symbol",$wallet_symbol)->first();
				// $totalMainAccountAmount=str_replace(",", "", $totalMainAccountAmount);
		  //      	$coinUsdVal=floatval($amount)*floatval($coindet['coin_price']);
				// $totalMainAccountAmount=floatval($totalMainAccountAmount)-$coinUsdVal;
				// $totalTradeAccountAmount=str_replace(",", "", $totalTradeAccountAmount);
				// $totalTradeAccountAmount=floatval($totalTradeAccountAmount)+$coinUsdVal;

				$main_bal_sum = CoinListing::select(DB::raw('SUM(main_balance*coin_price) as main_bal'))->leftJoin('dbt_balance','dbt_balance.currency_symbol','=','coin_listing.coin_symbol')->where("coin_listing.status","!=",0)->where('dbt_balance.user_id',$user_id)->get();
				$totalMainAccountAmount=$main_bal_sum[0]->main_bal;
				$tradeAccsum = CoinListing::select(DB::raw('SUM(balance*coin_price) as total_trade_bal'))->leftJoin('dbt_balance','dbt_balance.currency_symbol','=','coin_listing.coin_symbol')->where("coin_listing.status","!=",0)->where('dbt_balance.user_id',$user_id)->get();
				$totalTradeAccountAmount=$tradeAccsum[0]->total_trade_bal;

				$transaction_id=rand(100,10000).time();
				$sdata=array(
					"transaction_id"=>"MT".$transaction_id,
					"user_id"=>$user_id,
					"amount"=>$amount,
					"wallet_symbol"=>$wallet_symbol,
					"transaction_type"=>"MAIN_TO_TRADING",
					"usd_amount"=>$usd_amount
				);
				SelfTransactionsHistory::insert($sdata);
				return response()->json(['status'=>'Success','Result'=>"Successfully transferred amount from main account to trading account.","main_balance"=>number_format_eight_dec($afterMainBal),"trading_balance"=>number_format_eight_dec($afterTradeBal),"totalMainAccountAmount"=>number_format_two_dec($totalMainAccountAmount),"totalTradeAccountAmount"=>number_format_two_dec($totalTradeAccountAmount)], 200);
			}
		}else{
			return response()->json(['status'=>'Failure','Result'=>"Amount should be greater than zero."], 400);
		}
		

	}

	public function transfer_trading_to_main_acc(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'wallet_symbol' => 'required',
                'amount' => 'required'
                
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 400);            
        }
        $wallet_symbol=request('wallet_symbol');
		$amount=request('amount');
		$usd_amount=request('usd_amount');
		$totalMainAccountAmount=request('totalMainAccountAmount');
		$totalTradeAccountAmount=request('totalTradeAccountAmount');
		$balance = Balance::where('user_id', $user_id)->where('currency_symbol', $wallet_symbol)->first();
		if($amount>0){
			if(round($amount,8)>round($balance['balance'],8)){
				return response()->json(['status'=>'Failure','Result'=>"Insufficient Trading balance"], 400);
			}else{
				$afterTradeBal=floatval($balance->balance)-floatval($amount);
				$afterMainBal=floatval($amount)+floatval($balance->main_balance);

				$balance->main_balance=floatval($amount)+floatval($balance->main_balance);
				$balance->balance=floatval($balance->balance)-floatval($amount);
				$balance->save();

				// $coindet=CoinListing::where("coin_symbol",$wallet_symbol)->first();
				// $totalMainAccountAmount=str_replace(",", "", $totalMainAccountAmount);
		  //      	$coinUsdVal=floatval($amount)*floatval($coindet['coin_price']);
				// $totalMainAccountAmount=floatval($totalMainAccountAmount)+$coinUsdVal;
				// $totalTradeAccountAmount=str_replace(",", "", $totalTradeAccountAmount);
				// $totalTradeAccountAmount=round($totalTradeAccountAmount,8)-round($coinUsdVal,8);

				$main_bal_sum = CoinListing::select(DB::raw('SUM(main_balance*coin_price) as main_bal'))->leftJoin('dbt_balance','dbt_balance.currency_symbol','=','coin_listing.coin_symbol')->where("coin_listing.status","!=",0)->where('dbt_balance.user_id',$user_id)->get();
				$totalMainAccountAmount=$main_bal_sum[0]->main_bal;
				$tradeAccsum = CoinListing::select(DB::raw('SUM(balance*coin_price) as total_trade_bal'))->leftJoin('dbt_balance','dbt_balance.currency_symbol','=','coin_listing.coin_symbol')->where("coin_listing.status","!=",0)->where('dbt_balance.user_id',$user_id)->get();
				$totalTradeAccountAmount=$tradeAccsum[0]->total_trade_bal;
				
				$transaction_id=rand(100,10000).time();
				$sdata=array(
					"transaction_id"=>"TM".$transaction_id,
					"user_id"=>$user_id,
					"amount"=>$amount,
					"wallet_symbol"=>$wallet_symbol,
					"transaction_type"=>"TRADING_TO_MAIN",
					"usd_amount"=>$usd_amount
				);
				SelfTransactionsHistory::insert($sdata);
				return response()->json(['status'=>'Success','Result'=>"Successfully transferred amount from Trading account to Main account.","main_balance"=>number_format_eight_dec($afterMainBal),"trading_balance"=>number_format_eight_dec($afterTradeBal),"totalMainAccountAmount"=>number_format_two_dec($totalMainAccountAmount),"totalTradeAccountAmount"=>number_format_two_dec($totalTradeAccountAmount)], 200);
			}
		}else{
			return response()->json(['status'=>'Failure','Result'=>"Amount should be greater than zero."], 400);
		}
		

	}

	public function verifyUserByEmail(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'email' => 'required'
                
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 400);            
        }
        $email=request('email');
        if($data['email']==$email){
			return response()->json(['status'=>'Failure','Result'=>"You cannot transfer to yourself."], 400);
		}
        $checkEmailExistOrNot = User::where('email', '=', $email)->first();
		if (@count($checkEmailExistOrNot)>0) {
			if($checkEmailExistOrNot->status!="A"){
				return response()->json(['status'=>'Failure','Result'=>"User account not in active. Please contact support team."], 400);
				
			}
			if($checkEmailExistOrNot->is_user_blocked==1){
				return response()->json(['status'=>'Failure','Result'=>"User account is blocked. Please contact support team."], 400);
				
			}

			$lock_release_date=$checkEmailExistOrNot->lock_release_date;
			$is_user_locked=$checkEmailExistOrNot->is_user_locked;
			if($lock_release_date>date("Y-m-d H:i:s") && $is_user_locked==1 ){
				return response()->json(['status'=>'Failure','Result'=>"User account locked."], 400);
				
			}
			$userinfo=Userinfo::where("user_id",$checkEmailExistOrNot->user_id)->first();
			return response()->json(['status'=>'Success','Result'=>"Email is available.","name"=>ucwords($userinfo['first_name']." ".$userinfo['last_name'])], 200);
		}else{
			return response()->json(['status'=>'Failure','Result'=>"There is no user registered with this email address."], 400);
		}
	}

	public function transfer_internally(Request $request){
		$data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
                'wallet_symbol' => 'required',
                'amount' => 'required',
                'email' => 'required',
                'is_two_fa_enabled'=>'required'
                ]);
		if ($validator->fails()) {
            return response()->json(['status'=>'Failure','Result'=>$validator->errors()], 400);            
        }
        $wallet_symbol=request('wallet_symbol');
		$amount=request('amount');
		$usd_amount=request('usd_amount');
		$email=request('email');
		$is_two_fa_enabled=request('is_two_fa_enabled');
		$total_usd_amount=request('total_usd_amount');
		$totalMainAccountAmount=request('totalMainAccountAmount');
		$two_fa=request('two_fa');
		if($data['email']==$email){
			return response()->json(['status'=>'Failure','Result'=>"You cannot transfer to yourself."], 400);
		}
		if($amount<=0){
			return response()->json(['status'=>'Failure','Result'=>"Amount should be greater than zero."], 400);
		}
		$userkyc=KycVerification::where('user_id',$user_id)->first();
		if($userkyc!==null){
			if($userkyc['status']=='reject'){
				return response()->json(['status'=>'Failure','Result'=>"Your KYC is rejected"], 401);
			}else if($userkyc['status']=='pending'){
				return response()->json(['status'=>'Failure','Result'=>"Your KYC is pending for approval."], 401);
			}
			
		}else{
			return response()->json(['status'=>'Failure','Result'=>"You are not submitted the KYC."], 401);
		}
		$checkEmailExistOrNot = User::where('email', '=', $email)->first();
		if (@count($checkEmailExistOrNot)>0) {
			if($checkEmailExistOrNot->status!="A"){
				return response()->json(['status'=>'Failure','Result'=>"User account not in active. Please contact support team."], 400);
				
			}
			if($checkEmailExistOrNot->is_user_blocked==1){
				return response()->json(['status'=>'Failure','Result'=>"User account is blocked. Please contact support team."], 400);
				
			}
			$lock_release_date=$checkEmailExistOrNot->lock_release_date;
			$is_user_locked=$checkEmailExistOrNot->is_user_locked;
			if($lock_release_date>date("Y-m-d H:i:s") && $is_user_locked==1 ){
				return response()->json(['status'=>'Failure','Result'=>"User account locked."], 200);
				
			}
			
		}else{
			return response()->json(['status'=>'Failure','Result'=>"There is no user registered with this email address."], 400);
			
		}
		$userinfo=Userinfo::where("user_id",$user_id)->first();
		if($is_two_fa_enabled=="A"){
			$google2fa = new Google2FA();
	        $window = 0;
	        $res=$google2fa->verifyKey($userinfo['google_2fa_key'], $two_fa,$window);
	        if(!$res){
	        	return response()->json(['status'=>'Failure','Result'=>'Please enter correct verification code'], 400);
	            
	        }
		}
		$balance = Balance::where('user_id', $user_id)->where('currency_symbol', $wallet_symbol)->first();
		if($amount>$balance['main_balance']){
			return response()->json(['status'=>'Failure','Result'=>"Insufficient Main balance"], 400);
		}else{
			$senderMainBalance=floatval($balance->main_balance)-floatval($amount);
			$balance->main_balance=$senderMainBalance;
			$balance->save();

			$coindet=CoinListing::where("coin_symbol",$wallet_symbol)->first();
			$total_usd_amount=str_replace(",", "", $total_usd_amount);
	       	$coinUsdVal=floatval($amount)*floatval($coindet['coin_price']);
			$total_usd_amount=floatval($total_usd_amount)-$coinUsdVal;
			$total_usd_amount = ($total_usd_amount <= -0 ? '0.00' : $total_usd_amount);
			$totalMainAccountAmount=str_replace(",", "", $totalMainAccountAmount);
			$totalMainAccountAmount=floatval($totalMainAccountAmount)-$coinUsdVal;
			$testamt=floatval($totalMainAccountAmount)-$coinUsdVal;
			$totalMainAccountAmount = ($totalMainAccountAmount <= -0 ? '0.00' : $totalMainAccountAmount);

			$receiver_balance = Balance::where('user_id', $checkEmailExistOrNot->user_id)->where('currency_symbol', $wallet_symbol)->first();
			if($receiver_balance===null){
				$bdata=array(
	                "user_id"=>$checkEmailExistOrNot->user_id,
	                "currency_symbol"=>$wallet_symbol,
	                "main_balance"=>$amount,
	                "created_at"=>date("Y-m-d H:i:s")
	            );
	            Balance::insert($bdata);
			}else{
				$receiver_balance->main_balance=floatval($receiver_balance->main_balance)+floatval($amount);
				$receiver_balance->save();
			}
			
			
			$transaction_id=rand(100,10000).time();
			$sdata=array(
				"user_id"=>$user_id,
				"received_user_id"=>$checkEmailExistOrNot->user_id,
				"transaction_id"=>"IN".$transaction_id,
				"amount"=>$amount,
				"wallet_symbol"=>$wallet_symbol,
				"usd_amount"=>$usd_amount
			);
			InternalTransactionHistory::insert($sdata);
			$linkkey=randomstring(12);
			ShortLinks::insert(array(
				"link_key"=>$linkkey,
				"link_value"=>"IN".$transaction_id
			));
			$fromemail=$data['email'];
			if($checkEmailExistOrNot['first_name'] != ""){
				$receiver_username = ucwords($checkEmailExistOrNot['first_name']." ".$checkEmailExistOrNot['last_name']);
			}else{
				$receiver_username = $checkEmailExistOrNot['email'];
			}
			$edata['useremail'] = array( 'first_name' => $userinfo['first_name'], 'last_name' => $userinfo['last_name'], 'email' => $fromemail,'crypto_amount'=>number_format_eight_dec($amount),"crypto_type"=>strtoupper($wallet_symbol),"dtId"=>$linkkey,'receiver_user_firstname'=>$checkEmailExistOrNot['first_name'],"receiver_user_lastname"=>$checkEmailExistOrNot['last_name'],"receiver_username"=>$receiver_username);

			Mail::send(['html'=>'email_templates.internal_transfer'], $edata, function($message) use ($userinfo,$fromemail) {
				$message->to($fromemail, $userinfo['first_name']." ".$userinfo['last_name'])->subject('Brexily Action required : Transfer to exchange user');
					$message->from('support@brexily.com ','Brexily');
				});
			return response()->json(['status'=>'Success','Result'=>"Successfully transferred amount to '".$email."'","senderMainBalance"=>number_format_eight_dec($senderMainBalance),"totalUsdAmount"=>number_format_two_dec($total_usd_amount),"totalMainAccountAmount"=>number_format_two_dec($totalMainAccountAmount)], 200);
		}
	}

}