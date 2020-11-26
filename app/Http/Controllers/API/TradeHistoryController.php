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
use App\DepositTransaction;
class TradeHistoryController extends BaseController 
{
	
	public function paymentHistory(Request $request){
		//echo "sridhar";exit;
		$data = $request->user();
		$user_id = $data['user_id'];
		$withdraw_list=Withdraw::where("user_id",$user_id)->orderBy('id','desc')->get()->toArray();
		$deposit_list=DepositTransaction::where("user_id",$user_id)->orderBy('id','desc')->get()->toArray();
		$withdrawArr=array();
		$depositArr=array();
		foreach ($withdraw_list as $w) {
			$payment_status='Waiting at email confirmation';
			if($w['status']==1){
				$payment_status='Pending';
			}else if($w['status']==2){
				$payment_status='Cancelled';
			}else if($w['status']==3){
				$payment_status='Completed';
			}else if($w['status']==4){
				$payment_status='Rejected';
			}else if($w['status']==5){
				$payment_status='Pending';
			}
			if($w['transaction_hash']==""){
				$hash_link="";
			}else{
				//$hash_link="hash_link";
				if($w['currency_symbol']=="BTC"){
					$hash_link="https://live.blockcypher.com/btc/tx/".$w['transaction_hash'];
				}else if($w['currency_symbol']=="LTC"){
					$hash_link="https://live.blockcypher.com/ltc/tx/".$w['transaction_hash'];
				}else if($w['currency_symbol']=="BCH"){
					$hash_link="https://www.blockchain.com/bch/tx/".$w['transaction_hash'];
					
				}else if($w['currency_symbol']=="ETC"){
					$hash_link="https://blockscout.com/etc/mainnet/tx/".$w['transaction_hash'];
					
				}else{
					$hash_link="https://etherscan.io/tx/".$w['transaction_hash'];
				}
				
			} 

			$withdrawArr[]=array(
				"time"=>date('d/m/Y H:i:s', strtotime($w['created_at'])),
				"created_at"=>$w['created_at'],
				"id"=>$w['withdraw_id'],
				"type"=>"Withdrawal",
				"currency"=>$w['currency_symbol'],
				"amount"=>number_format_eight_dec($w['amount']),
				"fee"=>number_format_eight_dec($w['fees_amount']),
				"payment_status"=>$payment_status,
				"verification_status"=>ucwords($w['blockchain_status']),
				"confirmations"=>$w['confirmations'],
				"details"=>$w['transaction_hash'],
				"hash_link"=>$hash_link
			);
		}
		/*print_r($withdrawArr);
		echo "<br>";*/
		foreach ($deposit_list as $d) {
			$payment_status='Pending';
			if($d['status']==1){
				$payment_status='Completed';
			}else if($d['status']==2){
				$payment_status='Cancelled';
			} 
			if($d['remarks']=='Reload transaction from admin'){
				$type = "Reload";
			}else{
				$type = "Deposit";
			}
			
			
			if($d['crypto_symbol']==""){
				$coin_info=CoinListing::where('id', $d['crypto_type_id'])->first();
				$crypto_symbol=$coin_info['coin_symbol'];
			}else{
				$crypto_symbol=$d['crypto_symbol'];
			}
			if($d['transaction_hash']==""){
				$hash_link="";
			}else{
				if($crypto_symbol=="BTC"){
					$hash_link="https://live.blockcypher.com/btc/tx/".$d['transaction_hash'];
				}else if($crypto_symbol=="LTC"){
					$hash_link="https://live.blockcypher.com/ltc/tx/".$d['transaction_hash'];
				}else if($crypto_symbol=="BCH"){
					$hash_link="https://www.blockchain.com/bch/tx/".$d['transaction_hash'];
					
				}else if($crypto_symbol=="ETC"){
					$hash_link="https://blockscout.com/etc/mainnet/tx/".$d['transaction_hash'];
					
				}else{
					$hash_link="https://etherscan.io/tx/".$d['transaction_hash'];
				}
			}
			$depositArr[]=array(
				"time"=>date('d/m/Y H:i:s', strtotime($d['created_at'])),
				"created_at"=>$d['created_at'],
				"id"=>$d['deposit_id'],
				"type"=>$type,
				"currency"=>$crypto_symbol,
				"amount"=>number_format_eight_dec($d['internal_value']),
				"fee"=>0.00,
				"payment_status"=>$payment_status,
				"verification_status"=>ucwords($d['blockchain_status']),
				"confirmations"=>$d['confirmations'],
				"details"=>$d['transaction_hash'],
				"hash_link"=>$hash_link
			);
		}
		// print_r($depositArr);
		// echo "<br>";exit();
		$res = array_merge($withdrawArr, $depositArr); 
  		usort($res, 'sortByTime');
  		$res = array_reverse($res);
		return response()->json(["Success"=>true,'status' => 200,'Result' => $res], 200); 

	}
	public function cryptoCurrencyList(Request $request){
		$result = CoinListing::select('coin_symbol','coin_name','coin_price', 'coin_image')->where('status' , 1)->get();
		return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200); 

	}
	public function mainWalletLedger(Request $request){
		
		$data = $request->user();
		$user_id = $data['user_id'];
		$searchDate = $request->selectedDate;
		$selectDateFilter = $request->selectDateFilter;
		$currencySymbol = $request->currency_symbol['coin_symbol'];
		
        if(!empty($selectDateFilter) && $selectDateFilter != 'Custom Range') {
        	if($selectDateFilter == 'Today'){
        		$from_date = date('Y-m-d'); 
        		$to_date = date('Y-m-d');
        	}else if($selectDateFilter == '7 days'){
        		$from_date = Carbon::today()->subDays(7)->toDateTimeString();
        		$to_date = date('Y-m-d');
        	}
        	else if($selectDateFilter == '2 weeks'){
        		$from_date = Carbon::today()->subDays(14)->toDateTimeString();
        		$to_date = date('Y-m-d');
        	}
        	else if($selectDateFilter == '1 month'){
        		$from_date = Carbon::now()->subDays(30)->toDateTimeString();
        		$to_date = date('Y-m-d');
        	}
        	else if($selectDateFilter == '2 months'){
        		$from_date = Carbon::now()->subDays(60)->toDateTimeString();
        		$to_date = date('Y-m-d');
        	}else{
        		$from_date = date('Y-m-d'); 
        		$to_date = date('Y-m-d');
        	}
        } else{
        	
        	if(!empty($searchDate)) {
        		if(!empty($searchDate[0]) && !empty($searchDate[1])){
        			$from_date = $searchDate[0]; 
	            	$to_date = $searchDate[1];
        		}else if(empty($searchDate[0])){
        			$from_date = $searchDate[1]; 
	            	$to_date = $searchDate[1];
        		}else if(empty($searchDate[1])){
        			$from_date = $searchDate[0]; 
	            	$to_date = $searchDate[0];
        		}else{
        			$from_date = date('Y-m-d'); 
	            	$to_date = date('Y-m-d');
        		}
	           
	        } else {
	            $from_date = date('Y-m-d'); 
	            $to_date = date('Y-m-d');
	        }
        }

        if(!empty($currencySymbol)){
        	$currency_symbol = $currencySymbol;
        }else{
        	$currency_symbol = 'EVR';
        }
        //return response()->json(["Success"=>true,'status' => 200,'Result' => $date], 200); 
		$res=DB::table('main_wallet_ledger')->where("user_id",$user_id)->where("currency_symbol",$currency_symbol)->whereBetween(DB::raw('DATE(transaction_date)'), [$from_date, $to_date])->orderBy('transaction_date','desc')->get()->toArray();
		$arr = array();
		foreach ($res as $key => $value) {
			if($value->type == 'CREDIT'){
				$credit_amt = $value->amount;
				$debit_amt = '0.00';
			}else{
				$credit_amt = '0.00';
				$debit_amt = $value->amount;
			}
			array_push($arr, ["transaction_date"=>$value->transaction_date,"transaction_id"=>$value->transaction_id,"currency_symbol"=>$value->currency_symbol,"description"=>$value->description,"credit_amt"=>number_format_eight_dec($credit_amt),"debit_amt"=>number_format_eight_dec($debit_amt),"balance"=>number_format_eight_dec($value->balance),"transaction_type"=>$value->transaction_type]);
		}
		return response()->json(["Success"=>true,'status' => 200,'Result' => $arr], 200); 
	}
}