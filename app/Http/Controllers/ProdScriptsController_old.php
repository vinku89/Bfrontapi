<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
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
use App\Library\CurlRequest;
use App\Withdraw;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

use App\Events\WebsocketDemoEvent;
use App\ApiSessions;
use App\Coinhistory;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\ExchangeController;
use App\Biding;
use App\Http\Controllers\rabbitmq\RabbitmqController;
use App\ReferralBonus;
use App\CryptoValues;
use App\CryptoValueHistory;
class ProdScriptsController extends Controller {
    public static function rand_float($st_num=0,$end_num=1,$mul=1000000)
    {
        if ($st_num>$end_num) return false;
        return mt_rand($st_num*$mul,$end_num*$mul)/$mul;
    }
    public static function realtime_selected_trade_cron_sell_buy(){

        Log::info("realtime_selected_trade_cron_sell_buy start");
        //$perc=self::rand_float(0.001,1);
        $qty_perc=0.001;
        $buy_user_id=1;
        $sell_user_id=1;
        //$crypto=['BTC','EVR'];
        //DB::enableQueryLog();
        $buy_sell=["BUY","SELL"];
        $coinList = CoinListing::whereIn("coin_symbol",['BTC','ETH','EVR','TRU-E','USDT'])->where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_name", "ASC")->get()->toArray();
        //$coinList = CoinListing::whereIn("coin_symbol",['ETH'])->where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_name", "ASC")->get()->toArray();
        //dd(DB::getQueryLog());exit;
        //print_r($coinList);exit;
        foreach($coinList as $cv){
            
            if($cv['coin_symbol']=="BTC"){
                $trading_pairs=["EVR/".$cv['coin_symbol']];
            }else if($cv['coin_symbol']=="ETH"){
                $trading_pairs=["EVR/".$cv['coin_symbol']];
            }else if($cv['coin_symbol']=="TRU-E"){
                $trading_pairs=["EVR/".$cv['coin_symbol']];
            }else if($cv['coin_symbol']=="USDT"){
                $trading_pairs=["EVR/".$cv['coin_symbol']];
            }else if($cv['coin_symbol']=="EVR"){
                $trading_pairs=["ETH/".$cv['coin_symbol'],"BTC/".$cv['coin_symbol'],"LTC/".$cv['coin_symbol'],"USDT/".$cv['coin_symbol']];
            }
            
            $coin_id = $cv['id'];
            $cryptolistfrom = $cv['coin_symbol']; 
            $basePairingCurrency = BaseCurrencyPairing::whereIn("trading_pairs",$trading_pairs)->where("status","=",1)->orderBy('trading_pairs')->get();
            if(@count($basePairingCurrency) > 0){
               
                foreach($basePairingCurrency as $pair){
                    $pair_id = $pair['pairing_id'];
                   
                    
                    $coinList_pair = CoinListing::where("id","=",$pair_id)->where("status","=",1)->first();
                    if($coinList_pair!==null){

                        $cryptolistto = $coinList_pair['coin_symbol'];
                        $market_symbol = $cryptolistto."_".$cryptolistfrom;

                        $coinprice=$coinList_pair['coin_price'];
                        $base_coinprice=$cv['coin_price'];
                        $mprice=floatval($coinprice)/floatval($base_coinprice);
                        $mrprice=number_format_eight_dec($mprice);
                        $market_price=str_replace(",","", $mrprice);
                       
                        
                        //$price_ten_perc=($market_price*1)/100;
                        $buy_price=floatval($market_price);
                        //$sell_price=$market_price+$price_ten_perc;
                        //$sell_price=$buy_price;
                        //$perc=self::rand_float(0.001,1);
                        $perc=0;
                            $perc_price=($buy_price*$perc)/100;
                            $buy_price=$buy_price+$perc_price;
                            $market_price_usd=floatval($buy_price)*floatval($base_coinprice);
                            if($market_price_usd>1000){
                                $qty=self::rand_float(0.0001,0.001);
                            }else if($market_price_usd>100 && $market_price_usd<1000){
                                $qty=self::rand_float(0.001,0.01);
                            }else if($market_price_usd>1 && $market_price_usd<100){
                                $qty=self::rand_float(0.01,0.1);
                            }else if($market_price_usd>0.1 && $market_price_usd<1){
                                $qty=self::rand_float(0.1,100);
                            }else if($market_price_usd<0.1){
                                $qty=self::rand_float(100,200);
                            }
                            $randomSelected = $buy_sell[rand(0,(count($buy_sell)-1))];
                            if($randomSelected=="SELL"){
                                //Log::info("first sell executed");
                                $sell_price=$buy_price;
                                
                                $coin_symbol        = explode('_', $market_symbol);
                                
                                $amount_withoutfees = $sell_price * $qty;
                                
                                $amount_withfees    = $amount_withoutfees;
                                //Log::info("cron realtime_selected_trade_cron_sell_buy sell check bal");
                                $rec_balance_c0         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[0]);
                                $rec_balance_c1         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[1]);

                                //Pending Withdraw amoun sum
                                //$rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $sell_user_id)->first();

                                //Discut user withdraw pending balance
                                //$rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;
                                $rec_real_balance = (float)@$rec_balance_c0->balance;

                                if (@$rec_real_balance >= $qty && @$rec_balance_c0->balance>0 && $qty>0) {

                                    //$date       = new DateTime();
                                    $rec_open_date  = date('Y-m-d H:i:s');

                                    $rec_tdata['TRADES']   = (object)$rec_exchangedata = array(
                                        'bid_type'          => 'SELL',
                                        'bid_price'         => $sell_price,
                                        'bid_qty'           => $qty,
                                        'bid_qty_available' => $qty,
                                        'total_amount'      => $sell_price*$qty,
                                        'amount_available'  => $sell_price*$qty,
                                        'currency_symbol'   => $coin_symbol[0],
                                        'market_symbol'     => $market_symbol,
                                        'user_id'           => $sell_user_id,
                                        'open_order'        => $rec_open_date,
                                        'fees_amount'       => 0,
                                        'status'            => 2
                                    );
                                    $rec_info = Biding::create($rec_exchangedata);
                                    $rec_exchange_id = $rec_info->id;
                                    //Exchange Data Insert
                                    if ($rec_exchange_id ) {                   

                                        $rec_last_exchange   =  Biding::where("id","=",$rec_exchange_id)->first(); //$this->web_model->single($exchange_id);
                                        //User Balance Debit(-) C0
                                        ExchangeController::balanceDebit1($rec_last_exchange);
                                        
                                        $rec_rabitmqData = json_encode(array("exchange_id"=>$rec_exchange_id,"user_id"=>$sell_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"sell"));
                                        //Log::info("sell publish data ".$rec_exchange_id);
                                        
                                        RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');
                                        Log::info("sell publish data2 ".$rec_exchange_id);
                                        //Log::info("realtime_selected_trade_cron_sell_buy sell publish data ");
                                                            
                                       
                                       }else{
                                        Log::info("realtime_selected_trade_cron_sell_buy sell data failed ");
                                    }

                                }else{
                                    Log::info("realtime_selected_trade_cron_sell_buy sell data insufficient bal failed ");

                                }
                                sleep(1);
                                $coin_symbol        = explode('_', $market_symbol);
                                
                                $amount_withoutfees = $buy_price * $qty;
                                
                                $amount_withfees    = $amount_withoutfees;
                                //Log::info("cron realtime_selected_trade_cron_sell_buy check bal");
                                //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                                $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                                $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                                //$pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first(); 
                                //Log::info("cron realtime_selected_trade_cron_sell_buy withdraw bal");
                                //Discut user withdraw pending balance
                                //$real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;

                                $real_balance = (float)@$balance_c1->balance;

                                if ($real_balance >= $amount_withfees && @$balance_c1->balance>0 && $amount_withfees>0) {

                                    //$date       = new DateTime();
                                    $open_date  = date('Y-m-d H:i:s');
                                    
                                    $tdata['TRADES']   = (object)$exchangedata = array(
                                        'bid_type'          => 'BUY',
                                        'bid_price'         => $buy_price,
                                        'bid_qty'           => $qty,
                                        'bid_qty_available' => $qty,
                                        'total_amount'      => $amount_withoutfees,
                                        'amount_available'  => $amount_withoutfees,
                                        'currency_symbol'   => $coin_symbol[0],
                                        'market_symbol'     => $market_symbol,
                                        'user_id'           => $buy_user_id,
                                        'open_order'        => $open_date,
                                        'fees_amount'       => 0,
                                        'status'            => 2
                                    );
                                    $info = Biding::create($exchangedata);
                                    $exchange_id = $info->id;
                                    //Exchange Data Insert
                                    if ($exchange_id ) {

                                        $last_exchange = Biding::where("id","=",$exchange_id)->first(); 

                                        //User Balance Debit(-) C1
                                        ExchangeController::balanceDebit($last_exchange, $coin_symbol[1]);
                                        //Log::info("buy publish data ".$exchange_id);
                                        
                                        $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));

                                        RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');
                                       Log::info("buy publish data2 ".$exchange_id);
                                        //Log::info("realtime_selected_trade_cron_sell_buy buy publish data ".$exchange_id);
                                                                   
                                    }else{
                                        Log::info("realtime_selected_trade_cron_sell_buy buy data failed ");
                                        
                                    }

                                }else{
                                    Log::info("realtime_selected_trade_cron_sell_buy buy data insufficient bal failed ");
                                   
                                }
                            }
                            if($randomSelected=="BUY"){
                                //Log::info("first buy executed");
                                
                                $coin_symbol        = explode('_', $market_symbol);
                                
                                $amount_withoutfees = $buy_price * $qty;
                                
                                $amount_withfees    = $amount_withoutfees;
                                //Log::info("cron realtime_selected_trade_cron_sell_buy check bal");
                                //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                                $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                                $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                                //$pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first(); 
                                //Log::info("cron realtime_selected_trade_cron_sell_buy withdraw bal");
                                //Discut user withdraw pending balance
                                //$real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;
                                $real_balance = (float)@$balance_c1->balance;

                                if ($real_balance >= $amount_withfees && @$balance_c1->balance>0 && $amount_withfees>0) {

                                    //$date       = new DateTime();
                                    $open_date  = date('Y-m-d H:i:s');
                                    
                                    $tdata['TRADES']   = (object)$exchangedata = array(
                                        'bid_type'          => 'BUY',
                                        'bid_price'         => $buy_price,
                                        'bid_qty'           => $qty,
                                        'bid_qty_available' => $qty,
                                        'total_amount'      => $amount_withoutfees,
                                        'amount_available'  => $amount_withoutfees,
                                        'currency_symbol'   => $coin_symbol[0],
                                        'market_symbol'     => $market_symbol,
                                        'user_id'           => $buy_user_id,
                                        'open_order'        => $open_date,
                                        'fees_amount'       => 0,
                                        'status'            => 2
                                    );
                                    $info = Biding::create($exchangedata);
                                    $exchange_id = $info->id;
                                    //Exchange Data Insert
                                    if ($exchange_id ) {

                                        $last_exchange = Biding::where("id","=",$exchange_id)->first(); 

                                        //User Balance Debit(-) C1
                                        ExchangeController::balanceDebit($last_exchange, $coin_symbol[1]);
                                        //Log::info("realtime_selected_trade_cron_sell_buy pushing into log");
                                        //Log::info("buy publish data ".$exchange_id);
                                        $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                                        RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');
                                       Log::info("buy publish data2 ".$exchange_id);
                                        //Log::info("realtime_selected_trade_cron_sell_buy buy publish data ");
                                                                   
                                    }else{
                                        Log::info("realtime_selected_trade_cron_sell_buy buy data failed ");
                                        
                                    }

                                }else{
                                    Log::info("realtime_selected_trade_cron_sell_buy buy data insufficient bal failed ");
                                   
                                }
                                sleep(1);
                                $sell_price=$buy_price;
                                
                                $coin_symbol        = explode('_', $market_symbol);
                                
                                $amount_withoutfees = $sell_price * $qty;
                                
                                $amount_withfees    = $amount_withoutfees;
                                //Log::info("cron realtime_selected_trade_cron_sell_buy sell check bal");
                                $rec_balance_c0         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[0]);
                                $rec_balance_c1         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[1]);

                                //Pending Withdraw amoun sum
                                //$rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $sell_user_id)->first();

                                //Discut user withdraw pending balance
                                //$rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;
                                $rec_real_balance = (float)@$rec_balance_c0->balance;

                                if (@$rec_real_balance >= $qty && @$rec_balance_c0->balance>0 && $qty>0) {

                                    //$date       = new DateTime();
                                    $rec_open_date  = date('Y-m-d H:i:s');

                                    $rec_tdata['TRADES']   = (object)$rec_exchangedata = array(
                                        'bid_type'          => 'SELL',
                                        'bid_price'         => $sell_price,
                                        'bid_qty'           => $qty,
                                        'bid_qty_available' => $qty,
                                        'total_amount'      => $sell_price*$qty,
                                        'amount_available'  => $sell_price*$qty,
                                        'currency_symbol'   => $coin_symbol[0],
                                        'market_symbol'     => $market_symbol,
                                        'user_id'           => $sell_user_id,
                                        'open_order'        => $rec_open_date,
                                        'fees_amount'       => 0,
                                        'status'            => 2
                                    );
                                    $rec_info = Biding::create($rec_exchangedata);
                                    $rec_exchange_id = $rec_info->id;
                                    //Exchange Data Insert
                                    if ($rec_exchange_id ) {                   

                                        $rec_last_exchange   =  Biding::where("id","=",$rec_exchange_id)->first(); //$this->web_model->single($exchange_id);
                                        //User Balance Debit(-) C0
                                        ExchangeController::balanceDebit1($rec_last_exchange);
                                        //Log::info("sell publish data ".$rec_exchange_id);
                                        $rec_rabitmqData = json_encode(array("exchange_id"=>$rec_exchange_id,"user_id"=>$sell_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"sell"));
                                        //Log::info("sell publish data");
                                        
                                        RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');
                                        Log::info("sell publish data2 ".$rec_exchange_id);
                                        //Log::info("realtime_selected_trade_cron_sell_buy sell publish data ");
                                                            
                                       
                                       }else{
                                        Log::info("realtime_selected_trade_cron_sell_buy sell data failed ");
                                    }

                                }else{
                                    Log::info("realtime_selected_trade_cron_sell_buy sell data insufficient bal failed ");

                                }
                            }
                           $sec= rand(2,4);
                      sleep($sec);
                    }
                   
                }
            }
        }
    
    }
}