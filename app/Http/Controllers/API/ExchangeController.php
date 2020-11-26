<?php

namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\User;
use Illuminate\Support\Facades\Auth;
use Validator;
use Illuminate\Support\Facades\Mail;
use App\Userinfo;
use App\Country;
use App\Referrals;
use App\ReferralBonus;
use App\ResetPasswordHistory;
use DB;
use App\CoinListing;
use App\BaseCurrency;
use App\BaseCurrencyPairing;
use App\Balance;
use App\BalanceLog;
use App\Biding;
use App\BidingLog;
use App\Coinhistory;
use App\Withdraw;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
//use Amqp;
use Log;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPConnection;
use App\Http\Controllers\rabbitmq\RabbitmqController;
use App\Events\buy;
use App\ExecutedOrders;
use App\LatestTradeData;
use App\Last24hCoinhistory;
use App\RecentBidingLog;
use App\RolesSettings;
use App\FeeDeductions;

class ExchangeController extends BaseController 
{
	
	
	public function baseCurrencyList(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_symbol", "ASC")->get();
		$basecurrencyList = array();
		$stablecoinList = array();
		if(@count($coinList)>0){
			
				foreach($coinList as $res){
					$infocoins = Redis::lrange('info:'.$res['coin_symbol'],0,1000);
					if(!empty($infocoins)){
						if($res['is_stablecoin']==1){
							$stablecoinList[] = array(
										"base_currency_id"=>$res['id'],
										"base_currency_name"=>$res['coin_name'],
										"base_currency_symbol"=>$res['coin_symbol'],
										"is_stablecoin"=>$res['is_stablecoin']
										
										);
						}else{
							$basecurrencyList[] = array(
										"base_currency_id"=>$res['id'],
										"base_currency_name"=>$res['coin_name'],
										"base_currency_symbol"=>$res['coin_symbol']
										
										);
						}
					}
					
					
				}
				return response()->json(["Success"=>true,'status' => 200,'Result' => $basecurrencyList,'stablecoinslist'=>$stablecoinList], 200);
		}else{
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => $basecurrencyList,'stablecoinslist'=>$stablecoinList], 200);
		}
		
	}
	
	
	
    public function getBaseCurrencyPairingList(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$base_currency_name = request('base_currency_name');
		if(!empty($base_currency_name)){
			$coin_symbol = 	$base_currency_name;
		}else{
			$coin_symbol = "BTC";
		}
		$infocoins = Redis::lrange('info:'.$coin_symbol,0,1000);
        $infocoins=json_decode($infocoins[0]);
        
        $pairinginfo = Redis::lrange('pairing:'.$coin_symbol,0,1000);
        $pairinfo=array();
        foreach ($pairinginfo as $pk => $pv) {
            $pairinfo[]=json_decode($pv);
        }
        //$pairinginfo=json_decode($pairinginfo[0]);
        
        $basecurrencyList[] = array(
                        "base_currency_id"=>$infocoins->id,
                        "base_currency_name"=>$infocoins->coin_name,
                        "base_currency_symbol"=>$infocoins->coin_symbol,
                        "pairing_list"=>$pairinfo,
                        
                        );
		return response()->json(["Success"=>true,'status' => 200,'Result' => $basecurrencyList], 200);
	}
	
	// Get Market price values
	
	public function getMarketPriceValues()
    {
        $market_symbol = request('base_currency_symbol');//$this->input->get('market', TRUE);

       
		if(!empty($market_symbol)){
		
			$coin_symbol = $market_symbol; // It's base currency symbol (Ex : BTC)
			
			
			$coinList = CoinListing::where("is_base_currency","=",1)->where("coin_symbol","=",$coin_symbol)->where("status","=",1)->orderBy("coin_name", "ASC")->first();
				$pairingArrPrice = array();
				if(@count($coinList)>0){
					$coin_id = $coinList->id;
					$cryptolistfrom = $coinList->coin_symbol;	
					$basePairingCurrency = BaseCurrencyPairing::where("coin_id","=",$coin_id)->where("status","=",1)->get();
					if(@count($basePairingCurrency) > 0){
						
						foreach($basePairingCurrency as $pair){
							$pair_id = $pair['pairing_id'];
							//get pairing_info
							$coinList_pair = CoinListing::where("id","=",$pair_id)->where("status","=",1)->first();
							
							if(@count($coinList_pair) > 0){
								 
								$cryptolistto = $coinList_pair['coin_symbol'];
								//$base_piar = $cryptolistfrom."_".$cryptolistto;
								$base_piar = $cryptolistto."_".$cryptolistfrom;
								//echo $base_piar;exit;
								//SELECT * FROM `dbt_coinhistory` WHERE `market_symbol` = 'EVR_BTC' ORDER by id DESC LIMIT 1
								$market_data = Coinhistory::select("*")->where("market_symbol","=",$base_piar)->orderBy("id","DESC")->LIMIT(1)->first();
								if(!empty($market_data)){
								
									$price = $market_data->last_price;
									$volume = round($market_data->total_coin_supply*100)/100;
									
									$change = $market_data->price_change_24h/$market_data->last_price;
									$price_change_percent = (round($change*100)/100)*100;
									
									$pairingArrPrice[]= array(
														"market_price"=>number_format($price,8),
														"volume"=>$volume,
														"change"=>$price_change_percent,
														);
									
								}else{
								
									/*$test1 = "https://min-api.cryptocompare.com/data/price?fsym=".$cryptolistto."&tsyms=".$cryptolistfrom." ";
								
									$test2 = file_get_contents($test1,true);
									$history1   = json_decode($test2, true);
									//print_r($history1["BTC"]);exit;
									
									if(!empty($history1)){
										if(!empty($history1[$coin_symbol])){
											$cryptPrice = $history1[$coin_symbol];	
										}else{
											$cryptPrice = 0;
										}
									}else{
										$cryptPrice = 0;
									}*/
									$cryptPrice = 0;
									$pairingArrPrice[]= array(
														"market_price"=>number_format($coinList_pair['coin_price'],8),
														"volume"=>0,
														"change"=>0,
														);
								}
								
								
							}
						}
						return response()->json(["Success"=>true,'status' => 200,'Result' => $pairingArrPrice], 200);
						
					}
					
					
				}else{
					return response()->json(["Success"=>false,'Status' => 422, 'Result' => $pairingArrPrice], 200);
				}
			
			
			
		}else{
			return response()->json(["Success"=>false,'status' => 422,'Result' => "Base Currency is missing"], 200);	
		}
	

    }
	
	
	
	
	
	public function getExchange(Request $request)
	{ 
		Log::info("get Exchange start ".microtime(true));
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$market_symbol = request('market'); // "LTC_BTC"; 
		if(!empty($market_symbol)){

			if (strpos($market_symbol, '_') !== false) {
			    $coin_symbol = explode('_', $market_symbol);
			    $check_coin = CoinListing::select("id")->where("coin_symbol","=",$coin_symbol[0])->where("status","=",1)->first();
			    $check_coin1 = CoinListing::select("id")->where("coin_symbol","=",$coin_symbol[1])->where("status","=",1)->first();
			    if($check_coin && $check_coin1){
			    	//true
			    	$qs = BaseCurrencyPairing::select("id")->where("coin_id","=",$check_coin1['id'])->where("pairing_id","=",$check_coin['id'])->where("status","=",1)->first();
			    	if(!$qs){
			    		return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Invalid Market Symbol"], 400);
			    	}
			    	$infocoins = Redis::lrange('info:'.$coin_symbol[1],0,1000);
			    	if(empty($infocoins)){
			    		return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Not BC"], 400);
			    	}
			    }else{
			    	return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Invalid Market Symbol"], 400);
			    }
			}else{
			    return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Invalid Market Symbol"], 400);
			}
		}else{
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Exchange crypto is missing"], 200);
		}
		
		$buy = $coin_symbol[0];
		$sell = $coin_symbol[0];
		
		$balanceTo = Balance::where('user_id', $user_id)->where('currency_symbol', $coin_symbol[1])->first();
		//Log::info("balance to ".json_encode($balanceTo));
		if(!empty($balanceTo)){
			$balance_to = $balanceTo['balance'];
		}else{
			$balance_to = "0.00";
		}
		//Log::info("balance to2 ".$balance_to);
        //$data['fee_to'] = $this->web_model->checkFees('BUY', $coin_symbol[1]);
        $coinListTo = CoinListing::select("id","coin_name","coin_image","coin_price")->where("coin_symbol","=",$coin_symbol[1])->where("status","=",1)->first();
		$coin_idTo = $coinListTo['id'];

		//check role settings and payment of fee mode
        $roleSettings = RolesSettings::select('*')->where("role","=",$data['role'])->first();
        if($roleSettings->fee_applicable == 1){
        	//Check BUY fees
        	$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
            if($userData->payment_fee_mode == 1){
				$feeto = BaseCurrency::select("*")->where("coin_id","=",36)->first();
			}else{
	        	$feeto = BaseCurrency::select("*")->where("coin_id","=",$coin_idTo)->first();
	        }

			if(!empty($feeto)){
				$fee_to = ($feeto->trading_maker_fee)?$feeto->trading_maker_fee:0.00;
			}else{
				$fee_to = 0.00;
			}
		}else{
			$fee_to = 0.00;
		}
		
		
		$balanceFrom = Balance::where('user_id', $user_id)->where('currency_symbol', $coin_symbol[0])->first();
		
		if(!empty($balanceFrom)){
			$balance_from = $balanceFrom['balance'];
		}else{
			$balance_from = "0.00";
		}
		
        //$data['fee_from']       = $this->web_model->checkFees('SELL', $coin_symbol[0]);
		
		$coinList = CoinListing::select("id","coin_name","coin_image","coin_price")->where("coin_symbol","=",$coin_symbol[0])->where("status","=",1)->first();
		$coin_id = $coinList['id'];
		$coin_name = $coinList['coin_name'];
		$coin_image = ($coinList['coin_image'])?$coinList['coin_image']:"";
		$coin_price_dollar = ($coinList['coin_price'])?$coinList['coin_price']:0;

		//check role settings and payment of fee mode
        $roleSettings = RolesSettings::select('*')->where("role","=",$data['role'])->first();
        if($roleSettings->fee_applicable == 1){
        	//Check BUY fees
        	$userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
            if($userData->payment_fee_mode == 1){
				$feeFrom = BaseCurrency::select("*")->where("coin_id","=",36)->first();
			}else{
	        	$feeFrom = BaseCurrency::select("*")->where("coin_id","=",$coin_idTo)->first();
	        }

			if(!empty($feeFrom)){
				$fee_from = ($feeFrom->trading_maker_fee)?$feeFrom->trading_maker_fee:0.00;
			}else{
				$fee_from = 0.00;
			}
		}else{
			$fee_from = 0.00;
		}
		
		$coinhistory=LatestTradeData::where('market_symbol', $market_symbol)->first(); 
		if($coinhistory===null){
			$coinhistory = Coinhistory::select('*')->where('market_symbol', $market_symbol)->orderBy("id","DESC")->LIMIT(1)->first();
		}
		
		//DB::enableQueryLog(); 
		
		
		
		if($coinhistory!==null){
			//DB::enableQueryLog();
			//Log::info("get exchange before latest trade data2".microtime(true));
			$coindata =	Last24hCoinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close'))->where('market_symbol', $market_symbol)->first();
			//Log::info("get exchange before latest trade data3".microtime(true));
			//$coindata =	Coinhistory::select(\DB::raw('MAX(price_high_24h) as price_high_24h,MIN(price_low_24h) as price_low_24h'))->where('date','>=',\DB::raw( 'DATE_SUB(NOW(), INTERVAL 24 HOUR)'))->where('market_symbol', $market_symbol)->first();
			//print_r($coindata);exit;
			if($coindata!==null){
				$max_open=$coindata['max_open'];
				$max_close=$coindata['max_close'];
				$min_open=$coindata['min_open'];
				$min_close=$coindata['min_close'];

				if($max_open>$max_close){
					$high_price =$max_open;
				}else{
					$high_price =$max_close;
				}
				if($min_open<$min_close){
					$low_price =$min_open;
				}else{
					$low_price =$min_close;
				}
			}else{
				
				$coindata =	Coinhistory::select('last_price')->where('market_symbol', $market_symbol)->orderBy('date','desc')->first();
				//dd(DB::getQueryLog());
				if($coindata!==null){
					$high_price = $coindata->last_price;
					$low_price = $coindata->last_price;
				}else{
					$high_price = 0;
					$low_price = 0;
				}
			}
			
			
//dd(DB::getQueryLog());
			$coin_last_price = $coinhistory['last_price'];
			$coinTo_usd_price=$coinListTo['coin_price'];

			$coin_usd_price=$coinhistory['usd_price'];
			if($coin_usd_price==0){
				$coin_usd_price=floatval($coin_last_price)*floatval($coinTo_usd_price);
			}
			//$coin_usd_price=floatval($coin_last_price)*floatval($coinTo_usd_price);
			/*$price_change_24h = $coinhistory->price_change_24h;
			$price_change_percent1 =  round(($price_change_24h/$coin_last_price)*100);
			$price_change_percent2 = ($price_change_percent1/100)*100;*/
			//Log::info("get exchange ".microtime(true).json_encode($coinhistory));
			$change=$coinhistory['change_perc'];
			$mdateArr=explode(" ", $coinhistory['date']);
            $tdate=date("Y-m-d");
            $last24hdate=date("Y-m-d H:i:s", strtotime("-24 hour"));
            if($mdateArr[0]!=$tdate){
                $change=0.00;
            }
			$price_change_percent2=$change;
			

			if($coinhistory['date']>$last24hdate){
				$total_volume = ($coinhistory['volume_24h'])?$coinhistory['volume_24h']:0;
                $volume=floatval($coin_last_price)*floatval($total_volume);
            }else{
            	$total_volume = 0.00;
                $volume=0.00;
            }
			//(Math.round(($price_change_24h/$coin_last_price)*100)/100)*100;
			
					
			
			$coinhistoryArr = array(
								"coin_price_dollar"=>number_format_six_dec_currency($coin_usd_price),
								"coin_last_price"=>number_format_eight_dec(exp2dec($coin_last_price)),
								"coin_change_price"=>number_format_two_dec($price_change_percent2),
								"price_high_24h"=>($high_price)?number_format_eight_dec(exp2dec($high_price)):0,
								"price_low_24h"=>($low_price)?number_format_eight_dec(exp2dec($low_price)):0,
								"total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0]." / ".number_format_four_dec($volume)." ".$coin_symbol[1],
								"coin_total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0],
								"basecurrency_total_volume"=>number_format_four_dec($volume)." ".$coin_symbol[1],
								);
			
		}else{
			$total_volume = 0;
			$coin_last_price = 0;
			$coinhistoryArr = array(
								"coin_price_dollar"=>number_format_four_dec($coin_price_dollar),
								"coin_last_price"=>"0.00",
								"coin_change_price"=>"0.00",
								"price_high_24h"=>"0.00",
								"price_low_24h"=>"0.00",
								"total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0]." / ".number_format_four_dec($coin_last_price)." ".$coin_symbol[1],
								"coin_total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0],
								"basecurrency_total_volume"=>number_format_four_dec($coin_last_price*$total_volume)." ".$coin_symbol[1],
							);
		}
		
		$evr_balance = self::checkBalance($user_id,'EVR');
		$usd_price = CoinListing::select("coin_price")->where("coin_symbol","=",$coin_symbol[1])->where("status","=",1)->first();
		$bc_usd_price = $usd_price['coin_price'];
		$usd_price1 = CoinListing::select("coin_price")->where("coin_symbol","=",'EVR')->where("status","=",1)->first();
		$evr_usd_price = $usd_price1['coin_price'];
		
		$exchangeArr = array(
						"buy"=>$buy,
						"sell"=>$sell,
						"base_currency"=>$coin_symbol[1],
						"pair_currency"=>$coin_symbol[0],
						"coin_name"=>$coin_name,
						"coin_image"=>$coin_image,
						//"coin_price_dollar"=>$coin_price_dollar,
						"balance_to"=>$balance_to,
						"fee_to"=>$fee_to,
						"balance_from"=>$balance_from, 
						"fee_from"=>$fee_from,
						"evr_balance"=>$evr_balance->balance,
						"bc_usd_price"=>$bc_usd_price,
						"evr_usd_price"=>$evr_usd_price,
						"coinhistoryArr"=>$coinhistoryArr
						);
		return response()->json(["Success"=>true,'status' => 200,'Result' => $exchangeArr], 200);
		
	}

//getexchange data without balnce
	public function getExchangeDetails(Request $request)
	{ 
		//Log::info("get Exchange start ".microtime(true));
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$market_symbol = request('market'); // "LTC_BTC"; 
		if(!empty($market_symbol)){

			if (strpos($market_symbol, '_') !== false) {
			    $coin_symbol = explode('_', $market_symbol);
			    $check_coin = CoinListing::select("id")->where("coin_symbol","=",$coin_symbol[0])->where("status","=",1)->first();
			    $check_coin1 = CoinListing::select("id")->where("coin_symbol","=",$coin_symbol[1])->where("status","=",1)->first();
			    if($check_coin && $check_coin1){
			    	//true
			    	$qs = BaseCurrencyPairing::select("id")->where("coin_id","=",$check_coin1['id'])->where("pairing_id","=",$check_coin['id'])->where("status","=",1)->first();
			    	if(!$qs){
			    		return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Invalid Market Symbol"], 400);
			    	}
			    	$infocoins = Redis::lrange('info:'.$coin_symbol[1],0,1000);
			    	if(empty($infocoins)){
			    		return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Not BC"], 400);
			    	}
			    }else{
			    	return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Invalid Market Symbol"], 400);
			    }
			}else{
			    return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Invalid Market Symbol"], 400);
			}
		}else{
			return response()->json(["Success"=>false,'Status' => 422, 'Result' => "Exchange crypto is missing"], 400);
		}
		
		$buy = $coin_symbol[0];
		$sell = $coin_symbol[0];
		
		// $balanceTo = Balance::where('user_id', $user_id)->where('currency_symbol', $coin_symbol[1])->first();
		// Log::info("get exchange before latest trade data".microtime(true));
		// //Log::info("balance to ".json_encode($balanceTo));
		// if(!empty($balanceTo)){
		// 	$balance_to = $balanceTo['balance'];
		// }else{
		// 	$balance_to = "0.00";
		// }
		//Log::info("balance to2 ".$balance_to);
        //$data['fee_to'] = $this->web_model->checkFees('BUY', $coin_symbol[1]);
        $coinListTo = CoinListing::select("id","coin_name","coin_image","coin_price")->where("coin_symbol","=",$coin_symbol[1])->where("status","=",1)->first();
		$coin_idTo = $coinListTo['id'];
		
		$feeto = BaseCurrency::select("*")->where("coin_id","=",$coin_idTo)->first();
		if(!empty($feeto)){
			$fee_to = ($feeto->trading_maker_fee)?$feeto->trading_maker_fee:0.00;
		}else{
			$fee_to = 0.00;
		}
		
		
		// $balanceFrom = Balance::where('user_id', $user_id)->where('currency_symbol', $coin_symbol[0])->first();
		// Log::info("get exchange before latest trade data".microtime(true));
		// if(!empty($balanceFrom)){
		// 	$balance_from = $balanceFrom['balance'];
		// }else{
		// 	$balance_from = "0.00";
		// }
		
        //$data['fee_from']       = $this->web_model->checkFees('SELL', $coin_symbol[0]);
		
		$coinList = CoinListing::select("id","coin_name","coin_image","coin_price")->where("coin_symbol","=",$coin_symbol[0])->where("status","=",1)->first();
		$coin_id = $coinList['id'];
		$coin_name = $coinList['coin_name'];
		$coin_image = ($coinList['coin_image'])?$coinList['coin_image']:"";
		$coin_price_dollar = ($coinList['coin_price'])?$coinList['coin_price']:0;
		$feeFrom = BaseCurrency::select("*")->where("coin_id","=",$coin_id)->first();
		if(!empty($feeFrom)){
			$fee_from = ($feeFrom->trading_taker_fee)?$feeFrom->trading_taker_fee:0.00;
		}else{
			$fee_from = 0.00;
		}
		
		$coinhistory=LatestTradeData::where('market_symbol', $market_symbol)->first(); 
		if($coinhistory===null){
			$coinhistory = Coinhistory::select('*')->where('market_symbol', $market_symbol)->orderBy("id","DESC")->LIMIT(1)->first();
		}
		
		//DB::enableQueryLog(); 
		
		
		
		if($coinhistory!==null){
			//DB::enableQueryLog();
			//Log::info("get exchange before latest trade data2".microtime(true));
			$coindata =	Last24hCoinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close'))->where('market_symbol', $market_symbol)->first();
			//Log::info("get exchange before latest trade data3".microtime(true));
			//$coindata =	Coinhistory::select(\DB::raw('MAX(price_high_24h) as price_high_24h,MIN(price_low_24h) as price_low_24h'))->where('date','>=',\DB::raw( 'DATE_SUB(NOW(), INTERVAL 24 HOUR)'))->where('market_symbol', $market_symbol)->first();
			//print_r($coindata);exit;
			if($coindata!==null){
				$max_open=$coindata['max_open'];
				$max_close=$coindata['max_close'];
				$min_open=$coindata['min_open'];
				$min_close=$coindata['min_close'];

				if($max_open>$max_close){
					$high_price =$max_open;
				}else{
					$high_price =$max_close;
				}
				if($min_open<$min_close){
					$low_price =$min_open;
				}else{
					$low_price =$min_close;
				}
			}else{
				
				$coindata =	Coinhistory::select('last_price')->where('market_symbol', $market_symbol)->orderBy('date','desc')->first();
				//dd(DB::getQueryLog());
				if($coindata!==null){
					$high_price = $coindata->last_price;
					$low_price = $coindata->last_price;
				}else{
					$high_price = 0;
					$low_price = 0;
				}
			}
			
			
//dd(DB::getQueryLog());
			$coin_last_price = $coinhistory['last_price'];
			$coinTo_usd_price=$coinListTo['coin_price'];
			$coin_usd_price=$coinhistory['usd_price'];
			if($coin_usd_price==0){
				$coin_usd_price=floatval($coin_last_price)*floatval($coinTo_usd_price);
			}
			//$coin_usd_price=floatval($coin_last_price)*floatval($coinTo_usd_price);
			/*$price_change_24h = $coinhistory->price_change_24h;
			$price_change_percent1 =  round(($price_change_24h/$coin_last_price)*100);
			$price_change_percent2 = ($price_change_percent1/100)*100;*/
			//Log::info("get exchange ".microtime(true).json_encode($coinhistory));
			$change=$coinhistory['change_perc'];
			$mdateArr=explode(" ", $coinhistory['date']);
            $tdate=date("Y-m-d");
            $last24hdate=date("Y-m-d H:i:s", strtotime("-24 hour"));
            if($mdateArr[0]!=$tdate){
                $change=0.00;
            }
			$price_change_percent2=$change;
			

			if($coinhistory['date']>$last24hdate){
				$total_volume = ($coinhistory['volume_24h'])?$coinhistory['volume_24h']:0;
                $volume=floatval($coin_last_price)*floatval($total_volume);
            }else{
            	$total_volume = 0.00;
                $volume=0.00;
            }
			//(Math.round(($price_change_24h/$coin_last_price)*100)/100)*100;
			
					
			
			$coinhistoryArr = array(
								"coin_price_dollar"=>number_format_six_dec_currency($coin_usd_price),
								"coin_last_price"=>number_format_eight_dec(exp2dec($coin_last_price)),
								"coin_change_price"=>number_format_two_dec($price_change_percent2),
								"price_high_24h"=>($high_price)?number_format_eight_dec(exp2dec($high_price)):0,
								"price_low_24h"=>($low_price)?number_format_eight_dec(exp2dec($low_price)):0,
								"total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0]." / ".number_format_four_dec($volume)." ".$coin_symbol[1],
								"coin_total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0],
								"basecurrency_total_volume"=>number_format_four_dec($volume)." ".$coin_symbol[1],
								);
			
		}else{
			$total_volume = 0;
			$coin_last_price = 0;
			$coinhistoryArr = array(
								"coin_price_dollar"=>number_format_four_dec($coin_price_dollar),
								"coin_last_price"=>"0.00",
								"coin_change_price"=>"0.00",
								"price_high_24h"=>"0.00",
								"price_low_24h"=>"0.00",
								"total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0]." / ".number_format_four_dec($coin_last_price)." ".$coin_symbol[1],
								"coin_total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0],
								"basecurrency_total_volume"=>number_format_four_dec($coin_last_price*$total_volume)." ".$coin_symbol[1],
							);
		}
		
		
		$exchangeArr = array(
						"buy"=>$buy,
						"sell"=>$sell,
						"base_currency"=>$coin_symbol[1],
						"pair_currency"=>$coin_symbol[0],
						"coin_name"=>$coin_name,
						"coin_image"=>$coin_image,
						//"coin_price_dollar"=>$coin_price_dollar,
						//"balance_to"=>$balance_to,
						"fee_to"=>$fee_to,
						//"balance_from"=>$balance_from, 
						"fee_from"=>$fee_from,
						"coinhistoryArr"=>$coinhistoryArr
						);
		return response()->json(["Success"=>true,'status' => 200,'Result' => $exchangeArr], 200);
		
	}	
	
	public function buy_orders()
    {
        $market_symbol = request('market');//$this->input->get('market', TRUE); 

       /* $trades = $this->db->query("SELECT *, SUM(`bid_qty_available`) as total_qty, SUM(`bid_qty_available`*`bid_price`) as total_price FROM dbt_biding WHERE `status`=2 AND `market_symbol`='".$market_symbol."'  AND `bid_type`='BUY' GROUP BY `id`,`market_symbol`, `bid_type`, `bid_price` ORDER BY `dbt_biding`.`bid_price` ASC")->result();

       echo json_encode(array('trades' => $trades));
	   */
	   
		if(!empty($market_symbol)){
		//DB::enableQueryLog();
		   $trades = Biding::select("*",DB::raw('SUM(bid_qty_available) as total_qty'),DB::raw(		'SUM(`bid_qty_available`*`bid_price`) as total_price'))
			->where("status","=",2)
			->where("market_symbol","=",$market_symbol)
			->where("bid_type","=","BUY")
			->groupBy('bid_price')
			->orderBy('bid_price','desc')
			->limit(15)
			->get();
			//dd(DB::getQueryLog());
			//print_r($trades);exit;
			$tradesArr = array();
			$bqty=array();
			if(@count($trades)){
				foreach($trades as $res){
					//$tradesArr[] = $res;
					$bqty[$res->bid_price.""]=floatval(@$bqty[$res->bid_price.""])+floatval($res->total_qty);
					$tradesArr[$res->bid_price.""] = array(
									"id"=>$res->id,
									"bid_price"=>number_format_eight_dec($res->bid_price),
									//"r_bid_price"=>$res->bid_price,
									"total_qty"=>number_format_eight_dec($bqty[$res->bid_price.""]),
									"total_price"=>number_format_eight_dec(floatval($res->bid_price)*floatval($bqty[$res->bid_price.""])),
									);
				}	
			}
			$tradArr=array_values($tradesArr);
			//usort($tradArr, "bidprice_sort");
		   //echo json_encode(array('trades' => $trades));
			return response()->json(["Success"=>true,'status' => 200,'Result' => $tradArr], 200);
		}else{
			return response()->json(["Success"=>false,'status' => 422,'Result' => "Market Request is missing"], 200);	
		}
	

    }


    public function sell_orders()
    {
        $market_symbol = request('market'); //$this->input->get('market', TRUE);

        /*$trades = $this->db->query("SELECT *, SUM(`bid_qty_available`) as total_qty, SUM(`bid_qty_available`*`bid_price`) as total_price FROM dbt_biding WHERE `status`=2 AND `market_symbol`='".$market_symbol."' AND `bid_type`='SELL' GROUP BY `id`,`market_symbol`, `bid_type`, `bid_price` ORDER BY `dbt_biding`.`bid_price` DESC")->result();
		*/
		if(!empty($market_symbol)){
			$trades = Biding::select("*",DB::raw('SUM(bid_qty_available) as total_qty'),DB::raw(		'SUM(`bid_qty_available`*`bid_price`) as total_price'))
			->where("status","=",2)
			->where("market_symbol","=",$market_symbol)
			->where("bid_type","=","SELL")
			->groupBy('bid_price')
			->limit(15)
			->get();
			//print_r($trades);exit;
			$tradesArr = array();
			$sqty=array();
			if(@count($trades)){
				foreach($trades as $res){
					//$tradesArr[] = $res;
					$sqty[$res->bid_price.""]=floatval(@$sqty[$res->bid_price.""])+floatval($res->total_qty);
					$tradesArr[$res->bid_price.""] = array(
									"id"=>$res->id,
									"bid_price"=>number_format_eight_dec(exp2dec($res->bid_price)),
									"total_qty"=>number_format_eight_dec($sqty[$res->bid_price.""]),
									"total_price"=>number_format_eight_dec(floatval($res->bid_price)*floatval($sqty[$res->bid_price.""])),
									);
				}	
			}
			$tradArr=array_values($tradesArr);
		   //echo json_encode(array('trades' => $trades));
			return response()->json(["Success"=>true,'status' => 200,'Result' => $tradArr], 200);
		}else{
			return response()->json(["Success"=>false,'status' => 422,'Result' => "Market Request is missing"], 200);	
		}
		
		
    }
	
	
	
	
	// trading History
	
	public function tradehistory()
    {

        $market_symbol = request('market');
		if(!empty($market_symbol)){
			$coin_symbol   = explode('_', $market_symbol);
			//$today = date("Y-m-d");
			//$todate = Carbon::parse($today)->format('Y-m-d H:i:s');
			//echo $todate;exit;
			
			//$tradehistory_info =  BidingLog::select("*")->where('market_symbol', $market_symbol)->where('status',1)->where('trade_history_status',1)->orderBy("log_id","DESC")->limit(100)->get();//$this->web_model->tradeHistory($market_symbol);
			$tradehistory_info =  RecentBidingLog::select("*")->where('market_symbol', $market_symbol)->where('status',1)->where('trade_history_status',1)->orderBy("log_id","DESC")->limit(100)->get();//$this->web_model->tradeHistory($market_symbol);
			$tradehistory =array();
			if(@count($tradehistory_info) > 0){
				foreach($tradehistory_info as $res){
					$tradehistory[] = array(
										"date"=>date("H:i:s",strtotime($res->success_time)),
										"type"=>$res->bid_type,
										"amount"=>number_format_eight_dec($res->complete_qty),
										"price"=>number_format_eight_dec($res->bid_price),
										);	
				}
			}
			
			
		   
			$coinhistoryArr = array();
			//self::trandeHistoryPublish($tradehistory,$coinhistoryArr);
			return response()->json(["Success"=>true,'status' => 200,'Result' => $tradehistory,'coinhistory' => $coinhistoryArr], 200);
		}else{
			return response()->json(["Success"=>false,'status' => 422,'Result' => "Request Parameter is missing"], 200);
		}
		
    }
    public static function tradehistoryForPublish($market_symbol)
    {
    	//Log::info("tradeHistory publish");
       	$queue = 'tradehistory_queue';
		$exchange = 'router';
		$rabitmqData = json_encode(array("queue_type"=>"tradehistory","market_symbol"=>$market_symbol));
		//Log::info("trade history publish data");
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
		
    }
    
	
	// orders list
	
	public function ordersList(Request $request)
    {

        $data = $request->user();
		$user_id = $data['user_id'];
		
		$market_symbol = request('market');//$this->input->get('market', TRUE);
		$changed_market_symbol = str_replace("_", " / ", $market_symbol);
		if(!empty($market_symbol)){
		
		   $ordersList = Biding::select('*')
								->where("status","=", 2)
								->where("user_id","=", $user_id)
								->where("market_symbol","=",$market_symbol)->orderBy('id','desc')->limit(50)
								->get();
								
			//print_r($ordersList);exit;
			$ordersListArr = array();
			if(@count($ordersList)){ 
				foreach($ordersList as $value){
					$ordersListArr[] = array(
										"id"=>$value->id,
										"bid_type"=>$value->bid_type,
										"bid_price"=>number_format_eight_dec(exp2dec($value->bid_price)),
										"bid_qty"=>$value->bid_qty,
										"order_date"=>date("d-m-Y H:i:s",strtotime($value->open_order)),
										"pair"=>$changed_market_symbol,
										"amount"=>number_format_eight_dec(exp2dec($value->bid_qty_available)),
										"price"=>number_format_eight_dec(exp2dec($value->total_amount)),
										"balance"=>number_format_eight_dec(exp2dec($value->amount_available)),
										"status"=>"In Orders",
										"cancel"=>"Cancel",
										);	
				}	
			}

		   return response()->json(["Success"=>true,'status' => 200,'Result' => $ordersListArr], 200);
		}else{
			return response()->json(["Success"=>false,'status' => 422,'Result' => "Market Request is missing"], 400);	
		}
	

    }
	
	// orders History
	
	public function ordersHistory(Request $request)
    {
        $data = $request->user();
		$user_id = $data['user_id'];
		
		$market_symbol = request('market');//$this->input->get('market', TRUE);
		$changed_market_symbol = str_replace("_", " / ", $market_symbol);

		if(!empty($market_symbol)){
		
		  	//DB::enableQueryLog(); 
			/*$ordersList = DB::table('executed_orders as bidmaster')
					   ->leftJoin('recent_biding_log as biddetail', 'biddetail.bid_id', '=', 'bidmaster.exchange_id')
					  	->select('bidmaster.id','bidmaster.bid_type','bidmaster.bid_price','bidmaster.bid_qty','bidmaster.market_symbol','bidmaster.bid_qty_available','bidmaster.total_amount','bidmaster.amount_available','biddetail.complete_qty', 'biddetail.complete_amount', 'biddetail.success_time', 'biddetail.status')
					   ->where("bidmaster.user_id","=", $user_id)
						->where("biddetail.market_symbol","=",$market_symbol)->orderBy('biddetail.success_time','desc')->limit(100)
					   ->get();	*/
			$ordersList = RecentBidingLog::select("*")->where('market_symbol', $market_symbol)->where('user_id',$user_id)->orderBy("success_time","DESC")->limit(100)->get();
			//dd(DB::getQueryLog());				
			//print_r($ordersList);exit;
			$ordersListArr = array();
			if(@count($ordersList)){ 
				foreach($ordersList as $value){
					$statusMsg = "";
					
					if($value->status==0){
						$statusMsg = "Canceled";
					}else if($value->status==1){
						$statusMsg = "Executed";
					}else{
						$statusMsg = "Running";
						
					}
					if($value->fees_amount == '0.00'){
						$fee = '0.00';
					}else{
						$fee = number_format_eight_dec(exp2dec($value->fees_amount))." ".$value->fee_deducted_wallet;
					}
					$ordersListArr[] = array(
										"id"=>$value->id,
										"bid_type"=>$value->bid_type,
										"bid_price"=>number_format_eight_dec($value->bid_price),
										"bid_qty"=>number_format_eight_dec($value->complete_qty),
										"order_date"=>date("d-m-Y H:i:s",strtotime($value->success_time)),
										"pair"=>$changed_market_symbol,
										"amount"=>number_format_eight_dec(exp2dec($value->complete_qty)),
										"fee"=>$fee,
										"price"=>number_format_eight_dec(exp2dec($value->complete_qty)),
										"balance"=>number_format_eight_dec(exp2dec($value->complete_amount)),
										"status"=>$statusMsg,
										
										);	
				}	
			}

		   return response()->json(["Success"=>true,'status' => 200,'Result' => $ordersListArr], 200);
		}else{
			return response()->json(["Success"=>false,'status' => 422,'Result' => "Market Request is missing"], 200);	
		}
	

    }
	
	
	// Order Cancel
	public function order_cancel(Request $request)
    {
    	//Log::info("order cancel start");
        $data = $request->user();
		$user_id = $data['user_id'];
		$validator = Validator::make($request->all(), [
			'market' => 'required',
			'order_id' => 'required',
		]);
		if ($validator->fails()) {
			return response()->json(['status' => false, 'Result' => $validator->errors()], 200);
		}else{
			$market_symbol = request('market');//$this->input->get('market', TRUE);
			$bid_id = request('order_id');
			DB::beginTransaction();
			$orderdata = Biding::select('*')->where('status',2)->where('id', $bid_id)->sharedLock()->first();
			if(!empty($orderdata)){
				$newbuy_arr=array(
            		"exchange_id"=>$orderdata->id,
            		"bid_type"=>$orderdata->bid_type,
            		"bid_price"=>$orderdata->bid_price,
            		"bid_qty" => $orderdata->bid_qty,
            		"bid_qty_available"=>$orderdata->bid_qty_available,
            		"total_amount"=>$orderdata->total_amount,
            		"amount_available"=>$orderdata->amount_available,
            		"currency_symbol"=>$orderdata->currency_symbol,
            		"market_symbol"=>$orderdata->market_symbol,
            		"user_id"=>$orderdata->user_id,
            		"open_order"=>$orderdata->open_order,
            		"fees_amount"=>$orderdata->fees_amount,
            		"fee_deducted_wallet"=>$orderdata->fee_deducted_wallet,
            		"payment_fee_mode"=>$orderdata->payment_fee_mode,
            		"fee_perc"=>$orderdata->fee_perc,
            		"bc_usd_price"=>$orderdata->bc_usd_price,
            		"status"=>0,
            		"created_at"=>date("Y-m-d H:i:s",strtotime($orderdata->created_at)),
            		"updated_at"=>date("Y-m-d H:i:s")
            	);
            	//Log::info("buy_exchange_query loop starteddddbbb ".json_encode($newbuy_arr));
            	ExecutedOrders::insert($newbuy_arr);
            	Biding::where('id', $orderdata->id)->delete();

            	//order status updaed in fee deductions table
            	$updata =array(
            		"status"=>0,
            		"updated_at"=>date("Y-m-d H:i:s")
            	);
            	FeeDeductions::where('bid_id', $orderdata->id)->update($updata);

				//$canceltrade = array('status' => 0);
				//Biding::where('id',$bid_id)->update($canceltrade);
				
				$currency_symbol = '';
				$refund_amount = '';
				$temp = explode("_", $orderdata->market_symbol);
				if ($orderdata->bid_type == 'SELL') {
					$currency_symbol = $temp[0];
					// $refund_amount = $orderdata->bid_qty_available;
					
					//With fees refund
					$percent = (($orderdata->bid_qty-$orderdata->bid_qty_available)*100)/$orderdata->bid_qty;
					$per_pending = 100 - $percent;
					$return_fees = ($per_pending*$orderdata->fees_amount)/100;
					
					
					//$refund_amount = ($orderdata->bid_qty_available*$orderdata->bid_price) + $return_fees;
					$refund_amount = $orderdata->bid_qty_available;
					
					/*$return_fees = $orderdata->bid_price*$orderdata->fees_amount/100;
					$refund_amount = ($orderdata->bid_qty_available*$orderdata->bid_price) + $return_fees;
					*/
					
					if($orderdata->payment_fee_mode == 1){
						$query2 = "UPDATE dbt_balance SET balance=(balance+$return_fees) WHERE user_id='" . $orderdata->user_id . "' AND  currency_symbol = 'EVR' ";
						DB::statement($query2);
					}
					$query2 = "UPDATE dbt_balance SET balance=(balance+$refund_amount) WHERE user_id='" . $orderdata->user_id . "' AND  currency_symbol = '" . $currency_symbol . "' ";
						DB::statement($query2);	

				}else{
					
					$currency_symbol = $temp[1];

					$percent     = (($orderdata->bid_qty-$orderdata->bid_qty_available)*$orderdata->bid_price*100)/($orderdata->bid_qty*$orderdata->bid_price);
					$per_pending = 100 - $percent;
					$return_fees = ($per_pending*$orderdata->fees_amount)/100;

					//With fees refund
					$refund_amount = ($orderdata->bid_qty_available*$orderdata->bid_price);
					$refund_amount_withfee = ($orderdata->bid_qty_available*$orderdata->bid_price) + $return_fees;

					if($orderdata->payment_fee_mode == 1){
						$query1 = "UPDATE dbt_balance SET balance=(balance+$return_fees) WHERE user_id='" . $orderdata->user_id . "' AND  currency_symbol = 'EVR' ";
						DB::statement($query1);
						$query2 = "UPDATE dbt_balance SET balance=(balance+$refund_amount) WHERE user_id='" . $orderdata->user_id . "' AND  currency_symbol = '" . $currency_symbol . "' ";
						DB::statement($query2);	
					}else{
						$query2 = "UPDATE dbt_balance SET balance=(balance+$refund_amount_withfee) WHERE user_id='" . $orderdata->user_id . "' AND  currency_symbol = '" . $currency_symbol . "' ";
						DB::statement($query2);		
					}

				}
				
				$balance = self::checkBalance($orderdata->user_id,$currency_symbol);
				$tradecanceldata = array(
						'user_id'            => $orderdata->user_id,
						'balance_id'         => @$balance->id,
						'currency_symbol'    => $currency_symbol,
						'transaction_type'   => 'TRADE_CANCEL',
						'transaction_amount' => $refund_amount,
						'transaction_fees'   => $return_fees,
						'ip'                 => \Request::getClientIp(true),
						'date'               => date('Y-m-d H:i:s')
					);
				BalanceLog::insert($tradecanceldata);
				
				//$new_balance = @$balance->balance+($refund_amount);
				
				//$this->db->set('balance', $new_balance)->where('user_id', $orderdata->user_id)->where('currency_symbol', $currency_symbol)->update("dbt_balance");
				
				
				//Balance::where('user_id', $orderdata->user_id)->where('currency_symbol', $currency_symbol)->increment('balance', $new_balance);
				
				// $query2 = "UPDATE dbt_balance SET balance=(balance+$refund_amount) WHERE user_id='" . $orderdata->user_id . "' AND  currency_symbol = '" . $currency_symbol . "' ";
				// DB::statement($query2);
				

				$traderlog = array(
					"bid_id"=>$bid_id,
					'bid_type'        => $orderdata->bid_type,
					'complete_qty'    => $orderdata->bid_qty_available,
					'bid_price'       => $orderdata->bid_price,
					'complete_amount' => $refund_amount,
					'user_id'         => $orderdata->user_id,
					'currency_symbol' => $orderdata->currency_symbol,
					'market_symbol'   => $orderdata->market_symbol,
					'success_time'    => date('Y-m-d H:i:s'),
					'fees_amount'     => $return_fees,
					'fee_deducted_wallet'=> $orderdata->fee_deducted_wallet,
					'available_amount'=> 0,
					'status'          => 0,
				);

				//$this->db->insert('dbt_biding_log', $traderlog);
				BidingLog::insert($traderlog);
				DB::commit();
				//Log::info("order cancel after biding log");
				if($orderdata->bid_type=='SELL'){
					/*$rabitmqData = json_encode(array("exchange_id"=>$bid_id,"user_id"=>$user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$temp,"queue_type"=>"sellorders"));
					Log::info("sell publish data");
					RabbitmqController::publish_channel('sellorders_queue',$rabitmqData,'router');*/
					$message=json_encode(array("market_symbol"=>$market_symbol));
					$msg=json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id));
					RabbitmqController::sellorders($message);
					//Log::info("order cancel after cell orders");
					RabbitmqController::balance_broadcast($msg);
				    //Log::info("order cancel after balance");
				    RabbitmqController::orderslist_broadcast($msg);
				    //Log::info("order cancel after order list");
				    RabbitmqController::ordershistory_broadcast($msg);
				}else{
					/*$rabitmqData = json_encode(array("exchange_id"=>$bid_id,"user_id"=>$user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$temp,"queue_type"=>"buyorders"));
					Log::info("buy publish data");
					RabbitmqController::publish_channel('buyorders_queue',$rabitmqData,'router');*/
					$message=json_encode(array("market_symbol"=>$market_symbol));
					$msg=json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id));
					RabbitmqController::buyorders($message);
			      	
			      	RabbitmqController::balance_broadcast($msg);
				    
				    RabbitmqController::orderslist_broadcast($msg);
				    RabbitmqController::ordershistory_broadcast($msg);
				}
				
				//Log::info("order cancel end");
				return response()->json(["Success"=>true,'status' => 200,'Result' => "Order Canceled Successfully "], 200);
				
			}else{
				DB::commit();
				$message=json_encode(array("market_symbol"=>$market_symbol));
				$msg=json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id));
				RabbitmqController::orderslist_broadcast($msg);
				return response()->json(["Success"=>false,'status' => 422,'Result' => "There is no order for cancel"], 200);
			}
			
		}
	

    }
	
	
	// get Fees
	public static function getFees($type,$coin_symbol){
	
		$coinList = CoinListing::select("id","coin_name","coin_image")->where("coin_symbol","=",$coin_symbol)->where("status","=",1)->first();
		$coin_id = $coinList['id'];
		
		$feeto = BaseCurrency::select("*")->where("coin_id","=",$coin_id)->first();
		if($feeto!==null){
			if($type=="BUY"){
				$fees = $feeto->trading_maker_fee;	
			}else if($type=="SELL"){
				$fees = $feeto->trading_maker_fee;	
			}else{
				$fees = "0.00";
			}
		}else{
			$fees = "0.00";
		}
		
		return $fees;
	}
	//check balance
	public static function checkBalance($user_id,$coin_symbol){
	
		$balanceFrom = Balance::where('user_id', $user_id)->where('currency_symbol', $coin_symbol)->sharedLock()->first();
		
		return $balanceFrom;
	}
	
	// debit balance
	public static function balanceDebit($data = array(), $coin_symbol)
	{
		$check_user_balance = Balance::where('user_id', $data->user_id)->where('currency_symbol', $coin_symbol)->first();
		
		$updatebalance = array(
            'balance'     => @$check_user_balance->balance-(@$data->total_amount+@$data->fees_amount),
        );

        
		$res = Balance::where('user_id',$data->user_id)->where('currency_symbol', $coin_symbol)->update($updatebalance);
		return $res;
		
	}
	
	//Return Balance
	public static function balanceReturn($data = array()){

		$balance = Balance::where('user_id', $data['user_id'])->where('currency_symbol',$data['currency_symbol'])->first();

		$updatebalance = array(
            'balance'     => $balance->balance+$data['amount'],
        );

        Balance::where('user_id', $data['user_id'])->where('currency_symbol',$data['currency_symbol'])->update($updatebalance);

        $balance1 = Balance::where('user_id', $data['user_id'])->where('currency_symbol',$data['fee_deducted_wallet'])->first();

        $updbalance = array(
            'balance'     => $balance1->balance+$data['return_fees'],
        );

        Balance::where('user_id', $data['user_id'])->where('currency_symbol',$data['fee_deducted_wallet'])->update($updbalance);

        $logdata = array(
        	'balance_id'		=> $balance->id,
        	'user_id' 			=> $data['user_id'],
        	'currency_symbol'	=> $data['currency_symbol'],
        	'transaction_type' 	=> 'ADJUSTMENT',
        	'transaction_amount'=> $data['amount'],
        	'transaction_fees'	=> $data['return_fees'],
        	'ip' 				=> $data['ip'],
        	'date'				=> date('Y-m-d H:i:s')
        );
		
        BalanceLog::insert($logdata);

	}
	
	public function buy(Request $request)
	{ 
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$validator = Validator::make($request->all(), [
			'market' => 'required',
			'buypricing' => 'required',
			'buyamount' => 'required',
			
		]);
		if ($validator->fails()) {
			return response()->json(['status' => false, 'Result' => $validator->errors()], 200);
		}
		
        if ($user_id){
			
            $coin_symbol        = explode('_', request('market'));
            $market_symbol      = request('market');
            $rate               = request('buypricing'); //$this->input->post('buypricing', TRUE);
            $qty                = request('buyamount'); //$this->input->post('buyamount', TRUE);
           // $user_id            = $this->session->userdata('user_id');
            // $fees_amount         = $this->input->post('buyfeesval');

            $amount_withoutfees = $rate * $qty;

            $userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
            //check role setings and payment of fee mode
            $bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
            $roleSettings = RolesSettings::select('*')->where("role","=",$data['role'])->first();
            if($roleSettings->fee_applicable == 1){
            	//Check BUY fees            	
	            if($userData->payment_fee_mode == 1){
	            	$fees = self::getFees('BUY', 'EVR'); 
	            	if ($fees) {
		                //$fees_amount = ($rate*$qty*$fees)/100;
						$fee_amt = ($amount_withoutfees*$fees)/100;						
						$evr_usd_price = CoinListing::select('coin_price')->where('coin_symbol','EVR')->first();
						$fees_amount = $fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
		                $buyfees     = $fees;
		            }else{
		                $fees_amount = 0;
		                $buyfees     = 0;
		            }
	            }else{
	            	$fees = self::getFees('BUY', $coin_symbol[1]); 
	            	if ($fees) {
		                //$fees_amount = ($rate*$qty*$fees)/100;
						$fees_amount = ($amount_withoutfees*$fees)/100;
		                $buyfees     = $fees;
		            }else{
		                $fees_amount = 0;
		                $buyfees     = 0;
		            }
	            }
	            
            }else{
            	$fees_amount = 0;
	            $buyfees     = 0;
            }          
	    //$pdo = DB::connection()->getPdo();
	    //$pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
	    DB::beginTransaction();
            //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
            $balance_c0         = self::checkBalance($user_id,$coin_symbol[0]);
            $balance_c1         = self::checkBalance($user_id,$coin_symbol[1]);
            $evr_balance         = self::checkBalance($user_id,'EVR');


            $real_balance = (float)@$balance_c1->balance;
            $everus_balance = (float)@$evr_balance->balance;
            if($userData->payment_fee_mode == 1){            	
            	$amount_withfees = $amount_withoutfees;
            	$fee_deducted_wallet = 'EVR';
            	if($everus_balance >= $fees_amount && @$evr_balance->balance>0){
            		//true
            	}else{
            		return response()->json(['status' => false, 'Result' => "Insufficient Fee In EVR Wallet."], 200);
            	}
            	
            }else{        
            	$amount_withfees = $amount_withoutfees + $fees_amount;  
            	$fee_deducted_wallet = $coin_symbol[1];  	
            	if ($real_balance >= $amount_withfees && @$balance_c1->balance>0 && $amount_withfees>0) {
            		//true
            	}else{
            		return response()->json(['status' => false, 'Result' => "Insufficient Balance"], 200);
            	}
            }

            if ($real_balance >= $amount_withfees && @$balance_c1->balance>0 && $amount_withfees>0) {
            	$rate=number_format_eight_dec($rate);
            	$rate=str_replace(",", "", $rate);
                //$date       = new DateTime();
                $open_date  = date('Y-m-d H:i:s');
                $trade_id="TR".rand(100,10000).time();
                $tdata['TRADES']   = (object)$exchangedata = array(
                	//'trade_id'=>$trade_id,
                    'bid_type'          => 'BUY',
                    'bid_price'         => $rate,
                    'bid_qty'           => $qty,
                    'bid_qty_available' => $qty,
                    'total_amount'      => $amount_withoutfees,
                    'amount_available'  => $amount_withoutfees,
                    'currency_symbol'   => $coin_symbol[0],
                    'market_symbol'     => $market_symbol,
                    'user_id'           => $user_id,
                    'open_order'        => $open_date,
                    'fees_amount'       => $fees_amount,//insert fee deducted from wallet                    
                    'fee_deducted_wallet' => $fee_deducted_wallet,
                    'payment_fee_mode' => $userData->payment_fee_mode,
                    'fee_perc'       => $buyfees,
                    'bc_usd_price'		=>$bc_usd_price->coin_price,
                    'status'            => 2
                );
				$info = Biding::create($exchangedata);
				$exchange_id = $info->id;
                //Exchange Data Insert
                if ($exchange_id ) {

                    $last_exchange = Biding::where("id","=",$exchange_id)->first(); //$this->web_model->single($exchange_id);

                    //User Balance Debit(-) C1
                    //self::balanceDebit($last_exchange, $coin_symbol[1]);
                    //Log::info("pushing into log");
                    Log::info("buy order placing Fee ".$fees_amount);
                    Log::info("buy order evr balance".$evr_balance->balance);
                    if($userData->payment_fee_mode == 1){ 
                    	$updatebalance = array(
				            'balance' => @$evr_balance->balance-$fees_amount,
				        );
				        Log::info("buy order balance condition1 ".json_encode($updatebalance));
				        $res = Balance::where('user_id',$user_id)->where('currency_symbol', 'EVR')->update($updatebalance);
				        
				        $balance_c1 = self::checkBalance($user_id,$coin_symbol[1]);
                    	$updatebalance1 = array(
				            'balance' => @$balance_c1->balance-$amount_withoutfees,
				        );
				        Log::info("buy order balance condition2 ".json_encode($updatebalance1));
				        $res = Balance::where('user_id',$user_id)->where('currency_symbol', $coin_symbol[1])->update($updatebalance1);
                    }
                    else{
                    	$amount_withfees = $amount_withoutfees + $fees_amount;
                    	$updatebalance = array(
				            'balance' => @$balance_c1->balance-$amount_withfees,
				        );
				        Log::info("buy order balance condition3 ".json_encode($updatebalance));
				        $res = Balance::where('user_id',$user_id)->where('currency_symbol', $coin_symbol[1])->update($updatebalance);
                    }
                   //BAlacnce deductions end
                    //Fee dedudtion info table
                    if($fees_amount > 0){
                    	$usd_price = CoinListing::select('coin_price')->where('coin_symbol',$fee_deducted_wallet)->first();
                    	$fee_in_usd = $fees_amount*$usd_price->coin_price;
                    	$bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
                    	$total_amount_usd = $amount_withoutfees*$bc_usd_price->coin_price;
	                    $fdata = array(
	                	'user_id' => $user_id,
	                    'bid_id' => $exchange_id,
	                    'bid_type' => 'BUY',
	                    'total_amount' => $amount_withoutfees,
	                    'total_amount_usd' => $total_amount_usd,
	                    'paid_date' => $open_date,
	                    'fee_percentage' => $buyfees,
	                    'fee_amount' => $fees_amount,
	                    'fee_in_usd' => $fee_in_usd,
	                    'currency' => $fee_deducted_wallet,
	                    'status'  => 2
	                );
						FeeDeductions::create($fdata);
					}
                    
					$rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
					//Log::info("buy publish data ".$exchange_id);
					DB::commit();
					RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');
					//Log::info("buy publish data ".$exchange_id);
					/*RabbitmqController::buyorders(json_encode(array("market_symbol"=>$market_symbol)));
					RabbitmqController::orderslist_broadcast(json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id)));*/
					echo json_encode(array("Success"=>true,'status' => 200,'Result'=>''));
					//self::BuyTradeMatchingEngine($exchange_id,$user_id,$market_symbol,$coin_symbol);
                   
                    
                }else{
                    //echo 0;
                    //trade not submited
					echo json_encode(array("Success"=>false,'status' => 422,'Result'=>"trade not submitted"));
                }

            }else{
                //echo 2;
                //Insufficent Balance
				echo json_encode(array("Success"=>false,'status' => 422,'Result'=>"Insufficient Balance"));
            }
            DB::commit();
        } else {
            //echo 1;
			echo json_encode(array("Success"=>false,'status' => 422,'Result'=>"User not found"));
            //login requred

        }
	}
	
	
	
	
	public static function BuyTradeMatchingEngine($exchange_id,$user_id,$market_symbol,$coin_symbol){
		Log::info("BuyTradeMatchingEngine function started ".$exchange_id);
		$open_date  = date('Y-m-d H:i:s');
		$last_exchange   =   Biding::where("id","=",$exchange_id)->first();
         $rate = $last_exchange->bid_price;
//         Log::info("BuyTradeMatchingEngine 1 ".json_encode($last_exchange));
		//Check BUY fees
		// $fees = self::getFees('BUY', $coin_symbol[1]);  
		// //Log::info("BuyTradeMatchingEngine executed getfees() ");
		// if ($fees) {
		// 	//$fees_amount = ($rate*$qty*$fees)/100;
		// 	$fees_amount = ($rate*$fees)/100;
		// 	$buyfees     = $fees;

		// }else{
		// 	$fees_amount = 0;
		// 	$buyfees     = 0;

		// }

		//SELL fees
		// $sellerfees = self::getFees('SELL', $coin_symbol[0]);
		// if ($sellerfees) {
		// 	$sellfees = $sellerfees;

		// }else{
		// 	$sellfees = 0;

		// }
		
		 //After balance discut(-)
                    $balance = self::checkBalance($user_id,$coin_symbol[1]);
					 //Log::info("BuyTradeMatchingEngine 2 ");
					$where = ['status' => 2, 'bid_type' => 'SELL','market_symbol'=>$market_symbol ];
                    $sell_exchange_query = Biding::select('*')->where("bid_price","<=",$rate)->where($where)->where("id","<",$exchange_id)->orderBy('bid_price', 'asc')->get();
					 //Log::info("BuyTradeMatchingEngine 3 ".json_encode($sell_exchange_query));
					$match=0;
					//$aftermatch=0;
                    //Check if any order availabe
                    Log::info("BuyTradeMatchingEngine 4 ".@count($sell_exchange_query));
                    $ordersArr=array();
                    if (@count($sell_exchange_query) >0 ) {
						$match=1;
						
                        foreach ($sell_exchange_query as $key => $sellexchange) { 
                            $seller_available_qty       = 0;
                            $buyer_available_qty        = 0;
                            $buyer_amount_available     = 0;
                            $seller_amount_available    = 0;
                            $seller_complete_qty_log    = 0;
                            $buyer_complete_qty_log     = 0;
                            $buyer_amount_available_log = 0;
                            $seller_complete_qty_log    = 0;

                            //Log::info("buy matcheninge2 ");
							$last_exchange   =   Biding::where("id","=",$exchange_id)->first();
							//$this->web_model->single($exchange_id);
							if($last_exchange!==null){
                            if ($last_exchange->status==2) {
								
                                //Seller+Buyer Quantity/Amount Complete/Available Master table
                                $seller_available_qty   = (($sellexchange->bid_qty_available-$last_exchange->bid_qty_available)<0)?0:$sellexchange->bid_qty_available-$last_exchange->bid_qty_available;
                                $buyer_available_qty   = (($last_exchange->bid_qty_available-$sellexchange->bid_qty_available)<=0)?0:($last_exchange->bid_qty_available-$sellexchange->bid_qty_available);

                                $buyer_amount_available = ($last_exchange->amount_available-($sellexchange->bid_qty_available*$sellexchange->bid_price)<=0)?0:($last_exchange->amount_available-($sellexchange->bid_qty_available*$sellexchange->bid_price));
                                $seller_amount_available = ((($sellexchange->bid_qty_available-$last_exchange->bid_qty_available)<0)?0:$sellexchange->bid_qty_available-$last_exchange->bid_qty_available)*$sellexchange->bid_price;


                                // Seller+Buyer Quantity Complete log table
                                

                                $buyer_amount_available_log = ($last_exchange->amount_available-($last_exchange->bid_qty_available*$sellexchange->bid_price)<=0)?0:($last_exchange->amount_available-($last_exchange->bid_qty_available*$sellexchange->bid_price));
                                $buyer_complete_qty_log = (($sellexchange->bid_qty_available-$last_exchange->bid_qty_available)==0)?$last_exchange->bid_qty_available:((($sellexchange->bid_qty_available-$last_exchange->bid_qty_available)<=0)?$sellexchange->bid_qty_available:$last_exchange->bid_qty_available);

                                $seller_complete_qty_log = $buyer_complete_qty_log;
                                //Log::info("buy matcheninge3 ");
                                if(($last_exchange->bid_qty_available-$sellexchange->bid_qty_available)<=0){
                                	$newbuy_arr=array(
                                		"exchange_id"=>$exchange_id,
                                		"bid_type"=>$last_exchange->bid_type,
                                		"bid_price"=>$last_exchange->bid_price,
                                		"bid_qty" => $last_exchange->bid_qty,
                                		"bid_qty_available"=>$buyer_available_qty,
                                		"total_amount"=>$last_exchange->total_amount,
                                		"amount_available"=>$buyer_amount_available,
                                		"currency_symbol"=>$last_exchange->currency_symbol,
                                		"market_symbol"=>$last_exchange->market_symbol,
                                		"user_id"=>$last_exchange->user_id,
                                		"open_order"=>$last_exchange->open_order,
                                		"fees_amount"=>$last_exchange->fees_amount,
                                		"fee_deducted_wallet"=>$last_exchange->fee_deducted_wallet,
                                		"payment_fee_mode"=>$last_exchange->payment_fee_mode,
                                		"fee_perc"=>$last_exchange->fee_perc,
                                		"bc_usd_price"=>$last_exchange->bc_usd_price,
                                		"status"=>1,
                                		"created_at"=>$last_exchange->created_at,
                                		"updated_at"=>$last_exchange->updated_at,
                                	);
                                	ExecutedOrders::insert($newbuy_arr);
                                	Biding::where('id', $exchange_id)->delete();
                                	//order status updaed in fee deductions table
					            	$updata =array(
					            		"status"=>1,
					            		"order_completed_at"=>date("Y-m-d H:i:s"),
					            		"updated_at"=>date("Y-m-d H:i:s")
					            	);
					            	FeeDeductions::where('bid_id', $exchange_id)->update($updata);
                                }else{
                                	$exchangebuydata = array(
	                                    'bid_qty_available'  => $buyer_available_qty,
	                                    'amount_available'   => $buyer_amount_available, //Balance added buy account
	                                    'status'             => 2,
	                                );
	                                Biding::where('id', $exchange_id)->update($exchangebuydata);
                                }
                                Log::info("buy matcheninge4 ");
                                if(($sellexchange->bid_qty_available-$last_exchange->bid_qty_available)<=0){
                                	$newsell_arr=array(
                                		"exchange_id"=>$sellexchange->id,
                                		"bid_type"=>$sellexchange->bid_type,
                                		"bid_price"=>$sellexchange->bid_price,
                                		"bid_qty" => $sellexchange->bid_qty,
                                		"bid_qty_available"=>$seller_available_qty,
                                		"total_amount"=>$sellexchange->total_amount,
                                		"amount_available"=>$seller_amount_available,
                                		"currency_symbol"=>$sellexchange->currency_symbol,
                                		"market_symbol"=>$sellexchange->market_symbol,
                                		"user_id"=>$sellexchange->user_id,
                                		"open_order"=>$sellexchange->open_order,
                                		"fees_amount"=>$sellexchange->fees_amount,
                                		"fee_deducted_wallet"=>$sellexchange->fee_deducted_wallet,
                                		"payment_fee_mode"=>$sellexchange->payment_fee_mode,
                                		"fee_perc"=>$sellexchange->fee_perc,
                                		"bc_usd_price"=>$sellexchange->bc_usd_price,
                                		"status"=>1,
                                		"created_at"=>$sellexchange->created_at,
                                		"updated_at"=>$sellexchange->updated_at,
                                	);
                                	ExecutedOrders::insert($newsell_arr);
                                	Biding::where('id', $sellexchange->id)->delete(); 
                                	//order status updaed in fee deductions table
					            	$updata =array(
					            		"status"=>1,
					            		"order_completed_at"=>date("Y-m-d H:i:s"),
					            		"updated_at"=>date("Y-m-d H:i:s")
					            	);
					            	FeeDeductions::where('bid_id', $exchange_id)->update($updata);                               	
                                }else{
                                	$exchangeselldata = array(
	                                    'bid_qty_available'  => $seller_available_qty,
	                                    'amount_available'   => $seller_amount_available, //Balance added buy account
	                                    'status'             => 2,
	                                );
	                                Biding::where('id', $sellexchange->id)->update($exchangeselldata);
                                }

                                //Log::info("buy matcheninge5 ");
                                //Adjustment Amount+Fees
                                if($last_exchange->bid_price>$sellexchange->bid_price){

                                    $totalexchanceqty = $buyer_complete_qty_log;

                                    $buyremeaningrate = $last_exchange->bid_price-$sellexchange->bid_price;
                                    $buyerbalence     = $buyremeaningrate*$totalexchanceqty;

                                    //Fees when Adjustment
                                    $returnfees     = 0;
                                    // $byerfees       = ($totalexchanceqty*$last_exchange->bid_price*$buyfees)/100;
                                    // $sellerrfees    = ($totalexchanceqty*$sellexchange->bid_price*$sellfees)/100;
                                    // $buyerreturnfees= $byerfees-$sellerrfees;

                                    $buyfees = $last_exchange->fee_perc;
                                    //$buyerreturnfees = $buyerbalence*$buyfees/100;

                                    $bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
	                                $evr_usd_price = CoinListing::select('coin_price')->where('coin_symbol','EVR')->first();
	                                $buy_fee_amt = $buyerbalence*$buyfees/100;
	                                if($last_exchange->payment_fee_mode == 1){
	                                	$buyerreturnfees = $buy_fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
	                                }else{
	                                	$buyerreturnfees = $buy_fee_amt;
	                                }

                                    if($buyerreturnfees>0){
                                    	Log::info("BTAdjustmentNew : buyerreturnfees ".$buyerreturnfees);
                                        $returnfees = $buyerreturnfees;
                                        //fee deduction table update
	                                        $fee_amt = ($sellexchange->bid_price*$totalexchanceqty*$buyfees)/100;
	                                        if($last_exchange->payment_fee_mode == 1){
	                                        	$fee_amount = $fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
	                                        }else{
	                                        	$fee_amount = $fee_amt;
	                                        }
	                                        Log::info("BTAdjustmentNew : fee_amount ".$fee_amount);
	                                        $usd_price = CoinListing::select('coin_price')->where('coin_symbol',$last_exchange->fee_deducted_wallet)->first();
	                                		//$fee_in_usd = $fee_amount*$usd_price->coin_price;
	                                		$fee_in_usd = $returnfees*$usd_price->coin_price;
	                                		$fd_res = FeeDeductions::where('bid_id', $last_exchange->id)->first();
	                                        $updata =array(
							            		"fee_amount"=>$fd_res->fee_amount - $returnfees,
							            		"fee_in_usd"=>$fd_res->fee_in_usd - $fee_in_usd
							            	);
							            	Log::info("BTAdjustmentNew : FeeDeductions ".json_encode($updata));
							            	FeeDeductions::where('bid_id', $last_exchange->id)->update($updata);

                                    }
                                    
                                    $buyeruserid      = $last_exchange->user_id;

                                    $balance_data = array(
                                        'user_id'    => $buyeruserid,
                                        'amount'     => $buyerbalence,
                                        'return_fees'=> $returnfees,
                                        'currency_symbol'=>$coin_symbol[1],
                                        'fee_deducted_wallet'=>$last_exchange->fee_deducted_wallet,
                                        'ip'         => \Request::getClientIp(true) 
                                    );

                                    self::balanceReturn($balance_data);

                                }
                                $orderId="OR".rand(100,10000).time();
                                //Exchange Log Data =>Buyer
                                ////Log::info("buy matcheninge6 ");
                                $bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
                                $evr_usd_price = CoinListing::select('coin_price')->where('coin_symbol','EVR')->first();
                                $buy_fee_amt = $buyer_complete_qty_log*$sellexchange->bid_price*$last_exchange->fee_perc/100;
                                if($last_exchange->payment_fee_mode == 1){
                                	$buy_fee_amount = $buy_fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
                                }else{
                                	$buy_fee_amount = $buy_fee_amt;
                                }

                                $buytraderlog = array(
                                	"order_id"=>$orderId,
                                    'bid_id'          => $last_exchange->id,
                                    'bid_type'        => $last_exchange->bid_type,
                                    'complete_qty'    => $buyer_complete_qty_log,
                                    'bid_price'       => $sellexchange->bid_price,
                                    'complete_amount' => $buyer_complete_qty_log*$sellexchange->bid_price,
                                    'user_id'         => $last_exchange->user_id,
                                    'currency_symbol' => $last_exchange->currency_symbol,
                                    'market_symbol'   => $last_exchange->market_symbol,
                                    'success_time'    => date('Y-m-d H:i:s'),
                                    'fees_amount'     => $buy_fee_amount,
                                    'fee_deducted_wallet'     => $last_exchange->fee_deducted_wallet,
                                    'available_amount'=> $buyer_amount_available_log,
                                    //'status'          => ($last_exchange->amount_available-($last_exchange->bid_qty_available*$sellexchange->bid_price)<=0)?1:2,
                                    'status'          => 1,
                                    'trade_history_status'=>1
                                );

                                // Exchange Log Data =>Seller
                                $sell_fee_amt = $seller_complete_qty_log*$sellexchange->bid_price*$sellexchange->fee_perc/100;
                                if($sellexchange->payment_fee_mode == 1){                                	
                                	$sell_fee_amount = $sell_fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
                                }else{
                                	$sell_fee_amount = $sell_fee_amt;
                                }
                                
                                $selltraderlog = array(
                                	"order_id"=>$orderId,
                                    'bid_id'          => $sellexchange->id,
                                    'bid_type'        => $sellexchange->bid_type,
                                    'complete_qty'    => $seller_complete_qty_log,
                                    'bid_price'       => $sellexchange->bid_price,
                                    'complete_amount' => $seller_complete_qty_log*$sellexchange->bid_price,
                                    'user_id'         => $sellexchange->user_id,
                                    'currency_symbol' => $sellexchange->currency_symbol,
                                    'market_symbol'   => $sellexchange->market_symbol,
                                    'success_time'    => date('Y-m-d H:i:s'),
                                    'fees_amount'     => $sell_fee_amount,
                                    'fee_deducted_wallet'     => $sellexchange->fee_deducted_wallet,
                                    'available_amount'=> $sellexchange->bid_qty_available*$sellexchange->bid_price,
                                    //'status'          => ($sellexchange->amount_available-($sellexchange->bid_qty_available*$sellexchange->bid_price)<=0)?1:2,
                                    'status'          => 1,
                                    'trade_history_status'=>0
                                );

                                //Exchange Sell+Buy Log data
                                BidingLog::insert($selltraderlog);
                                //Log::info("buy matcheninge7 ");
                                BidingLog::insert($buytraderlog);
                                //Log::info("buy matcheninge8 ");
                                $ordersArr[]=$orderId;

                                //Buy balance update
                                $buyer_balance = Balance::where('user_id', $last_exchange->user_id)->where('currency_symbol', $coin_symbol[0])->first();

                                if ($buyer_balance===null) {
                                    $user_balance = array(
                                        'user_id'           => $last_exchange->user_id, 
                                        'currency_symbol'   => $coin_symbol[0], 
                                        'balance'           => $buyer_complete_qty_log, 
                                        'last_update'       => date('Y-m-d H:i:s'), 
                                        );
                                    Balance::insert($user_balance);

                                }else{
                                    
									$buyerBalUpdate = $buyer_complete_qty_log;
									Balance::where('user_id', $last_exchange->user_id)->where('currency_symbol', $coin_symbol[0])->increment('balance', $buyerBalUpdate);
									
                                }

                                //Seller balance update
                                $check_seller_balance = Balance::where('user_id', $sellexchange->user_id)->where('currency_symbol', $coin_symbol[1])->first();

                                if($sellexchange->payment_fee_mode == 1){
                                	//order status updaed in fee deductions table
					            	$updata =array(
					            		"status"=>1,
					            		"order_completed_at"=>date("Y-m-d H:i:s"),
					            		"updated_at"=>date("Y-m-d H:i:s")
					            	);
					            	FeeDeductions::where('bid_id', $sellexchange->id)->update($updata);
					            	$sell_fee_amt = 0;
                                }else{
                                	 //Fee dedudtion info table
                                	$sell_fee_amt = $buyer_complete_qty_log*$sellexchange->bid_price*$sellexchange->fee_perc/100;
                                	if($sellexchange->fees_amount > 0){
                                		$usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
                                		$fee_in_usd = $sell_fee_amt*$usd_price->coin_price;
				                    	$bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
				                    	$total_amount_usd = $buyer_complete_qty_log*$sellexchange->bid_price*$bc_usd_price->coin_price;
					                    $fdata = array(
					                	'user_id' => $sellexchange->user_id,
					                    'bid_id' => $sellexchange->id,
					                    'bid_type' => $sellexchange->bid_type,
					                    'total_amount' => $buyer_complete_qty_log*$sellexchange->bid_price,
					                    'total_amount_usd' => $total_amount_usd,
					                    'paid_date' => date("Y-m-d H:i:s"),
					                    'fee_percentage' => $sellexchange->fee_perc,
					                    'fee_amount' => $sell_fee_amt,
					                    'fee_in_usd' => $fee_in_usd,
					                    'currency' => $coin_symbol[1],
					                    'status'  => 1,
					                    "order_completed_at"=>date("Y-m-d H:i:s"),
					            		"updated_at"=>date("Y-m-d H:i:s")
					                );
										FeeDeductions::create($fdata);
									}
										
                                }

                                if ($check_seller_balance===null) {
                                    $user_balance = array(
                                        'user_id'           => $sellexchange->user_id, 
                                        'currency_symbol'   => $coin_symbol[1], 
                                        'balance'           => $buyer_complete_qty_log*$sellexchange->bid_price-$sell_fee_amt, 
                                        'last_update'       => date('Y-m-d H:i:s'), 
                                        );
                                    Balance::insert($user_balance);

                                }else{                                  									
									$sellerBalUpdate = $buyer_complete_qty_log*$sellexchange->bid_price-$sell_fee_amt;
									
									Balance::where('user_id', $sellexchange->user_id)->where('currency_symbol', $coin_symbol[1])->increment('balance', $sellerBalUpdate);
                                }


								$where01 = ["bid_type"=>"BUY","status"=>1,"market_symbol"=>$last_exchange->market_symbol];
								
                               
                                //Log::info("buy matcheninge9 ");
								
                                $h24_coin_supply        = BidingLog::select(DB::raw('SUM(complete_qty) as complete_qty'))->where('success_time','>=',\DB::raw('DATE_SUB(NOW(), INTERVAL 24 HOUR)'))->where($where01)->first();
								
                                //Log::info("buy matcheninge10 ");
                               //$total_all_coin_supply= @$total_coin_supply->complete_qty;
                               $latestdata=LatestTradeData::where('market_symbol', $last_exchange->market_symbol)->first();
                               if($latestdata===null){
                               		$total_coin_supply      = BidingLog::select(DB::raw('SUM(complete_qty) as complete_qty'))->where($where01)->first();
                               		$total_all_coin_supply =@$total_coin_supply->complete_qty;
                               		$volumeFrom=$total_coin_supply->complete_qty-@$buyer_complete_qty_log;
	                               $coinpricedata =	Coinhistory::where('date','like',date("Y-m-d")."%")->where('market_symbol', $last_exchange->market_symbol)->orderBy('date','asc')->first();
	                               $open=0;
	                               $close=0;
	                               $open_value=0;
	                               $coinpricedata2 =	Coinhistory::select('last_price','change_perc','close','open')->where('market_symbol', $last_exchange->market_symbol)->orderBy('date','desc')->first();
	                               if($coinpricedata===null){
	                               		
		                               	if($coinpricedata2!==null){
		                           			$open=@$coinpricedata2->close;
											$open_value=$open;
		                               	}else{
		                               		$open=0;
	                               			$open_value=$open;
		                               	}
										
	                               }else{
	                               	 //Log::info("buy match3");
	                               		$open=@$coinpricedata->open;
										$open_value=$coinpricedata2->last_price;
	                               }
                               	}else{
                               		//Log::info("after before coinhistory6 ".json_encode($latestdata));
                               		$updatedat=date("Y-m-d",strtotime($latestdata['updated_at']));
                                	$open_value=$latestdata['last_price'];
                                	if($updatedat==date("Y-m-d")){
                                		$open=$latestdata['day_open_price'];
										
                                	}else{
                                		$open=$latestdata['close'];
                                	}
                                	$total_all_coin_supply = @$buyer_complete_qty_log+$latestdata['total_coin_supply'];
                                	$volumeFrom=$latestdata['total_coin_supply'];
                               	}
 //Log::info("buy match4");
                               $change_perc=0;
                               if($open>0){
                               		$change_perc=(($sellexchange->bid_price-$open)/$open)*100;
                               		$change_perc=round($change_perc,2);
                               	}
                               $volume_24h=(@$h24_coin_supply->complete_qty=='')?0:$h24_coin_supply->complete_qty;
                                /*Log::info("buy matcheninge11 ");
                                Log::info("buy matcheninge13 ".$orderId);
                                Log::info("buy matcheninge13 ".$last_exchange->currency_symbol);
                                Log::info("buy matcheninge13 ".$last_exchange->market_symbol);
                                Log::info("buy matcheninge13 ".$sellexchange->bid_price);
                                Log::info("buy matcheninge13 ".$total_all_coin_supply);
                                Log::info("buy matcheninge13 ".$volume_24h);
                                Log::info("buy matcheninge13 ".$open);
                                Log::info("buy matcheninge13 ".$open_value);
                                Log::info("buy matcheninge13 ".$sellexchange->bid_price);
                                Log::info("buy matcheninge13 ".$volumeFrom);
                                Log::info("buy matcheninge13 ".$total_all_coin_supply);
                                 Log::info("buy matcheninge13 ".$change_perc);*/
                                 $latest_usd_price=$sellexchange->bid_price*$bc_usd_price->coin_price;
                                $coinhistory = array(
                                	"order_id"=>$orderId,
                                    'coin_symbol'       => $last_exchange->currency_symbol,
                                    'market_symbol'     => $last_exchange->market_symbol,
                                    'usd_price'         => $latest_usd_price,
                                    'last_price'        => $sellexchange->bid_price,
                                    'total_coin_supply' => $total_all_coin_supply,
                                    'volume_24h' 		=> $volume_24h,
                                    'day_open_price'	=> $open,
                                    'open'              => $open_value,
                                    'close'             => $sellexchange->bid_price,
                                    'volumefrom'        => $volumeFrom,
                                    'volumeto'          => $total_all_coin_supply,
                                    'change_perc'=>$change_perc,
                                    'date'              => date('Y-m-d H:i:s'),
                                );
                                Log::info("buy matcheninge13 ".json_encode($coinhistory));
                                Coinhistory::insert($coinhistory);
                                Log::info("buy matcheninge12 ");
                                Log::info("buy matchengine after insert ".$exchange_id);
                                
                                if($sellexchange->user_id!=$user_id){
                                	Log::info("bal_order_list_history call ".$exchange_id);
                                	RabbitmqController::bal_order_list_history(json_encode(array("market_symbol"=>$last_exchange->market_symbol,"user_id"=>$sellexchange->user_id,"latest_bidding_log_orders"=>[$orderId])));
                                }
                                
                                
                                self::pairingDataForPublish($last_exchange->market_symbol,$sellexchange->bid_price,$total_all_coin_supply,$change_perc,$volume_24h);
                               
                                
                            }else{
                            	//$aftermatch=1;
                            	break;
                            }
                         
                        }else{
                        	//$aftermatch=1;
                        	break;
                        }
                        
                            //Order running

                        }
                        //Order list in loop

                    }
                    
					if($match==1){
						RabbitmqController::bal_order_list_history(json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id,"latest_bidding_log_orders"=>$ordersArr)));
						//self::bal_order_list_history_publish($last_exchange->market_symbol,$user_id);
						//Log::info("buy match after3");
						RabbitmqController::buy_sell_trade_exchange(json_encode(array("market_symbol"=>$market_symbol,"latest_bidding_log_orders"=>$ordersArr)));
					}else{
						RabbitmqController::bal_order_list(json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id)));
						//self::bal_order_list_history_publish($last_exchange->market_symbol,$user_id);
						//Log::info("buy match after3");
						RabbitmqController::buy_sell(json_encode(array("market_symbol"=>$market_symbol)));
					}
					
					

	}
	public static function bal_order_list_history_publish($market_symbol,$user_id){
		$queue = 'bal_order_list_history_queue';
		$exchange = 'router';
		//Log::info("bal_order_list_history_publish ".$market_symbol);
		$rabitmqData = json_encode(array("queue_type"=>"bal_order_list_history_queue","market_symbol"=>$market_symbol,"user_id"=>$user_id));
		//Log::info("balance publish data");
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
	}
	public static function buy_sell_trade_exchange_publish($market_symbol){
		$queue = 'buy_sell_trade_exchange_queue';
		$exchange = 'router';
		//Log::info("buy_sell_trade_exchange ".$market_symbol);
		$rabitmqData = json_encode(array("queue_type"=>"buy_sell_trade_exchange_queue","market_symbol"=>$market_symbol));
		//Log::info("buyorders_queue publish data");
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
	}
	public static function balanceForPublish($market_symbol,$user_id){
		$queue = 'balance_queue';
		$exchange = 'router';
		//Log::info("balanceForPublish ".$market_symbol);
		$rabitmqData = json_encode(array("queue_type"=>"balance","market_symbol"=>$market_symbol,"user_id"=>$user_id));
		//Log::info("balance publish data");
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
	}
	public static function ordersListForPublish($market_symbol,$user_id){
		$queue = 'orderslist_queue';
		$exchange = 'router';
		//Log::info("ordersListForPublish ".$market_symbol);
		$rabitmqData = json_encode(array("queue_type"=>"orderslist","market_symbol"=>$market_symbol,"user_id"=>$user_id));
		//Log::info("orders list publish data");
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
	}
	public static function ordersHistoryForPublish($market_symbol,$user_id){
		$queue = 'ordershistory_queue';
		$exchange = 'router';
		//Log::info("ordersHistoryForPublish ".$market_symbol);
		$rabitmqData = json_encode(array("queue_type"=>"ordershistory","market_symbol"=>$market_symbol,"user_id"=>$user_id));
		//Log::info("orders history publish data");
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
	}
	public static function getExchangeForPublish($market_symbol){
		$queue = 'getexchange_queue';
		$exchange = 'router';
		//Log::info("getExchangeForPublish ".$market_symbol);
		$rabitmqData = json_encode(array("queue_type"=>"getexchange","market_symbol"=>$market_symbol));
		//Log::info("buyorders_queue publish data");
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
	}
	public static function pairingDataForPublish($market_symbol,$bid_price,$total_coin_supply,$change_perc,$volume_24h){
		//Log::info("pairingDataForPublish1 ".$market_symbol);
		$queue = 'pairingdata_queue';
		$exchange = 'router';
		$total_volume = ($volume_24h)?$volume_24h:0;
        $volume=floatval($bid_price)*floatval($total_volume);
		
				//Log::info("pairingDataForPublish2 ".$market_symbol);					
		//$change = $price_change_24h/$bid_price;
		$change = $change_perc;
		//$price_change_percent = (round($change*100)/100)*100;
		$price_change_percent = $change;
		$index=Redis::get('coinspairs:'.$market_symbol);
		$pairarr=explode('_', $market_symbol);
		$pairing=Redis::lindex('pairing:'.$pairarr[1], $index);
		//Log::info("pairingDataForPublish3 ".$market_symbol);
		//Log::info("pairing data 1".$pairing);
		//Log::info("pairing index ".$index);
		//$pairinginfo = Redis::lrange('pairing:'.$pairarr[1],0,1000);
		$pairing=json_decode($pairing);
		$pairing->market_price= number_format_eight_dec($bid_price);
		if($volume==0){
			$volume=0.00;
		}
		$fomatvolume=number_format_four_dec($volume);
        $roundvolume=str_replace(",", "", $fomatvolume);
		$pairing->volume= $roundvolume;
		$pairing->change= number_format_two_dec($price_change_percent);
		//Log::info("pairing data 2".json_encode($pairing));
		//Log::info("pairingDataForPublish4 ".$market_symbol);
		Redis::lSet('pairing:'.$pairarr[1], $index, json_encode($pairing));
		$pairing2=Redis::lindex('pairing:'.$pairarr[1], $index);
		//Log::info("pairing data 3".$pairing2);
		$rabitmqData = json_encode(array("queue_type"=>"pairingdata","market_symbol"=>$market_symbol,"price"=>number_format_eight_dec($bid_price),"volume"=>$roundvolume,"change"=>number_format_two_dec($price_change_percent)));
		//Log::info("pairingDataForPublish ".$market_symbol);
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
		//RabbitmqController::pairingdata_broadcast($rabitmqData);
	}
	public static function buyordersQueue($market_symbol){
		$queue = 'buyorders_queue';
		$exchange = 'router';
		$rabitmqData = json_encode(array("queue_type"=>"buyorders","market_symbol"=>$market_symbol));
		//Log::info("buyordersQueue ".$market_symbol);
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
		
	}
	
	public static function sellordersQueue($market_symbol){

		$queue = 'sellorders_queue';
		$exchange = 'router';
		$rabitmqData = json_encode(array("queue_type"=>"sellorders","market_symbol"=>$market_symbol));
		//Log::info("sellordersQueue ".$market_symbol);
		RabbitmqController::publish_channel($queue,$rabitmqData,$exchange);
		
	}
	
	public static function balanceDebit1($data = array())
	{
		$check_user_balance = Balance::where('user_id', $data->user_id)->where('currency_symbol', $data->currency_symbol)->first();

		/*$updatebalance = array(
            'balance'     => $check_user_balance->balance-($data->bid_price+$data->fees_amount),
        );*/
		
		/*$updatebalance = array(
            'balance'     => $check_user_balance->balance-(@$data->total_amount+@$data->fees_amount),
        );*/
		$updatebalance = array(
            'balance'     => $check_user_balance->balance-@$data->bid_qty,
        );
		Log::info("sell order updatebalance ".json_encode($updatebalance));
        return Balance::where('user_id', $data->user_id)->where('currency_symbol', $data->currency_symbol)->update( $updatebalance);

	}
	
	
	
	
	public function sell(Request $request)
    {
		
		$data = $request->user();
		$user_id = $data['user_id'];
		
		$validator = Validator::make($request->all(), [
			'market' => 'required',
			'sellpricing' => 'required',
			'sellamount' => 'required',
			
		]);
		if ($validator->fails()) {
			return response()->json(['status' => false, 'Result' => $validator->errors()], 200);
		}
		
        if ($user_id){		
			
            $coin_symbol         = explode('_', request('market'));
            $market_symbol       = request('market');
            $rate                = request('sellpricing'); //$this->input->post('sellpricing', TRUE);
            $qty                 = request('sellamount'); //$this->input->post('sellamount', TRUE);
            //$user_id             = $this->session->userdata('user_id');
            $amount_withoutfees = $rate * $qty;
            $bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
            //Check SELL fees
            $roleSettings = RolesSettings::select('*')->where("role","=",$data['role'])->first();
            $userData = Userinfo::select("*")->where("user_id","=",$user_id)->first();
            if($roleSettings->fee_applicable == 1){

	            if($userData->payment_fee_mode == 1){
	            	$fees = self::getFees('SELL', 'EVR'); 
	            	if ($fees) {                
		                //$fees_amount = ($rate*$qty*$fees->fees)/100;
		                $fee_amt = ($amount_withoutfees*$fees)/100;
		                
						$evr_usd_price = CoinListing::select('coin_price')->where('coin_symbol','EVR')->first();
						$fees_amount = $fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
		                $sellfees    = $fees;
		            }else{
		                $fees_amount = 0;
		                $sellfees    = 0;
		            }	            	

	            }else{
	            	$fees = self::getFees('SELL', $coin_symbol[1]); 
	            	if ($fees) {                
		                //$fees_amount = ($rate*$qty*$fees->fees)/100;
		                $fees_amount = ($amount_withoutfees*$fees)/100;
		                $sellfees    = $fees;
		            }else{
		                $fees_amount = 0;
		                $sellfees    = 0;
		            }
	            }	            

	        }else{
	        	$fees_amount = 0;
	            $sellfees    = 0;
	        }
           // $pdo = DB::connection()->getPdo();
	    //$pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
	    DB::beginTransaction();
            //$amount_withoutfees = $qty;
            $amount_withfees    = $qty + $fees_amount;
            Log::info("sell order placing ".$amount_withfees);
            //$amount_withfees    = $amount_withoutfees;
			//echo "amt_with_fee = ";echo $amount_withfees;exit;
            //SELL(BTC_USD) = C0_C1, BUY C0 vai C1
            $balance_c0         = self::checkBalance($user_id,$coin_symbol[0]);
            $balance_c1         = self::checkBalance($user_id,$coin_symbol[1]);
            $evr_balance         = self::checkBalance($user_id,'EVR');

            //Pending Withdraw amoun sum
            //$pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $user_id)->first();

            //Discut user withdraw pending balance
            //$real_balance = (float)@$balance_c0->balance-(float)@$pending_withdraw->amount;
            $real_balance = (float)@$balance_c0->balance;
            $everus_balance = (float)@$evr_balance->balance;
            $fee_deducted_wallet = $coin_symbol[1];
            if($userData->payment_fee_mode == 1){            	
            	//$amount_withfees = $amount_withoutfees;
            	$fee_deducted_wallet = 'EVR';
            	if($coin_symbol[0] == 'EVR' ){
            		if($everus_balance >= $amount_withfees && @$evr_balance->balance>0){
	            		//true
	            	}else{
	            		return response()->json(['status' => false, 'Result' => "Insufficient Fee In EVR Wallet."], 200);
	            	}
            	}else{
            		if($everus_balance >= $fees_amount && @$evr_balance->balance>0){
	            		//true
	            	}else{
	            		return response()->json(['status' => false, 'Result' => "Insufficient Fee In EVR Wallet."], 200);
	            	}
            	}
            	
            }
		Log::info("Balance data ".$user_id." ".@$real_balance." ".@$balance_c0->balance." ".$qty);
            if (@$real_balance >= $qty && @$balance_c0->balance>0 && $qty>0) {
            	$rate=number_format_eight_dec($rate);
            	$rate=str_replace(",", "", $rate);
                //$date       = new DateTime();
                $open_date  = date('Y-m-d H:i:s');
                $trade_id="TR".rand(100,10000).time();
                $tdata['TRADES']   = (object)$exchangedata = array(
                	//'trade_id'=>$trade_id,
                    'bid_type'          => 'SELL',
                    'bid_price'         => $rate,
                    'bid_qty'           => $qty,
                    'bid_qty_available' => $qty,
                    'total_amount'      => $rate*$qty,
                    'amount_available'  => $rate*$qty,
                    'currency_symbol'   => $coin_symbol[0],
                    'market_symbol'     => $market_symbol,
                    'user_id'           => $user_id,
                    'open_order'        => $open_date,
                    'fees_amount'       => $fees_amount,
                    'fee_deducted_wallet' => $fee_deducted_wallet,
                    'payment_fee_mode'	=> $userData->payment_fee_mode,
                    'fee_perc'       => $sellfees,
                    'bc_usd_price'		=> $bc_usd_price->coin_price,
                    'status'            => 2
                );
				$info = Biding::create($exchangedata);
				$exchange_id = $info->id;
                //Exchange Data Insert
                if ($exchange_id ) {                   

                    $last_exchange   =  Biding::where("id","=",$exchange_id)->first(); //$this->web_model->single($exchange_id);
                    //User Balance Debit(-) C0
                    Log::info("sell order balance cond1 ");
                    self::balanceDebit1($last_exchange);
					Log::info("sell order balance cond2 ");
                    if($userData->payment_fee_mode == 1 && @$fees_amount > 0){ 
                    	$evr_balance_new = self::checkBalance($user_id,'EVR');
                    	$updatebalance = array(
				            'balance' => $evr_balance_new->balance-@$fees_amount,
				        );
				        Log::info("sell order fee deductions ".json_encode($updatebalance));
				        $res = Balance::where('user_id',$user_id)->where('currency_symbol', 'EVR')->update($updatebalance);
				        //Fee dedudtion info table
				        	Log::info("sell order fee deductions2 ");
				        	$usd_price = CoinListing::select('coin_price')->where('coin_symbol',$fee_deducted_wallet)->first();
				        	$fee_in_usd = $fees_amount*$usd_price->coin_price;
	                    	$bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
	                    	$total_amount_usd = $rate*$qty*$bc_usd_price->coin_price;
		                    $fdata = array(
		                	'user_id' => $user_id,
		                    'bid_id' => $exchange_id,
		                    'bid_type' => 'SELL',
		                    'total_amount' => $rate*$qty,
		                    'total_amount_usd' => $total_amount_usd,
		                    'paid_date' => $open_date,
		                    'fee_percentage' => $sellfees,
		                    'fee_amount' => $fees_amount,
		                    'fee_in_usd' => $fee_in_usd,
		                    'currency' => $fee_deducted_wallet,
		                    'status'  => 2
		                );
							FeeDeductions::create($fdata);
						
                    }
                    Log::info("sell order fee deductions end");
					
					$rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"sell"));
					//Log::info("sell publish data ".$exchange_id);
		    DB::commit();
					RabbitmqController::publish_channel('sell_queue',$rabitmqData,'router');
					//Log::info("sell publish data2 ".$exchange_id);
					/*RabbitmqController::sellorders(json_encode(array("market_symbol"=>$market_symbol)));
					RabbitmqController::orderslist_broadcast(json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id)));*/
					//DB::commit();
					echo json_encode(array("Success"=>true,'status' => 200,'Result'=>''));
					//self::sellTradeMatchingEngine($exchange_id,$user_id,$market_symbol,$coin_symbol);
					
                    //After balance discut(-)
                   }else{
                    //echo 0;
                    //trade not submited
					echo json_encode(array("Success"=>false,'status' => 422,'Result'=>"Trade not submitted"));
                }

            }else{
                //echo 2;
                //Insufficent Balance
				echo json_encode(array("Success"=>false,'status' => 422,'Result'=>"Insufficient Balance"));

            }
            DB::commit();
        } else {
            //echo 1;
            //login requred
            echo json_encode(array("Success"=>false,'status' => 422,'Result'=>"User not found"));
        }

    }

	public static function sellTradeMatchingEngine($exchange_id,$user_id,$market_symbol,$coin_symbol){
		Log::info("sellTradeMatchingEngine function start".$exchange_id);
		$open_date  = date('Y-m-d H:i:s');
		//Log::info("sellTradeMatchingEngine1 ");
		//DB::enableQueryLog();
		$last_exchange   =  Biding::where("id","=",$exchange_id)->first();
		//Log::debug(DB::getQueryLog());
		$rate = $last_exchange->bid_price;
		//Log::info("sellTradeMatchingEngine 2 ".json_encode($last_exchange));
		$bid_qty = $last_exchange->bid_qty;
		$amount=$rate*$bid_qty;
		  //Check SELL fees
            // $fees = self::getFees('SELL', $coin_symbol[0]);
            // //Log::info("sellTradeMatchingEngine executed getfees() ");
            // if ($fees) {                
            //     //$fees_amount = ($rate*$qty*$fees->fees)/100;
            //     $fees_amount = ($amount*$fees)/100;
            //     $sellfees    = $fees;

            // }else{
            //     $fees_amount = 0;
            //     $sellfees    = 0;

            // }


            //BUY fees
            // $buyerfees = self::getFees('BUY', $coin_symbol[1]);
            // if ($buyerfees) {
            //     $buyfees     = $buyerfees;

            // }else{
            //     $buyfees     = 0;

            // }
		 $balance = self::checkBalance($user_id,$coin_symbol[0]);
//Log::info("sellTradeMatchingEngine 3 ");
			 
                  //Log::info("buy_exchange_query query  ".$rate." ".$market_symbol);
                    $buy_exchange_query = Biding::select('*')
											->where("bid_price",">=",$rate)
											->where("status","=",2)
											->where("bid_type","=","BUY")
											->where("market_symbol","=",$market_symbol)
											->where("id","<",$exchange_id)
											->orderBy('bid_price', 'asc')
											->get();
											//Log::info("sellTradeMatchingEngine 4 ".json_encode($buy_exchange_query));
					$match=0;	
					//$aftermatch=0;
					 $ordersArr=array();					
					//Log::info("buy_exchange_query query executed 1 ".count($buy_exchange_query));
                    if (@count($buy_exchange_query) > 0) {
                    	$match=1;
                        foreach ($buy_exchange_query as $key => $buyexchange) {
                        	//Log::info("buy_exchange_query loop started");
                            $seller_available_qty        = 0;
                            $buyer_available_qty         = 0;
                            $buyer_amount_available      = 0;
                            $seller_amount_available     = 0;
                            $seller_amount_available_log = 0;
                            $seller_complete_qty_log     = 0;
                            $buyer_complete_qty_log      = 0;
							
							$last_exchange   =  Biding::where("id","=",$exchange_id)->first();
                           //$this->web_model->single($exchange_id);
							//Log::info("buy_exchange_query loop started");
							if($last_exchange!==null){
								//Log::info("buy_exchange_query loop started");
	                            if ($last_exchange->status==2) {
								//Log::info("buy_exchange_query loop started");
	                                //Seller+Buyer Quantity/Amount Complete/Available Master table
	                                $seller_available_qty       = (($last_exchange->bid_qty_available-$buyexchange->bid_qty_available)<=0)?0:($last_exchange->bid_qty_available-$buyexchange->bid_qty_available);
	                                $buyer_available_qty        = (($buyexchange->bid_qty_available-$last_exchange->bid_qty_available)<0)?0:$buyexchange->bid_qty_available-$last_exchange->bid_qty_available;
	                                $buyer_amount_available     = ((($buyexchange->bid_qty_available-$last_exchange->bid_qty_available)<0)?0:$buyexchange->bid_qty_available-$last_exchange->bid_qty_available)*$last_exchange->bid_price;
	                                $seller_amount_available    = ($last_exchange->amount_available-($buyexchange->bid_qty_available*$last_exchange->bid_price)<=0)?0:($last_exchange->amount_available-($buyexchange->bid_qty_available*$last_exchange->bid_price));
	                                $seller_amount_available_log = ($last_exchange->amount_available-($last_exchange->bid_qty_available*$last_exchange->bid_price)<=0)?0:($last_exchange->amount_available-($last_exchange->bid_qty_available*$last_exchange->bid_price));

	                                // Seller+Buyer Quantity Complete log table
	                                $seller_complete_qty_log  = (($buyexchange->bid_qty_available-$last_exchange->bid_qty_available)==0)?$last_exchange->bid_qty_available:((($buyexchange->bid_qty_available-$last_exchange->bid_qty_available)<=0)?$buyexchange->bid_qty_available:$last_exchange->bid_qty_available);
	                                //$buyer_complete_qty_log   = (($buyexchange->bid_qty_available-$last_exchange->bid_qty_available)==0)?$last_exchange->bid_qty_available:((($buyexchange->bid_qty_available-$last_exchange->bid_qty_available)<=0)?$buyexchange->bid_qty_available:$last_exchange->bid_qty_available);
	                                $buyer_complete_qty_log  =$seller_complete_qty_log;
	                                $bqty=$last_exchange->bid_qty_available-$buyexchange->bid_qty_available;
	                                //Log::info("bqty ".$bqty);
	                                if($bqty<=0){
	                                	//Log::info("buy_exchange_query loop startedddd");
	                                	$newsell_arr=array(
	                                		"exchange_id"=>$exchange_id,
	                                		"bid_type"=>$last_exchange->bid_type,
	                                		"bid_price"=>$last_exchange->bid_price,
	                                		"bid_qty" => $last_exchange->bid_qty,
	                                		"bid_qty_available"=>$seller_available_qty,
	                                		"total_amount"=>$last_exchange->total_amount,
	                                		"amount_available"=>$seller_amount_available,
	                                		"currency_symbol"=>$last_exchange->currency_symbol,
	                                		"market_symbol"=>$last_exchange->market_symbol,
	                                		"user_id"=>$last_exchange->user_id,
	                                		"open_order"=>$last_exchange->open_order,
	                                		"fees_amount"=>$last_exchange->fees_amount,
	                                		"fee_deducted_wallet"=>$last_exchange->fee_deducted_wallet,
	                                		"payment_fee_mode"=>$last_exchange->payment_fee_mode,
	                                		"fee_perc"=>$last_exchange->fee_perc,
	                                		"bc_usd_price"=>$last_exchange->bc_usd_price,
	                                		"status"=>1,
	                                		"created_at"=>date("Y-m-d H:i:s",strtotime($last_exchange->created_at)),
	                                		"updated_at"=>date("Y-m-d H:i:s",strtotime($last_exchange->updated_at))
	                                	);
	                                	//Log::info("buy_exchange_query loop startedddd ".json_encode($newsell_arr));
	                                	ExecutedOrders::insert($newsell_arr);
	                                	Biding::where('id', $exchange_id)->delete();
	                                	//order status updaed in fee deductions table
						            	$updata =array(
						            		"status"=>1,
						            		"order_completed_at"=>date("Y-m-d H:i:s"),
						            		"updated_at"=>date("Y-m-d H:i:s")
						            	);
						            	FeeDeductions::where('bid_id', $buyexchange->id)->update($updata);
						            	Log::info("sell trade1 ".json_encode($updata));
	                                }else{
	                                	//Log::info("buy_exchange_query loop startedeeeee");
	                                	$exchangeselldata = array(
		                                    'bid_qty_available'  => $seller_available_qty,
		                                    'amount_available'   => $seller_amount_available, //Balance added SELL account
		                                    //'status'             => (($last_exchange->bid_qty_available-$buyexchange->bid_qty_available)<=0)?1:2,
		                                    'status'             => 2,
		                                );
		                                Biding::where('id', $exchange_id)->update($exchangeselldata);
	                                }
	                                //Exchange Data =>Sell
	                               	$sqty=$buyexchange->bid_qty_available-$last_exchange->bid_qty_available;
	                               	//Log::info("sqty ".$sqty);
	                                if($sqty<=0){
	                                	$newbuy_arr=array(
	                                		"exchange_id"=>$buyexchange->id,
	                                		"bid_type"=>$buyexchange->bid_type,
	                                		"bid_price"=>$buyexchange->bid_price,
	                                		"bid_qty" => $buyexchange->bid_qty,
	                                		"bid_qty_available"=>$buyer_available_qty,
	                                		"total_amount"=>$buyexchange->total_amount,
	                                		"amount_available"=>$buyer_amount_available,
	                                		"currency_symbol"=>$buyexchange->currency_symbol,
	                                		"market_symbol"=>$buyexchange->market_symbol,
	                                		"user_id"=>$buyexchange->user_id,
	                                		"open_order"=>$buyexchange->open_order,
	                                		"fees_amount"=>$buyexchange->fees_amount,
	                                		"fee_deducted_wallet"=>$buyexchange->fee_deducted_wallet,
	                                		"payment_fee_mode"=>$buyexchange->payment_fee_mode,
	                                		"fee_perc"=>$buyexchange->fee_perc,
	                                		"bc_usd_price"=>$buyexchange->bc_usd_price,
	                                		"status"=>1,
	                                		"created_at"=>date("Y-m-d H:i:s",strtotime($buyexchange->created_at)),
	                                		"updated_at"=>date("Y-m-d H:i:s",strtotime($buyexchange->updated_at))
	                                	);
	                                	//Log::info("buy_exchange_query loop starteddddbbb ".json_encode($newbuy_arr));
	                                	ExecutedOrders::insert($newbuy_arr);
	                                	Biding::where('id', $buyexchange->id)->delete();
	                                	//order status updaed in fee deductions table
						            	$updata =array(
						            		"status"=>1,
						            		"order_completed_at"=>date("Y-m-d H:i:s"),
						            		"updated_at"=>date("Y-m-d H:i:s")
						            	);
						            	FeeDeductions::where('bid_id', $buyexchange->id)->update($updata);
						            	Log::info("sell trade2 ".json_encode($updata));
	                                }else{
	                               		$exchangebuydata  = array(
		                                    'bid_qty_available'  => $buyer_available_qty,
		                                    'amount_available'   => $buyer_amount_available, //Balance added BUY account
		                                    'status'             => 2,
		                                );
		                                                              
		                                Biding::where('id', $buyexchange->id)->update($exchangebuydata);
	                               	}
	                               	
	                                //Adjustment Amount+Fees
	                                if($buyexchange->bid_price>$last_exchange->bid_price){

	                                    $totalexchanceqty = $buyer_complete_qty_log;
	                                    $buyremeaningrate = $buyexchange->bid_price-$last_exchange->bid_price;//5-1
	                                    $buyerbalence     = $buyremeaningrate*$totalexchanceqty;//4*1

	                                    //Fees when Adjustment
	                                    $returnfees     = 0;
	                                    $buyfees = $buyexchange->fee_perc;

	                                    //$byerfees       = ($totalexchanceqty*$buyexchange->bid_price*$buyfees)/100;
	                                    //$sellerrfees    = ($totalexchanceqty*$last_exchange->bid_price*$sellfees)/100;
	                                    //$buyerreturnfees= $byerfees-$sellerrfees;

	                                    //$buyerreturnfees = $buyerbalence*$buyfees/100;
	                                    $bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
		                                $evr_usd_price = CoinListing::select('coin_price')->where('coin_symbol','EVR')->first();
		                                $buy_fee_amt = $buyerbalence*$buyfees/100;
		                                if($buyexchange->payment_fee_mode == 1){
		                                	$buyerreturnfees = $buy_fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
		                                }else{
		                                	$buyerreturnfees = $buy_fee_amt;
		                                }

	                                    if($buyerreturnfees>0){
	                                    	Log::info("STAdjustmentNew : buyerreturnfees ".$buyerreturnfees);
	                                        $returnfees = $buyerreturnfees;
	                                        //fee deduction table update
	                                        $fee_amt = ($last_exchange->bid_price*$totalexchanceqty*$buyfees)/100;
	                                        if($buyexchange->payment_fee_mode == 1){
	                                        	$fee_amount = $fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
	                                        }else{
	                                        	$fee_amount = $fee_amt;
	                                        }
	                                        Log::info("STAdjustmentNew : fee_amount ".$fee_amount);
	                                        $usd_price = CoinListing::select('coin_price')->where('coin_symbol',$buyexchange->fee_deducted_wallet)->first();
	                                		//$fee_in_usd = $fee_amount*$usd_price->coin_price;
	                                		$fee_in_usd = $returnfees*$usd_price->coin_price;
	                                		$fd_res = FeeDeductions::where('bid_id', $buyexchange->id)->first();
	                                        $updata =array(
							            		"fee_amount"=>$fd_res->fee_amount - $returnfees,
							            		"fee_in_usd"=>$fd_res->fee_in_usd - $fee_in_usd
							            	);
							            	Log::info("STAdjustmentNew : FeeDeductions ".json_encode($updata));
							            	FeeDeductions::where('bid_id', $buyexchange->id)->update($updata);
							            	Log::info("sell trade3 ".json_encode($updata));
	                                    }
	                                    
	                                    $buyeruserid      = $buyexchange->user_id;

	                                    $balance_data = array(
	                                        'user_id'        => $buyeruserid,
	                                        'amount'         => $buyerbalence,
	                                        'return_fees'    => $returnfees,
	                                        'currency_symbol'=>$coin_symbol[1],
	                                        'fee_deducted_wallet'=>$buyexchange->fee_deducted_wallet,
	                                        'ip'             => \Request::getClientIp(true)
	                                    );

	                                    self::balanceReturn($balance_data);

	                                }

	                                $orderId="OR".rand(100,10000).time();
	                                Log::info("sell match engine after biding update");
	                                //Exchange Log Data =>Seller
	                                $bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
	                                $evr_usd_price = CoinListing::select('coin_price')->where('coin_symbol','EVR')->first();
	                                $sell_fee_amt = $seller_complete_qty_log*$last_exchange->bid_price*$last_exchange->fee_perc/100;
	                                if($last_exchange->payment_fee_mode == 1){
	                                	$sell_fee_amount = $sell_fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
	                                }else{
	                                	$sell_fee_amount = $sell_fee_amt;
	                                }

	                                $selltraderlog = array(
	                                	"order_id"=>$orderId,
	                                    'bid_id'          => $last_exchange->id,
	                                    'bid_type'        => $last_exchange->bid_type,
	                                    'complete_qty'    => $seller_complete_qty_log,
	                                    'bid_price'       => $last_exchange->bid_price,
	                                    'complete_amount' => $seller_complete_qty_log*$last_exchange->bid_price,
	                                    'user_id'         => $last_exchange->user_id,
	                                    'currency_symbol' => $last_exchange->currency_symbol,
	                                    'market_symbol'   => $market_symbol,
	                                    'success_time'    => date('Y-m-d H:i:s'),
	                                    'fees_amount'     => $sell_fee_amount,
	                                    'fee_deducted_wallet'     => $last_exchange->fee_deducted_wallet,
	                                    'available_amount'=> $seller_amount_available_log,
	                                    //'status'          => ($last_exchange->amount_available-($last_exchange->bid_qty_available*$last_exchange->bid_price)<=0)?1:2,
	                                    'status'          => 1,
	                                    'trade_history_status'=>1
	                                );
	//Log::info("sell match engine after biding update2");
	                                // Exchange Log Data =>Buyer 
	                                $buy_fee_amt = $buyer_complete_qty_log*$last_exchange->bid_price*$buyexchange->fee_perc/100;
	                                if($buyexchange->payment_fee_mode == 1){	                                	
	                                	$buy_fee_amount = $buy_fee_amt*$bc_usd_price->coin_price/$evr_usd_price->coin_price;
	                                }else{
	                                	$buy_fee_amount = $buy_fee_amt;
	                                }
	                               $buytraderlog = array(
	                               		"order_id"=>$orderId,
	                                    'bid_id'          => $buyexchange->id,
	                                    'bid_type'        => $buyexchange->bid_type,
	                                    'complete_qty'    => $buyer_complete_qty_log,
	                                    'bid_price'       => $last_exchange->bid_price,
	                                    'complete_amount' => $buyer_complete_qty_log*$last_exchange->bid_price,
	                                    'user_id'         => $buyexchange->user_id,
	                                    'currency_symbol' => $buyexchange->currency_symbol,
	                                    'market_symbol'   => $market_symbol,
	                                    'success_time'    => date('Y-m-d H:i:s'),
	                                    'fees_amount'     => $buy_fee_amount,
	                                    'fee_deducted_wallet'     => $buyexchange->fee_deducted_wallet,
	                                    'available_amount'=> $buyexchange->bid_qty_available*$last_exchange->bid_price,
	                                    //'status'          => ($buyexchange->amount_available-($buyexchange->bid_qty_available*$last_exchange->bid_price)<=0)?1:2,
	                                    'status'          => 1,
	                                    'trade_history_status'=>0
	                                );
	                               Log::info("before insert biding log");
	                                //Exchange Sell+Buy Log data
	                                BidingLog::insert($buytraderlog);
	                                Log::info("after insert biding log");
	                                BidingLog::insert($selltraderlog);
	                                Log::info("after insert biding log2");
	                                $ordersArr[]=$orderId;
	                               
	                                //Buy balance update
	                                $check_user_balance = Balance::where('user_id', $buyexchange->user_id)->where('currency_symbol', $coin_symbol[0])->first();
										
	                                if ($check_user_balance===null) {
	                                	//Log::info("sell match engine after biding update23");
	                                    $user_balance = array(
	                                        'user_id'           => $buyexchange->user_id, 
	                                        'currency_symbol'   => $coin_symbol[0], 
	                                        'balance'           => $seller_complete_qty_log, 
	                                        'last_update'       => date('Y-m-d H:i:s'), 
	                                        );
	                                    Balance::insert($user_balance);
	//Log::info("sell match engine after biding update33");
	                                }else{
	                                     
										$BuyerBalUpdate = $seller_complete_qty_log;
										Balance::where('user_id', $buyexchange->user_id)->where('currency_symbol', $coin_symbol[0])->increment('balance', $BuyerBalUpdate);
										//Log::info("sell match engine after biding update53");
	                                }


	                                //Seller balance update
	                                if($last_exchange->payment_fee_mode == 1){
	                                	//order status updaed in fee deductions table
						            	$updata =array(
						            		"status"=>1,
						            		"order_completed_at"=>date("Y-m-d H:i:s"),
						            		"updated_at"=>date("Y-m-d H:i:s")
						            	);
						            	FeeDeductions::where('bid_id', $last_exchange->id)->update($updata);
						            	Log::info("sell trade4 ".json_encode($updata));
						            	$sell_fee_amt = 0;
	                                }else{
	                                	 //Fee dedudtion info table
	                                	$sell_fee_amt = $seller_complete_qty_log*$last_exchange->bid_price*$last_exchange->fee_perc/100;

	                                	if($last_exchange->fees_amount > 0){
	                                		$usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
	                                		$fee_in_usd = $sell_fee_amt*$usd_price->coin_price;
					                    	$bc_usd_price = CoinListing::select('coin_price')->where('coin_symbol',$coin_symbol[1])->first();
					                    	$total_amount_usd = $seller_complete_qty_log*$last_exchange->bid_price*$bc_usd_price->coin_price;
	                                		$fdata = array(
							                	'user_id' => $last_exchange->user_id,
							                    'bid_id' => $last_exchange->id,
							                    'bid_type' => $last_exchange->bid_type,
							                    'total_amount' => $seller_complete_qty_log*$last_exchange->bid_price,
							                    'total_amount_usd' => $total_amount_usd,
							                    'paid_date' => date("Y-m-d H:i:s"),
							                    'fee_percentage' => $last_exchange->fee_perc,
							                    'fee_amount' => $sell_fee_amt,
							                    'fee_in_usd' => $fee_in_usd,
							                    'currency' => $coin_symbol[1],
							                    'status'  => 1,
							                    "order_completed_at"=>date("Y-m-d H:i:s"),
						            			"updated_at"=>date("Y-m-d H:i:s")
							                );
												FeeDeductions::create($fdata);
												Log::info("sell trade5 ".json_encode($updata));
	                                	}
											
											
	                                }

	                                $check_seller_balance = Balance::select('*')->where('user_id', $last_exchange->user_id)->where('currency_symbol', $coin_symbol[1])->first();

	                                if ($check_seller_balance===null) {
	                                    $user_balance = array(
	                                        'user_id'           => $last_exchange->user_id, 
	                                        'currency_symbol'   => $coin_symbol[1], 
	                                        'balance'           => $seller_complete_qty_log*$last_exchange->bid_price-$sell_fee_amt, 
	                                        'last_update'       => date('Y-m-d H:i:s'), 
	                                    );
	                                    Balance::insert($user_balance);

	                                }else{
	                                    
										$sellerBalUpdate = $seller_complete_qty_log*$last_exchange->bid_price-$sell_fee_amt;
										
										Balance::where('user_id', $last_exchange->user_id)->where('currency_symbol', $coin_symbol[1])->increment('balance', $sellerBalUpdate);

	                                }
									//Log::info("sell match engine after biding update5");
									
									$where01 = ["bid_type"=>"BUY","status"=>1,"market_symbol"=>$last_exchange->market_symbol];
	                                
									Log::info("after before coinhistory1");
									
									$h24_coin_supply        = BidingLog::select(DB::raw('SUM(complete_qty) as complete_qty'))->where('success_time','>=',\DB::raw('DATE_SUB(NOW(), INTERVAL 24 HOUR)'))->where($where01)->first();
Log::info("after before coinhistory2");
	                                                               	                       
	                                //$total_all_coin_supply = @$seller_complete_qty_log+@$total_coin_supply->complete_qty;
	                                
	                                $latestdata=LatestTradeData::where('market_symbol', $last_exchange->market_symbol)->first();  
	                                Log::info("after before coinhistory3");
	                                if($latestdata===null){
	                                	Log::info("after before coinhistory4");
	                                	$total_coin_supply      = BidingLog::select(DB::raw('SUM(complete_qty) as complete_qty'))->where($where01)->first();
	                                	$total_all_coin_supply =@$total_coin_supply->complete_qty;
	                                	$volumeFrom=@$total_coin_supply->complete_qty-@$seller_complete_qty_log;
	                                	$coinpricedata =	Coinhistory::where('date','like',date("Y-m-d")."%")->where('market_symbol', $last_exchange->market_symbol)->orderBy('date','asc')->first();
Log::info("after before coinhistory5");
		                               	$open=0;
		                               	$close=0;
		                              	$open_value=0;
		                               	$coinpricedata2 =	Coinhistory::select('last_price','change_perc','close','open')->where('market_symbol', $last_exchange->market_symbol)->orderBy('date','desc')->first();
		                               
		                               	if($coinpricedata===null){
			                               	
			                               	if($coinpricedata2!==null){
												$open=@$coinpricedata2->close;
												$open_value=$open;
											}else{
												$open=0;
		                               			$open_value=$open;
											}
		                               }else{
		                               		$open=@$coinpricedata->open;
											$open_value=$coinpricedata2->last_price;
		                               }
	                                }else{
	                                	//Log::info("after before coinhistory6 ".json_encode($latestdata));

	                                	$updatedat=date("Y-m-d",strtotime($latestdata['updated_at']));
	                                	$open_value=$latestdata['last_price'];
	                                	if($updatedat==date("Y-m-d")){
	                                		//Log::info("after before coinhistory9 ");
	                                		$open=$latestdata['day_open_price'];
											
	                                	}else{
	                                		//Log::info("after before coinhistory10 ");
	                                		$open=$latestdata['close'];
	                                	}
	                                	$total_all_coin_supply = @$seller_complete_qty_log+$latestdata['total_coin_supply'];
	                                	$volumeFrom =$latestdata['total_coin_supply'];
	                                }
	                               //Log::info("after before coinhistory7");
	                               $change_perc=0;
	                               	if($open>0){
	                               		$change_perc=(($last_exchange->bid_price-$open)/$open)*100;
	                               		$change_perc=round($change_perc,2);
	                               	}
	                               

	                               $volume_24h=(@$h24_coin_supply->complete_qty=='')?0:$h24_coin_supply->complete_qty;
	                               //Log::info("after before coinhistory");
	                               $latest_usd_price=$last_exchange->bid_price*$bc_usd_price->coin_price;
	                                $coinhistory = array(
	                                	"order_id"=>$orderId,
	                                    'coin_symbol'       => $last_exchange->currency_symbol,
	                                    'market_symbol'     => $last_exchange->market_symbol,
	                                    'usd_price'			=> $latest_usd_price,
	                                    'last_price'        => $last_exchange->bid_price,
	                                    'total_coin_supply' => $total_all_coin_supply,
	                                    'volume_24h'        => $volume_24h,
	                                    'day_open_price'	=> $open,
	                                    'open'              => $open_value,
	                                    'close'             => $last_exchange->bid_price,
	                                    'volumefrom'        => $volumeFrom,
	                                    'volumeto'          => $total_all_coin_supply,
	                                    'change_perc'=>$change_perc,
	                                    'date'              => date('Y-m-d H:i:s'),
	                                );

									//Log::info("after before coinhistory ".json_encode($coinhistory));
	                                Coinhistory::insert($coinhistory);
	                                //Log::info("sell matchengine coinhistory inserted ".$exchange_id);  
	                                if($buyexchange->user_id!=$user_id){
										RabbitmqController::bal_order_list_history(json_encode(array("market_symbol"=>$last_exchange->market_symbol,"user_id"=>$buyexchange->user_id,"latest_bidding_log_orders"=>[$orderId])));
									}

									
	                   self::pairingDataForPublish($last_exchange->market_symbol,$last_exchange->bid_price,$total_all_coin_supply,$change_perc,$volume_24h);
	                                //Log::info("after pairing");
	                            }else{
	                            	//$aftermatch=1;
	                            	break;
	                            }
	                        }else{
	                        	//$aftermatch=1;
	                        	break;
	                        }
                            //Order running

                        }
                        //Order list in loop

                    }
                    //Log::info("afte matchengine");
					if($match==1){
						RabbitmqController::bal_order_list_history(json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id,"latest_bidding_log_orders"=>$ordersArr)));
						//self::bal_order_list_history_publish($last_exchange->market_symbol,$user_id);
						RabbitmqController::buy_sell_trade_exchange(json_encode(array("market_symbol"=>$market_symbol,"latest_bidding_log_orders"=>$ordersArr)));
					}else{
						RabbitmqController::bal_order_list(json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$user_id)));
						//self::bal_order_list_history_publish($last_exchange->market_symbol,$user_id);
						RabbitmqController::buy_sell(json_encode(array("market_symbol"=>$market_symbol)));
					}
					
	}

	
    
}
