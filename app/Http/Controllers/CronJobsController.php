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
use App\BrexcoTransactions;
use App\Http\Controllers\API\BrexcoController;
use App\Referrals;
use App\Http\Controllers\API\DepositController;
use App\Library\NodeApiCalls;
use App\UserAddresses;
use App\EverusAddresses;
use App\BrexcoPackages;
use App\Country;
class CronJobsController extends Controller {
    private static $proCoinMarketCapAPI = "https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest";
     private static $coinmarketcap_key="ca72c2ce-f193-447a-94ef-afc3d1ea480a";//"82d0b54c-ff0c-4fa8-8f72-2296934b46cf";
    public static function cronForCryptoUsdValue(){

        $coinList = CoinListing::where("status","=",1)->orderBy("coin_symbol", "ASC")->get();
        foreach($coinList as $token){
            if($token['coin_symbol']!="TRU-E"){
                $coinname=$token['coingecko_id'];
                if($coinname!=""){


                    Log::info("coingecko url ".'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids='.$coinname);
                    $ch = curl_init('https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids='.$coinname);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    // get the (still encoded) JSON data:
                    $json = curl_exec($ch);
                    curl_close($ch);
                    Log::info("coingecko api ".$json);
                    // Decode JSON response:
                    $conversionResult = json_decode($json, true);
                    if(!empty($conversionResult)){

                        $token_value=$conversionResult[0]['current_price'];
                        $token->coin_price = $token_value;
                        $token->updated_at = Carbon::now();
                        $token->save();
                        $update_Array = array('crypto_id'=> $token['id'],"fiat_type" => 'USD','fiat_value'=>$token_value,'updated_at'=>date("Y-m-d H:i:s"));
                        CryptoValues::updateOrCreate(['crypto_id'=> $token['id']], $update_Array);

                        CryptoValueHistory::insert(array(
                            'crypto_name'=>$token['coin_name'],
                            'crypto_symbol'=>$token['coin_symbol'],
                            'fiat_value'=>$token_value,
                            'fiat_type'=>"USD",

                            'created_at'=>date("Y-m-d H:i:s")
                        ));
                        Log::info($token['coin_symbol']." value ".$token_value." cron success");
                    }else{
                        Log::info($token['coin_symbol']." value cron failed");
                    }
                }else{
                    Log::info(" empty coin id");
                }
                /*$response = self::curlCall(self::$proCoinMarketCapAPI,['acceptHeader' => 'application/json','X-CMC_PRO_API_KEY'=>self::$coinmarketcap_key])->makeGetRequest('/',['symbol'=>strtoupper($token['coin_symbol'])]);
                $exchangeRates = json_decode($response->getBody());
                Log::info($token['coin_symbol']." value ".json_encode($exchangeRates));
                $jsondata=json_encode($exchangeRates);
                if($jsondata!="null"){
                    if($exchangeRates->status->error_code==0){

                        $cryptoName=strtoupper($token['coin_symbol']);
                        $token_value = $exchangeRates->data->$cryptoName->quote->USD->price;
                        $token->coin_price = $token_value;
                        $token->updated_at = Carbon::now();
                        $token->save();

                        $update_Array = array('crypto_id'=> $token['id'],"fiat_type" => 'USD','fiat_value'=>$token_value,'updated_at'=>date("Y-m-d H:i:s"));
                        CryptoValues::updateOrCreate(['crypto_id'=> $token['id']], $update_Array);

                        CryptoValueHistory::insert(array(
                            'crypto_name'=>$token['coin_name'],
                            'crypto_symbol'=>$token['coin_symbol'],
                            'fiat_value'=>$token_value,
                            'fiat_type'=>"USD",

                            'created_at'=>date("Y-m-d H:i:s")
                        ));
                        Log::info($token['coin_symbol']." value ".$token_value." cron success");
                    }else{
                        Log::info($token['coin_symbol']." value cron failed");
                    }


                }else{
                    Log::info($token['coin_symbol']." value cron failed");
                }*/
            }

        }
    }
    /*public static function cronForCryptoUsdValue(){
        $coinList = CoinListing::where("status","=",1)->where('is_base_currency',1)->orderBy("coin_symbol", "ASC")->get();
        foreach($coinList as $token){
            $convertValue=self::currencyConvertUsingApilayer($token['coin_symbol'],"USD",1);
            if($convertValue!="null"){
                $cryptoName=strtoupper($token['coin_symbol']);
                $token_value = $convertValue;
                $token->coin_price = $token_value;
                $token->updated_at = Carbon::now();
                $token->save();

            }else{
                Log::info($token['symbol']." value cron failed");
            }
        }
    }*/
    public static function curlCall($baseurl,array $headers = [])
    {
        if(empty($headers)){
            $acceptHeader=self::$acceptHeader;
        }else{
            $acceptHeader= $headers['acceptHeader'];
        }

        return CurlRequest::getNewInstance($baseurl, $headers + ['Content-type' => $acceptHeader]);
    }

    public static function currencyConvertUsingApilayer($from_currency,$to_currency="USD",$amount=1){
        $endpoint = 'convert';
        $access_key = config("constants.API_LAYER_ACCESSKEY"); //'fe67fff72d0a8f104b1044d8dfea650d';
        Log::info( 'https://api.currencylayer.com/'.$endpoint.'?access_key=xxxxxxxx'.'&from='.$from_currency.'&to='.$to_currency.'&amount='.$amount);
        // initialize CURL:
        $ch = curl_init('https://api.currencylayer.com/'.$endpoint.'?access_key='.$access_key.'&from='.$from_currency.'&to='.$to_currency.'&amount='.$amount.'');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // get the (still encoded) JSON data:
        $json = curl_exec($ch);
        curl_close($ch);
        Log::info("currency layer cron ".date("Y-m-d H:i:s"));
        // Decode JSON response:
        $conversionResult = json_decode($json, true);
        // access the conversion result
        if($conversionResult['success'] == 1){
            $convertToUSD =  $conversionResult['result'];
        }else{
            $convertToUSD ="";
        }
        return $convertToUSD;
    }

    public static function redis_refresh_cron(){
        Log::info("redis refresh start");
        Redis::flushDb();
        //$dataarr = Redis::lrange('coins',0,100);
        //$dataarr = json_decode($dataarr);
        //print_r($dataarr);exit;
            //$allKeys = Redis::keys('*');
            //return response()->json(["Success"=>true,'status' => 200,'Result' => $dataarr], 200);
            $coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_name", "ASC")->get()->toArray();
            $mdata['coins']=array();
            if(@count($coinList)>0){
                Log::info("redis refresh if ");
            foreach ($coinList as $ck => $cv) {
                Log::info("redis refresh for loop start ");
                Redis::rpush("coins",$cv['coin_symbol']);
                Redis::rpush("info:".$cv['coin_symbol'], json_encode($cv));
                 //$mdata['coins'][$cv['coin_symbol']]=$cv;


                   $coin_id = $cv['id'];
                    $cryptolistfrom = $cv['coin_symbol'];
                    $basePairingCurrency = BaseCurrencyPairing::where("coin_id","=",$coin_id)->where("status","=",1)->orderBy('trading_pairs')->get();
                    $pairingarr=array();
                    if(@count($basePairingCurrency) > 0){
                        Log::info("redis refresh base currecny if ");
                        $x=0;
                        foreach($basePairingCurrency as $pair){
                            Log::info("redis refresh base currency for loop");
                            $pairingArrPrice = array();
                            $pair_id = $pair['pairing_id'];

                            //get cv
                            $coinList_pair = CoinListing::where("id","=",$pair_id)->where("status","=",1)->first();

                            if(@count($coinList_pair) > 0){
                                Log::info("redis refresh base currency coinlist if");
                                $cryptolistfrom = $cv['coin_symbol'];
                                $cryptolistto = $coinList_pair['coin_symbol'];


                                //$base_piar = $cryptolistfrom."_".$cryptolistto;
                                $base_piar = $cryptolistto."_".$cryptolistfrom;
                                //$pairingArrPrice['pair']=$pair;
                                $market_data = Coinhistory::select("*")->where("market_symbol","=",$base_piar)->orderBy("id","DESC")->LIMIT(1)->first();
                                if($market_data!==null){
                                Log::info("redis refresh market data if");
                                    $price = $market_data->last_price;
                                    //$volume = round($market_data->total_coin_supply*100)/100;

                                    $total_volume = ($market_data->volume_24h)?$market_data->volume_24h:0;
                                    $volume=floatval($price)*floatval($total_volume);
                                    $change = $market_data->change_perc;
                                    $mdateArr=explode(" ", $market_data->date);
                                    $tdate=date("Y-m-d");
                                    $last24hdate=date("Y-m-d H:i:s", strtotime("-24 hour"));
                                    if($mdateArr[0]!=$tdate){
                                        $change=0.00;
                                    }
                                    if($market_data->date>$last24hdate){
                                        $volume=floatval($price)*floatval($total_volume);
                                    }else{
                                        $volume=0.00;
                                    }

                                    $price_change_percent = $change;
                                    $pairingArrPrice["coin_id"]=$coinList_pair['id'];
                                    $pairingArrPrice["coin_name"]=$coinList_pair['coin_name'];
                                    $pairingArrPrice["coin_symbol"]=$coinList_pair['coin_symbol'];
                                    $pairingArrPrice["market_symbol"]=$coinList_pair['coin_symbol']."_".$cv['coin_symbol'];
                                    $pairingArrPrice["coin_image"]=$coinList_pair['coin_image'];
                                    $pairingArrPrice["market_price"]=number_format_eight_dec($price);
                                    $fomatvolume=number_format_four_dec($volume);
                                    $roundvolume=str_replace(",", "", $fomatvolume);
                                    $pairingArrPrice["volume"]=$roundvolume;
                                    $pairingArrPrice["change"]=number_format_two_dec($price_change_percent);


                                }else{
                                    Log::info("redis refresh market data else");
                                    $pairingArrPrice["coin_id"]=$coinList_pair['id'];
                                    $pairingArrPrice["coin_name"]=$coinList_pair['coin_name'];
                                    $pairingArrPrice["coin_symbol"]=$coinList_pair['coin_symbol'];
                                    $pairingArrPrice["market_symbol"]=$coinList_pair['coin_symbol']."_".$cv['coin_symbol'];
                                    $pairingArrPrice["coin_image"]=$coinList_pair['coin_image'];
                                    if($cv['coin_price']==0){
                                        $coinprice=0;
                                    }else{
                                        $coinprice=floatval($coinList_pair['coin_price'])/floatval($cv['coin_price']);
                                    }

                                    $pairingArrPrice["market_price"]=number_format_eight_dec($coinprice);
                                    $pairingArrPrice["volume"]="0.00";
                                    $pairingArrPrice["change"]="0.00";

                                }
                                $pairingarr[]=$pairingArrPrice;
                                Log::info("pairing:".$cv['coin_symbol']." : ".json_encode($pairingArrPrice));
                                Redis::set('coinspairs:'.$coinList_pair['coin_symbol']."_".$cv['coin_symbol'], $x );
                                Redis::rpush("pairing:".$cv['coin_symbol'], json_encode($pairingArrPrice));
                                // $mdata['coins'][$cv['coin_symbol']]["pairing"]=$pairingArrPrice;
                               $x++;
                            }
                        }
                            //Redis::rpush("pairing:".$cv['coin_symbol'], json_encode($pairingarr));

                    }


                }

            }


        Redis::persist('coins');
            $allKeys = Redis::keys('*');
            return response()->json(["Success"=>true,'status' => 200,'Result' => $allKeys], 200);

    }

    public static function expiresNodeApiAuth() {

        $apiAuths = ApiSessions::getNodeApiAuths();
        if(!empty($apiAuths)){
            foreach($apiAuths as $auth){
                $auth_obj = ApiSessions::where('session_id', $auth->session_id)->first();
                Log::info("expires node auth");
                if($auth_obj!==null){
                    $auth_obj->is_expired = 1;
                    $auth_obj->save();
                }

            }
        }
    }
    public static function rand_float($st_num=0,$end_num=1,$mul=1000000)
    {
        if ($st_num>$end_num) return false;
        return mt_rand($st_num*$mul,$end_num*$mul)/$mul;
    }
    public static function script_run(){
        Log::info("cron script_run start");

        $perc=0.001;

        $qty_perc=0.001;
        //$market_symbol="BTC_EVR";
        $buy_user_id=1;
        $rec_user_id=71;
        /*$coinpricelatestdata =  Biding::select(DB::raw('MAX(bid_price) as bid_price'))->where('market_symbol', $market_symbol)->where('status',2)->first();
        $price=$coinpricelatestdata->bid_price;
        for($i=0;$i<50;$i++){
            //sleep(3);
            $perc_price=($price*$perc)/100;
            $price=$price-$perc_price;*/
        /*$coinpricelatestdata =  Biding::where('market_symbol', $market_symbol)->orderBy('open_order','desc')->limit(1)->first();
        $price=$coinpricelatestdata->bid_price;
        $qty=$coinpricelatestdata->bid_qty;*/
        //Log::info($coinpricelatestdata->last_price);exit;
        /*$coinpricelatestdata =  Biding::select(DB::raw('MAX(bid_price) as bid_price'))->where('market_symbol', $market_symbol)->where('status',2)->first();
        $price=$coinpricelatestdata->bid_price;*/
        $crypto=['BTC','EVR'];
        foreach($crypto as $cr){
            if($cr=="BTC"){
                $pairings=array(
                    array('pair'=>'BNB_BTC'),array('pair'=>'ETH_BTC'),array('pair'=>'EVR_BTC'),array('pair'=>'LTC_BTC'));
            }
            /*if($cr=="ETH"){
                $pairings=array(array('pair'=>'REP_ETH','qty'=>self::rand_float(0.001,0.003)),array('pair'=>'BAT_ETH','qty'=>self::rand_float(0.000002,0.000010)),array('pair'=>'BCH_ETH','qty'=>self::rand_float(0.02,0.05)),array('pair'=>'BNB_ETH','qty'=>self::rand_float(0.0015,0.004)),array('pair'=>'BTM_ETH','qty'=>self::rand_float(0.000007,0.000020)),array('pair'=>'CHZ_ETH','qty'=>self::rand_float(0.0000016,0.00003)),array('pair'=>'CRO_ETH','qty'=>self::rand_float(0.000002,0.000005)),array('pair'=>'BTC_ETH','qty'=>self::rand_float(48.87,55)),array('pair'=>'EVR_ETH','qty'=>self::rand_float(0.00016,0.0003)),array('pair'=>'HEDG_ETH','qty'=>self::rand_float(0.00048,0.0008)),array('pair'=>'LINK_ETH','qty'=>self::rand_float(0.00045,0.009)),array('pair'=>'LTC_ETH','qty'=>self::rand_float(0.0045,0.009)),array('pair'=>'QTUM_ETH','qty'=>self::rand_float(0.00017,0.00039)));
            }*/
            if($cr=="EVR"){
                $pairings=array(array('pair'=>'BNB_EVR'),array('pair'=>'BTC_EVR'),array('pair'=>'ETH_EVR'),array('pair'=>'LTC_EVR'));
            }

            foreach($pairings as $pr){
                $market_symbol=$pr['pair'];
                $market_data = Coinhistory::select("*")->where("market_symbol","=",$market_symbol)->orderBy("id","DESC")->LIMIT(1)->first();
                $market_price=$market_data['last_price'];
                $price_ten_perc=($market_price*10)/100;
                $buy_price=$market_price-$price_ten_perc;
                $sell_price=$market_price+$price_ten_perc;
                for($i=0;$i<250;$i++){
                    $perc_price=($buy_price*$perc)/100;
                    $buy_price=$buy_price-$perc_price;
                    if($buy_price>1000){
                        $qty=self::rand_float(0.0001,0.001);
                    }else if($buy_price>100 && $buy_price<1000){
                        $qty=self::rand_float(0.001,0.01);
                    }else if($buy_price>1 && $buy_price<100){
                        $qty=self::rand_float(0.01,0.1);
                    }else if($buy_price>0.1 && $buy_price<1){
                        $qty=self::rand_float(0.1,100);
                    }else if($buy_price<0.1){
                        $qty=self::rand_float(100,200);
                    }
                    $coin_symbol        = explode('_', $market_symbol);
                    $rate               = $price;

                    $amount_withoutfees = $rate * $qty;

                    $amount_withfees    = $amount_withoutfees;
                    Log::info("cron script_run check bal");
                    //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                    $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                    $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                    $pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first();
                    Log::info("cron script_run withdraw bal");
                    //Discut user withdraw pending balance
                    $real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;


                    if ($real_balance >= $amount_withfees && @$balance_c1->balance>0 && $amount_withfees>0) {

                        //$date       = new DateTime();
                        $open_date  = date('Y-m-d H:i:s');

                        $tdata['TRADES']   = (object)$exchangedata = array(
                            'bid_type'          => 'BUY',
                            'bid_price'         => $rate,
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
                            Log::info("pushing into log");

                            $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                            RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');
                            RabbitmqController::buyorders(json_encode(array("market_symbol"=>$market_symbol)));
                            RabbitmqController::orderslist_broadcast(json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$buy_user_id)));
                            Log::info("buy publish data ");
                            //self::BuyTradeMatchingEngine($exchange_id,$user_id,$market_symbol,$coin_symbol);


                        }else{
                            Log::info("buy data failed ");

                        }

                    }else{
                        Log::info("buy data insufficient bal failed ");

                    }


                }


                //$qty=self::rand_float(0,2)/10000;


                //sleep(3);

                $rec_balance_c0         = ExchangeController::checkBalance($rec_user_id,$coin_symbol[0]);
                $rec_balance_c1         = ExchangeController::checkBalance($rec_user_id,$coin_symbol[1]);

                //Pending Withdraw amoun sum
                $rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $rec_user_id)->first();

                //Discut user withdraw pending balance
                $rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;

                if (@$rec_real_balance >= $qty && @$rec_balance_c0->balance>0 && $qty>0) {

                    //$date       = new DateTime();
                    $rec_open_date  = date('Y-m-d H:i:s');

                    $rec_tdata['TRADES']   = (object)$rec_exchangedata = array(
                        'bid_type'          => 'SELL',
                        'bid_price'         => $rate,
                        'bid_qty'           => $qty,
                        'bid_qty_available' => $qty,
                        'total_amount'      => $rate*$qty,
                        'amount_available'  => $rate*$qty,
                        'currency_symbol'   => $coin_symbol[0],
                        'market_symbol'     => $market_symbol,
                        'user_id'           => $rec_user_id,
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

                        $rec_rabitmqData = json_encode(array("exchange_id"=>$rec_exchange_id,"user_id"=>$rec_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"sell"));
                        //Log::info("sell publish data");

                        RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');
                        RabbitmqController::sellorders(json_encode(array("market_symbol"=>$market_symbol)));
                        RabbitmqController::orderslist_broadcast(json_encode(array("market_symbol"=>$market_symbol,"user_id"=>$rec_user_id)));
                        Log::info("sell publish data ");

                        //After balance discut(-)
                       }else{
                        Log::info("sell data failed ");
                    }

                }else{
                    Log::info("sell data insufficient bal failed ");

                }

            }
        }
    }
    public static function script_run1(){
        Log::info("cron script_run1 start");

        $perc=0.001;

        $qty_perc=0.001;
        $market_symbol="BTC_EVR";
        $buy_user_id=71;
        $rec_user_id=1;
        $coinpricelatestdata =  Biding::select(DB::raw('MIN(bid_price) as bid_price'))->where('market_symbol', $market_symbol)->where('status',2)->first();
        $price=$coinpricelatestdata->bid_price;

        for($i=0;$i<5;$i++){
            $perc_price=($price*$perc)/100;
            $price=$price-$perc_price;
           /* $perc_qty=($qty*$qty_perc)/100;
            $qty=$qty+$perc_qty;*/
            if($price>1000){
                $qty=self::rand_float(0.0001,0.001);
            }else if($price>100 && $price<1000){
                $qty=self::rand_float(0.001,0.01);
            }else if($price>1 && $price<100){
                $qty=self::rand_float(0.01,0.1);
            }else if($price>0.1 && $price<1){
                $qty=self::rand_float(0.1,100);
            }else if($price<0.1){
                $qty=self::rand_float(100,200);
            }
            //$price=self::rand_float(0,2);


            $coin_symbol        = explode('_', $market_symbol);
            $rate               = $price;

            $amount_withoutfees = $rate * $qty;

            $amount_withfees    = $amount_withoutfees;
            Log::info("cron script_run1 check bal");


            $rec_balance_c0         = ExchangeController::checkBalance($rec_user_id,$coin_symbol[0]);
            $rec_balance_c1         = ExchangeController::checkBalance($rec_user_id,$coin_symbol[1]);

            //Pending Withdraw amoun sum
            $rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $rec_user_id)->first();

            //Discut user withdraw pending balance
            $rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;

            if (@$rec_real_balance >= $qty && @$rec_balance_c0->balance>0 && $qty>0) {

                //$date       = new DateTime();
                $rec_open_date  = date('Y-m-d H:i:s');

                $rec_tdata['TRADES']   = (object)$rec_exchangedata = array(
                    'bid_type'          => 'SELL',
                    'bid_price'         => $rate,
                    'bid_qty'           => $qty,
                    'bid_qty_available' => $qty,
                    'total_amount'      => $rate*$qty,
                    'amount_available'  => $rate*$qty,
                    'currency_symbol'   => $coin_symbol[0],
                    'market_symbol'     => $market_symbol,
                    'user_id'           => $rec_user_id,
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

                    $rec_rabitmqData = json_encode(array("exchange_id"=>$rec_exchange_id,"user_id"=>$rec_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"sell"));
                    //Log::info("sell publish data");

                    RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');
                    Log::info("sell publish data ".$i);

                    //After balance discut(-)
                   }else{
                    Log::info("sell data failed ".$i);
                }

            }else{
                Log::info("sell data insufficient bal failed ".$i);

            }


            //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
            $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
            $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

            $pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first();
            Log::info("cron script_run1 withdraw bal");
            //Discut user withdraw pending balance
            $real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;


            if ($real_balance >= $amount_withfees && @$balance_c1->balance>0 && $amount_withfees>0) {

                //$date       = new DateTime();
                $open_date  = date('Y-m-d H:i:s');

                $tdata['TRADES']   = (object)$exchangedata = array(
                    'bid_type'          => 'BUY',
                    'bid_price'         => $rate,
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
                    Log::info("pushing into log");

                    $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                    RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');

                    Log::info("buy publish data ".$i);
                    //self::BuyTradeMatchingEngine($exchange_id,$user_id,$market_symbol,$coin_symbol);


                }else{
                    Log::info("buy data failed ".$i);

                }

            }else{
                Log::info("buy data insufficient bal failed ".$i);

            }





        }
    }

    public static function realtime_trade_cron(){


        //$perc=self::rand_float(0.001,1);
        $qty_perc=0.001;
        $buy_user_id=1;
        $sell_user_id=1;
        //$crypto=['BTC','EVR'];
        $coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_name", "ASC")->get()->toArray();
        foreach($coinList as $cv){
            /*if($cr=="BTC"){
                $pairings=array(
                    array('pair'=>'BNB_BTC'),array('pair'=>'ETH_BTC'),array('pair'=>'EVR_BTC'),array('pair'=>'LTC_BTC'));
            }
            if($cr=="EVR"){
                $pairings=array(array('pair'=>'BNB_EVR'),array('pair'=>'BTC_EVR'),array('pair'=>'ETH_EVR'),array('pair'=>'LTC_EVR'));
            }*/
            $coin_id = $cv['id'];
            $cryptolistfrom = $cv['coin_symbol'];
            $basePairingCurrency = BaseCurrencyPairing::where("coin_id","=",$coin_id)->where("status","=",1)->orderBy('trading_pairs')->get();
            if(@count($basePairingCurrency) > 0){

                foreach($basePairingCurrency as $pair){
                    $pair_id = $pair['pairing_id'];


                    $coinList_pair = CoinListing::where("id","=",$pair_id)->where("status","=",1)->first();
                    $cryptolistto = $coinList_pair['coin_symbol'];
                    $market_symbol = $cryptolistto."_".$cryptolistfrom;

                    /*if($pair['trading_pairs']=="EVR/BTC" || $pair['trading_pairs']=="EVR/LTC" || $pair['trading_pairs']=="EVR/ETH"){
                        $coinprice_perc=($coinList_pair['coin_price']*25)/100;
                        $coinprice=$coinList_pair['coin_price']+$coinprice_perc;
                    }else{*/
                       $coinprice=$coinList_pair['coin_price'];
                    /*}

                    if($pair['trading_pairs']=="BTC/EVR" || $pair['trading_pairs']=="LTC/EVR" || $pair['trading_pairs']=="ETH/EVR"){
                        $base_coinprice_perc=($cv['coin_price']*25)/100;
                        $base_coinprice=$cv['coin_price']+$base_coinprice_perc;
                    }else{*/
                       $base_coinprice=$cv['coin_price'];
                   // }

                    /*$market_data = Coinhistory::select("*")->where("market_symbol","=",$market_symbol)->orderBy("id","DESC")->LIMIT(1)->first();
                    if($market_data!==null){
                        $market_price=$market_data['last_price'];
                    }else{*/
                        $mprice=floatval($coinprice)/floatval($base_coinprice);
                        $mrprice=number_format_eight_dec($mprice);
                        $market_price=str_replace(",","", $mrprice);
                    // }


                    //$price_ten_perc=($market_price*1)/100;
                    $buy_price=floatval($market_price);
                    //$sell_price=$market_price+$price_ten_perc;
                    //$sell_price=$buy_price;
                    $perc=self::rand_float(0.001,1);
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
                        $coin_symbol        = explode('_', $market_symbol);

                        $amount_withoutfees = $buy_price * $qty;

                        $amount_withfees    = $amount_withoutfees;
                        Log::info("cron script_run check bal");
                        //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                        $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                        $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                        $pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first();
                        Log::info("cron script_run withdraw bal");
                        //Discut user withdraw pending balance
                        $real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;

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
                                Log::info("pushing into log");

                                $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                                RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');

                                Log::info("buy publish data ");

                            }else{
                                Log::info("buy data failed ");

                            }

                        }else{
                            Log::info("buy data insufficient bal failed ");

                        }
                        $sell_price=$buy_price;
                        /*$perc_price=($sell_price*$perc)/100;
                        $sell_price=$sell_price+$perc_price;*/
                        /*if($sell_price>1000){
                            $qty=self::rand_float(0.0001,0.001);
                        }else if($sell_price>100 && $sell_price<1000){
                            $qty=self::rand_float(0.001,0.01);
                        }else if($sell_price>1 && $sell_price<100){
                            $qty=self::rand_float(0.01,0.1);
                        }else if($sell_price>0.1 && $sell_price<1){
                            $qty=self::rand_float(0.1,100);
                        }else if($sell_price<0.1){
                            $qty=self::rand_float(100,200);
                        }*/
                        $coin_symbol        = explode('_', $market_symbol);

                        $amount_withoutfees = $sell_price * $qty;

                        $amount_withfees    = $amount_withoutfees;
                        Log::info("cron script_run check bal");
                        $rec_balance_c0         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[0]);
                        $rec_balance_c1         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[1]);

                        //Pending Withdraw amoun sum
                        $rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $sell_user_id)->first();

                        //Discut user withdraw pending balance
                        $rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;

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
                                //Log::info("sell publish data");

                                RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');

                                Log::info("sell publish data ");


                               }else{
                                Log::info("sell data failed ");
                            }

                        }else{
                            Log::info("sell data insufficient bal failed ");

                        }
                       $sec= rand(3,8);
                  sleep($sec);
                }
            }
        }

    }
    public static function realtime_trade_cron2(){


        //$perc=self::rand_float(0.001,1);
        $qty_perc=0.001;
        $buy_user_id=71;
        $sell_user_id=71;
        $coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_name", "ASC")->get()->toArray();
        foreach($coinList as $cv){

            $coin_id = $cv['id'];
            $cryptolistfrom = $cv['coin_symbol'];
            $basePairingCurrency = BaseCurrencyPairing::where("coin_id","=",$coin_id)->where("status","=",1)->orderBy('trading_pairs')->get();
            if(@count($basePairingCurrency) > 0){

                foreach($basePairingCurrency as $pair){
                    $pair_id = $pair['pairing_id'];


                    $coinList_pair = CoinListing::where("id","=",$pair_id)->where("status","=",1)->first();
                    $cryptolistto = $coinList_pair['coin_symbol'];
                    $market_symbol = $cryptolistto."_".$cryptolistfrom;
                $market_data = Coinhistory::select("*")->where("market_symbol","=",$market_symbol)->orderBy("id","DESC")->LIMIT(1)->first();
                //$market_price=$market_data['last_price'];
                if($market_data!==null){
                    $market_price=$market_data['last_price'];
                }else{
                    $mprice=floatval($coinList_pair['coin_price'])/floatval($cv['coin_price']);
                    $mrprice=number_format_eight_dec($mprice);
                    $market_price=str_replace(",","", $mrprice);
                }
                //$price_ten_perc=($market_price*1)/100;
                $buy_price=floatval($market_price);
                //$sell_price=$market_price+$price_ten_perc;
                //$sell_price=$buy_price;
                $perc=self::rand_float(0.001,1);
                    $perc_price=($buy_price*$perc)/100;
                    $buy_price=$buy_price-$perc_price;
                    $market_price_usd=floatval($buy_price)*floatval($cv['coin_price']);
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

                     $sell_price=$buy_price;

                    $coin_symbol        = explode('_', $market_symbol);

                    $amount_withoutfees = $sell_price * $qty;

                    $amount_withfees    = $amount_withoutfees;
                    Log::info("cron script_run check bal");
                    $rec_balance_c0         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[0]);
                    $rec_balance_c1         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[1]);

                    //Pending Withdraw amoun sum
                    $rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $sell_user_id)->first();

                    //Discut user withdraw pending balance
                    $rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;

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
                            //Log::info("sell publish data");

                            RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');

                            Log::info("sell publish data ");


                           }else{
                            Log::info("sell data failed ");
                        }

                    }else{
                        Log::info("sell data insufficient bal failed ");

                    }

                    $coin_symbol        = explode('_', $market_symbol);

                    $amount_withoutfees = $buy_price * $qty;

                    $amount_withfees    = $amount_withoutfees;
                    Log::info("cron script_run check bal");
                    //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                    $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                    $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                    $pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first();
                    Log::info("cron script_run withdraw bal");
                    //Discut user withdraw pending balance
                    $real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;

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
                            Log::info("pushing into log");

                            $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                            RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');

                            Log::info("buy publish data ");

                        }else{
                            Log::info("buy data failed ");

                        }

                    }else{
                        Log::info("buy data insufficient bal failed ");

                    }

                  sleep(12);
                }
            }
        }

    }
    public static function realtime_trade_cron3(){


        //$perc=self::rand_float(0.001,1);
        $qty_perc=0.001;
        $buy_user_id=10;
        $sell_user_id=10;
        $crypto=['BTC','EVR'];
        foreach($crypto as $cr){
            if($cr=="BTC"){
                $pairings=array(
                    array('pair'=>'BNB_BTC'),array('pair'=>'ETH_BTC'),array('pair'=>'EVR_BTC'),array('pair'=>'LTC_BTC'));
            }
            if($cr=="EVR"){
                $pairings=array(array('pair'=>'BNB_EVR'),array('pair'=>'BTC_EVR'),array('pair'=>'ETH_EVR'),array('pair'=>'LTC_EVR'));
            }
            $coinlisting=CoinListing::where("coin_symbol",$cr)->first();
            foreach($pairings as $pr){
                $market_symbol=$pr['pair'];
                $pairtemp=explode("_", $market_symbol);
                $coinList_pair = CoinListing::where("coin_symbol",$pairtemp[0])->first();
                $market_data = Coinhistory::select("*")->where("market_symbol","=",$market_symbol)->orderBy("id","DESC")->LIMIT(1)->first();
                //$market_price=$market_data['last_price'];
                if($market_data!==null){
                    $market_price=$market_data['last_price'];
                }else{
                    $mprice=floatval($coinList_pair['coin_price'])/floatval($coinlisting['coin_price']);
                    $mrprice=number_format_eight_dec($mprice);
                    $market_price=str_replace(",","", $mrprice);
                }
                //$price_ten_perc=($market_price*1)/100;
                $buy_price=floatval($market_price);
                //$sell_price=$market_price+$price_ten_perc;
                //$sell_price=$buy_price;
                $perc=self::rand_float(0.001,1);
                    $perc_price=($buy_price*$perc)/100;
                    $buy_price=$buy_price+$perc_price;
                    $market_price_usd=floatval($buy_price)*floatval($coinlisting['coin_price']);
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
                    $coin_symbol        = explode('_', $market_symbol);

                    $amount_withoutfees = $buy_price * $qty;

                    $amount_withfees    = $amount_withoutfees;
                    Log::info("cron script_run check bal");
                    //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                    $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                    $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                    $pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first();
                    Log::info("cron script_run withdraw bal");
                    //Discut user withdraw pending balance
                    $real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;

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
                            Log::info("pushing into log");

                            $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                            RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');

                            Log::info("buy publish data ");

                        }else{
                            Log::info("buy data failed ");

                        }

                    }else{
                        Log::info("buy data insufficient bal failed ");

                    }
                    $sell_price=$buy_price;
                    /*$perc_price=($sell_price*$perc)/100;
                    $sell_price=$sell_price+$perc_price;*/
                    /*if($sell_price>1000){
                        $qty=self::rand_float(0.0001,0.001);
                    }else if($sell_price>100 && $sell_price<1000){
                        $qty=self::rand_float(0.001,0.01);
                    }else if($sell_price>1 && $sell_price<100){
                        $qty=self::rand_float(0.01,0.1);
                    }else if($sell_price>0.1 && $sell_price<1){
                        $qty=self::rand_float(0.1,100);
                    }else if($sell_price<0.1){
                        $qty=self::rand_float(100,200);
                    }*/
                    $coin_symbol        = explode('_', $market_symbol);

                    $amount_withoutfees = $sell_price * $qty;

                    $amount_withfees    = $amount_withoutfees;
                    Log::info("cron script_run check bal");
                    $rec_balance_c0         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[0]);
                    $rec_balance_c1         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[1]);

                    //Pending Withdraw amoun sum
                    $rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $sell_user_id)->first();

                    //Discut user withdraw pending balance
                    $rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;

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
                            //Log::info("sell publish data");

                            RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');

                            Log::info("sell publish data ");


                           }else{
                            Log::info("sell data failed ");
                        }

                    }else{
                        Log::info("sell data insufficient bal failed ");

                    }
              sleep(14);
            }
        }

    }
    public static function realtime_trade_cron4(){

        Log::info("realtime_trade_cron4");

        $buy_user_id=10;
        $sell_user_id=10;
        $crypto=['BTC','EVR'];
        foreach($crypto as $cr){
            if($cr=="BTC"){
                $pairings=array(
                    array('pair'=>'BNB_BTC'),array('pair'=>'ETH_BTC'),array('pair'=>'EVR_BTC'),array('pair'=>'LTC_BTC'));
            }
            if($cr=="EVR"){
                $pairings=array(array('pair'=>'BNB_EVR'),array('pair'=>'BTC_EVR'),array('pair'=>'ETH_EVR'),array('pair'=>'LTC_EVR'));
            }
            $coinlisting=CoinListing::where("coin_symbol",$cr)->first();
            foreach($pairings as $pr){
                $market_symbol=$pr['pair'];
                $pairtemp=explode("_", $market_symbol);
                $coinList_pair = CoinListing::where("coin_symbol",$pairtemp[0])->first();
                $market_data = Coinhistory::select("*")->where("market_symbol","=",$market_symbol)->orderBy("id","DESC")->LIMIT(1)->first();
                //$market_price=$market_data['last_price'];
                if($market_data!==null){
                    $market_price=$market_data['last_price'];
                }else{
                    $mprice=floatval($coinList_pair['coin_price'])/floatval($coinlisting['coin_price']);
                    $mrprice=number_format_eight_dec($mprice);
                    $market_price=str_replace(",","", $mrprice);
                }
                //$price_ten_perc=($market_price*1)/100;
                $buy_price=floatval($market_price);
                //$sell_price=$market_price+$price_ten_perc;
                //$sell_price=$buy_price;
                $perc=self::rand_float(0.001,1);
                    $perc_price=($buy_price*$perc)/100;
                    $buy_price=$buy_price-$perc_price;
                    $market_price_usd=floatval($buy_price)*floatval($coinlisting['coin_price']);
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

                    $sell_price=$buy_price;

                    $coin_symbol        = explode('_', $market_symbol);

                    $amount_withoutfees = $sell_price * $qty;

                    $amount_withfees    = $amount_withoutfees;
                    Log::info("cron script_run check bal");
                    $rec_balance_c0         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[0]);
                    $rec_balance_c1         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[1]);

                    //Pending Withdraw amoun sum
                    $rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $sell_user_id)->first();

                    //Discut user withdraw pending balance
                    $rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;

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
                            //Log::info("sell publish data");

                            RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');

                            Log::info("sell publish data ");


                           }else{
                            Log::info("sell data failed ");
                        }

                    }else{
                        Log::info("sell data insufficient bal failed ");

                    }
                    $coin_symbol        = explode('_', $market_symbol);

                    $amount_withoutfees = $buy_price * $qty;

                    $amount_withfees    = $amount_withoutfees;
                    Log::info("cron script_run check bal");
                    //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                    $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                    $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                    $pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first();
                    Log::info("cron script_run withdraw bal");
                    //Discut user withdraw pending balance
                    $real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;

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
                            Log::info("pushing into log");

                            $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                            RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');

                            Log::info("buy publish data ");

                        }else{
                            Log::info("buy data failed ");

                        }

                    }else{
                        Log::info("buy data insufficient bal failed ");

                    }

              sleep(16);
            }
        }

    }
    public function add_referal_bonus_to_bal(){

        $refbonus= ReferralBonus::select(DB::raw('SUM(evr) as evr'),'user_id')->groupBy('user_id')->get()->toArray();
        foreach ($refbonus as $rb) {
            $bdata=array(
                "user_id"=>$rb['user_id'],
                "currency_symbol"=>'EVR',
                "main_balance"=>$rb['evr'],
                "created_at"=>date("Y-m-d H:i:s")
            );
            Balance::insert($bdata);
        }
    }
    public static function onetime_script_run(){
        //$perc=0.001;

        $qty_perc=0.001;
        $buy_user_id=10;
        $sell_user_id=10;
        $coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_name", "ASC")->get()->toArray();
        foreach($coinList as $cv){

            $coin_id = $cv['id'];
            $cryptolistfrom = $cv['coin_symbol'];
            $basePairingCurrency = BaseCurrencyPairing::where("coin_id","=",$coin_id)->where("status","=",1)->orderBy('trading_pairs')->get();
            if(@count($basePairingCurrency) > 0){

                foreach($basePairingCurrency as $pair){
                    $pair_id = $pair['pairing_id'];


                    $coinList_pair = CoinListing::where("id","=",$pair_id)->where("status","=",1)->first();
                    $cryptolistto = $coinList_pair['coin_symbol'];
                    $market_symbol = $cryptolistto."_".$cryptolistfrom;

                    //$market_price=$market_data['last_price'];
                    /*if($market_data!==null){
                        $market_price=$market_data['last_price'];
                    }else{*/
                        /*if($pair['trading_pairs']=="EVR/BTC" || $pair['trading_pairs']=="EVR/LTC" || $pair['trading_pairs']=="EVR/ETH"){
                            $coinprice_perc=($coinList_pair['coin_price']*25)/100;
                            $coinprice=$coinList_pair['coin_price']+$coinprice_perc;
                        }else{
                           $coinprice=$coinList_pair['coin_price'];
                        }

                        if($pair['trading_pairs']=="BTC/EVR" || $pair['trading_pairs']=="LTC/EVR" || $pair['trading_pairs']=="ETH/EVR"){
                            $base_coinprice_perc=($cv['coin_price']*25)/100;
                            $base_coinprice=$cv['coin_price']+$base_coinprice_perc;
                        }else{
                           $base_coinprice=$cv['coin_price'];
                        }*/
                        if($pair['trading_pairs']=="EVR/BTC" || $pair['trading_pairs']=="EVR/LTC" || $pair['trading_pairs']=="EVR/ETH" || $pair['trading_pairs']=="BTC/EVR" || $pair['trading_pairs']=="LTC/EVR" || $pair['trading_pairs']=="ETH/EVR"){
                            $coinprice=$coinList_pair['coin_price'];
                            $base_coinprice=$cv['coin_price'];

                            Log::info("coin usd values new ".$cryptolistto." ".$coinprice." ".$cryptolistfrom." ". $base_coinprice);
                            $mprice=floatval($coinprice)/floatval($base_coinprice);
                            $mrprice=number_format_eight_dec($mprice);
                            $market_price=str_replace(",","", $mrprice);
                        //}

                        $market_price=floatval($market_price);
                        $price_ten_perc=($market_price*0.5)/100;
                        $buy_price=$market_price-$price_ten_perc;
                        $sell_price=$market_price+$price_ten_perc;
                        for($i=0;$i<250;$i++){
                            $perc=self::rand_float(0.001,1);
                            $perc_price=($buy_price*$perc)/100;
                            $buy_price=$buy_price-$perc_price;
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
                            $coin_symbol        = explode('_', $market_symbol);

                            $amount_withoutfees = $buy_price * $qty;

                            $amount_withfees    = $amount_withoutfees;
                            Log::info("cron script_run check bal");
                            //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                            $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                            $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                            $pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first();
                            Log::info("cron script_run withdraw bal");
                            //Discut user withdraw pending balance
                            $real_balance = (float)@$balance_c1->balance-(float)@$pending_withdraw->amount;

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
                                    Log::info("pushing into log");

                                    $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                                    RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');

                                    Log::info("buy publish data ");

                                }else{
                                    Log::info("buy data failed ");

                                }

                            }else{
                                Log::info("buy data insufficient bal failed ");

                            }

                        }
                        for($j=0;$j<210;$j++){
                            $perc=self::rand_float(0.001,1);
                            $perc_price=($sell_price*$perc)/100;
                            $sell_price=$sell_price+$perc_price;
                            $sell_market_price_usd=floatval($sell_price)*floatval($base_coinprice);
                            if($sell_market_price_usd>1000){
                                $qty=self::rand_float(0.0001,0.001);
                            }else if($sell_market_price_usd>100 && $sell_market_price_usd<1000){
                                $qty=self::rand_float(0.001,0.01);
                            }else if($sell_market_price_usd>1 && $sell_market_price_usd<100){
                                $qty=self::rand_float(0.01,0.1);
                            }else if($sell_market_price_usd>0.1 && $sell_market_price_usd<1){
                                $qty=self::rand_float(0.1,100);
                            }else if($sell_market_price_usd<0.1){
                                $qty=self::rand_float(100,200);
                            }
                            $coin_symbol        = explode('_', $market_symbol);

                            $amount_withoutfees = $sell_price * $qty;

                            $amount_withfees    = $amount_withoutfees;
                            Log::info("cron script_run check bal");
                            $rec_balance_c0         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[0]);
                            $rec_balance_c1         = ExchangeController::checkBalance($sell_user_id,$coin_symbol[1]);

                            //Pending Withdraw amoun sum
                            $rec_pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[0])->whereIn('status', [0,1])->where('user_id', $sell_user_id)->first();

                            //Discut user withdraw pending balance
                            $rec_real_balance = (float)@$rec_balance_c0->balance-(float)@$rec_pending_withdraw->amount;

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
                                    //Log::info("sell publish data");

                                    RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');

                                    Log::info("sell publish data ");


                                   }else{
                                    Log::info("sell data failed ");
                                }

                            }else{
                                Log::info("sell data insufficient bal failed ");

                            }
                        }
                    }
                }
            }
        }
    }


    public static function realtime_selected_trade_cron_sell_buy(){

        Log::info("realtime_selected_trade_cron_sell_buy start");
        //$perc=self::rand_float(0.001,1);
        $qty_perc=0.001;
        $buy_user_id=10;
        $sell_user_id=10;
        //$crypto=['BTC','EVR'];
        //DB::enableQueryLog();
        $buy_sell=["BUY","SELL"];
        $coinList = CoinListing::whereIn("coin_symbol",['BTC','ETH','EVR','TRU-E','USDT'])->where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_name", "ASC")->get()->toArray();
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
                                Log::info("first sell executed");
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
                                        //Log::info("sell publish data");

                                        RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');

                                        Log::info("realtime_selected_trade_cron_sell_buy sell publish data ");


                                       }else{
                                        Log::info("realtime_selected_trade_cron_sell_buy sell data failed ");
                                    }

                                }else{
                                    Log::info("realtime_selected_trade_cron_sell_buy sell data insufficient bal failed ");

                                }
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
                                        Log::info("realtime_selected_trade_cron_sell_buy pushing into log");

                                        $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                                        RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');

                                        Log::info("realtime_selected_trade_cron_sell_buy buy publish data ");

                                    }else{
                                        Log::info("realtime_selected_trade_cron_sell_buy buy data failed ");

                                    }

                                }else{
                                    Log::info("realtime_selected_trade_cron_sell_buy buy data insufficient bal failed ");

                                }
                            }
                            if($randomSelected=="BUY"){
                                Log::info("first buy executed");

                                $coin_symbol        = explode('_', $market_symbol);

                                $amount_withoutfees = $buy_price * $qty;

                                $amount_withfees    = $amount_withoutfees;
                                Log::info("cron realtime_selected_trade_cron_sell_buy check bal");
                                //Buy(BTC_USD) = C0_C1, BUY C0 vai C1
                                $balance_c0         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[0]);
                                $balance_c1         = ExchangeController::checkBalance($buy_user_id,$coin_symbol[1]);

                                //$pending_withdraw = Withdraw::select(DB::raw('SUM(amount)+SUM(fees_amount) as amount') )->where('currency_symbol', $coin_symbol[1])->whereIn('status', [0,1])->where('user_id', $buy_user_id)->first();
                                Log::info("cron realtime_selected_trade_cron_sell_buy withdraw bal");
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
                                        Log::info("realtime_selected_trade_cron_sell_buy pushing into log");

                                        $rabitmqData = json_encode(array("exchange_id"=>$exchange_id,"user_id"=>$buy_user_id,"market_symbol"=>$market_symbol,"coin_symbol"=>$coin_symbol,"queue_type"=>"buy"));
                                        RabbitmqController::publish_channel('buy_queue',$rabitmqData,'router');

                                        Log::info("realtime_selected_trade_cron_sell_buy buy publish data ");

                                    }else{
                                        Log::info("realtime_selected_trade_cron_sell_buy buy data failed ");

                                    }

                                }else{
                                    Log::info("realtime_selected_trade_cron_sell_buy buy data insufficient bal failed ");

                                }
                                $sell_price=$buy_price;

                                $coin_symbol        = explode('_', $market_symbol);

                                $amount_withoutfees = $sell_price * $qty;

                                $amount_withfees    = $amount_withoutfees;
                                Log::info("cron realtime_selected_trade_cron_sell_buy sell check bal");
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
                                        //Log::info("sell publish data");

                                        RabbitmqController::publish_channel('sell_queue',$rec_rabitmqData,'router');

                                        Log::info("realtime_selected_trade_cron_sell_buy sell publish data ");


                                       }else{
                                        Log::info("realtime_selected_trade_cron_sell_buy sell data failed ");
                                    }

                                }else{
                                    Log::info("realtime_selected_trade_cron_sell_buy sell data insufficient bal failed ");

                                }
                            }
                           /*$sec= rand(3,8);
                      sleep($sec);*/
                    }

                }
            }
        }

    }
    public static function expireWithdraw(){
        Log::info("expired withdraw");
        $prev_day=date("Y-m-d H:i",strtotime("-24 hours"));
        $withdraw_list=Withdraw::where('status',0)->where("created_at","<=",$prev_day)->get();
        foreach ($withdraw_list as $w) {
            $admintransId=time().rand(1,10000);
            $w->status=4;
            $w->is_expired=1;
            $w->admin_transaction_id=$admintransId;
            $w->admin_action_date=date("Y-m-d H:i:s");
            $w->admin_description="Rejected by expire the request";
            $w->save();
            $balance=Balance::where('user_id',$w['user_id'])->where('currency_symbol', $w['currency_symbol'])->first();
            $balance->main_balance= $balance['main_balance']+$w['net_amount'];
            $balance->save();
        }
    }

    public static function brexco_transaction_status(){
        $brexcoTrans=BrexcoTransactions::where('status','CONFIRMED')->orWhere('status','SUBMITTED')->get();
        foreach ($brexcoTrans as $bt) {
            Log::info('payment id '.$bt['payment_id']);
            sleep(1);
            $pres=BrexcoController::curl_call_dvs("transactions/".$bt['payment_id']);
            Log::info("transaction status api ".$pres);
            $transStatus=json_decode($pres);
            if(empty($transStatus->errors)){
                Log::info("status ".$transStatus->status->message);
                $bt->status=$transStatus->status->message;
                $bt->status_text=$transStatus->status->message;
                $bt->save();
                $totalAmt=str_replace(",", "", $bt['total_amount']);
                if($transStatus->status->message!='CREATED' && $transStatus->status->message!='CONFIRMED' && $transStatus->status->message!='SUBMITTED' && $transStatus->status->message!='COMPLETED'){
                    $bal=Balance::where('user_id',$bt['user_id'])->where('currency_symbol',$bt['crypto_symbol'] )->first();
                    $bal->main_balance=$bal->main_balance+floatval($totalAmt);
                    $bal->save();

                }
            }
        }
    }
    public static function migrateBalEverus(){
        Log::info("migrateEverus");
        try{
            $everusMysql = DB::connection('everusMysql');
        }catch(\Exception $e){
            Log::info($e->getMessage());
        }

        $excludeEmails=array('duasarobox@gmail.com','alifagrapradikha1987@gmail.com','rizamaroon@gmail.com','anasmi@gmail.com');

        Log::info("user1");

        try{
            //$everusUserData3=$everusMysql->table('users')->leftJoin('cryptobalance','users.user_id','=','cryptobalance.user_id')->where("users.status",1)->whereNotIn("users.email",$excludeEmails)->groupBy('users.user_id')->union($everusUserData1)->get()->toArray();
            $everusUserData3=$everusMysql->table('users')->leftJoin('cryptobalance','users.user_id','=','cryptobalance.user_id')->select('users.*')->where("cryptobalance.balance",">",0)->whereNotIn("users.email",$excludeEmails)->groupBy('users.user_id')->get()->toArray();
        }catch(\Exception $e){
            Log::info($e->getMessage());
        }
        foreach ($everusUserData3 as $user) {
            Log::info("user bal det ".json_encode($user));
            $userBal=$everusMysql->table('cryptobalance')->where('user_id',$user->user_id)->get()->toArray();

                Log::info("avail bal ".$user->user_id);
                $bUser=User::where("email",$user->email)->first();
                if($bUser!==null){
                    if($bUser->is_everus_user==1){
                        continue;
                    }
                    $bUser->is_everus_user=1;
                    $bUser->is_duplicate_to_everus=1;
                    $bUser->save();
                    $evraddrdata=array(
                        'user_id'=>$bUser->user_id,
                        'everus_user_id'=>$user->user_id,
                        'evr_address'=>($user->everus_eth_address=='empty') ? "" : $user->everus_eth_address ,
                        'eth_address'=>($user->eth_address=='empty') ? "" : $user->eth_address,
                        'btc_address'=>($user->btc_address=='empty') ? "" : $user->btc_address,
                        'ltc_address'=>($user->ltc_address=='empty') ? "" : $user->ltc_address,
                        'erc20_address'=>($user->erc20_address=='empty') ? "" : $user->erc20_address
                    );
                    EverusAddresses::insert($evraddrdata);

                }else{
                    $bstatus="A";
                    if($user->status==0){
                        $bstatus="D";
                    }
                    $udata=array(
                        'email'=>$user->email,
                        'password'=>$user->password,
                        'status'=>$bstatus,
                        'role'=>1,
                        'is_everus_user'=>1,
                        'created_at'=>date('Y-m-d H:i:s')
                    );
                    $userid=User::insertGetId($udata);
                    $evraddrdata=array(
                        'user_id'=>$userid,
                        'everus_user_id'=>$user->user_id,
                        'evr_address'=>($user->everus_eth_address=='empty') ? "" : $user->everus_eth_address ,
                        'eth_address'=>($user->eth_address=='empty') ? "" : $user->eth_address,
                        'btc_address'=>($user->btc_address=='empty') ? "" : $user->btc_address,
                        'ltc_address'=>($user->ltc_address=='empty') ? "" : $user->ltc_address,
                        'erc20_address'=>($user->erc20_address=='empty') ? "" : $user->erc20_address
                    );
                    EverusAddresses::insert($evraddrdata);
                    $refUser=$everusMysql->table('users')->where('user_id',$user->referral_userid)->first();
                    $refcode="";
                    if($refUser!==null){
                        $refcode=$refUser->referral_code;
                    }
                    $userinfo=array(
                        "user_id"=>$userid,
                        "first_name"=>$user->first_name,
                        "last_name"=>$user->last_name,
                        "mobile_number"=>$user->mobile_no,
                        "birth_date"=>$user->dob,
                        "gender"=>$user->sex,
                        "nationality"=>$user->country,
                        "ref_code"=>$user->referral_code,
                        "google_2fa_key"=>$user->google2fa_secret,
                        "google2fa_qrcode_url"=>$user->google2fa_qrcode_url,
                        "applied_ref_code"=>$refcode

                    );
                    Userinfo::insert($userinfo);

                }

        }
        //print_r($everusUserData2);
    }
    public static function migrateActiveEverus(){
        Log::info("migrateEverus");
        try{
            $everusMysql = DB::connection('everusMysql');
        }catch(\Exception $e){
            Log::info($e->getMessage());
        }

        $excludeEmails=array('duasarobox@gmail.com','alifagrapradikha1987@gmail.com','rizamaroon@gmail.com','anasmi@gmail.com');

        Log::info("user1");

        try{
            $everusUserData3=$everusMysql->table('users')->leftJoin('cryptobalance','users.user_id','=','cryptobalance.user_id')->select('users.*')->where("users.status",1)->whereNotIn("users.email",$excludeEmails)->groupBy('users.user_id')->get()->toArray();
            //$everusUserData3=$everusMysql->table('users')->leftJoin('cryptobalance','users.user_id','=','cryptobalance.user_id')->where("cryptobalance.balance",">",0)->whereNotIn("users.email",$excludeEmails)->groupBy('users.user_id');
        }catch(\Exception $e){
            Log::info($e->getMessage());
        }
        foreach ($everusUserData3 as $user) {
            Log::info("user det ".json_encode($user));
            $userBal=$everusMysql->table('cryptobalance')->where('user_id',$user->user_id)->get()->toArray();
            if(!empty($userBal)){
                foreach ($userBal as $ub) {
                    if($ub->balance>0){
                        continue;
                    }
                }
            }
                Log::info("avail bal ".$user->user_id);
                $bUser=User::where("email",$user->email)->first();
                if($bUser!==null){
                    if($bUser->is_everus_user==1){
                        continue;
                    }
                    $bUser->is_everus_user=1;
                    $bUser->is_duplicate_to_everus=1;
                    $bUser->save();
                    $evraddrdata=array(
                        'user_id'=>$bUser->user_id,
                        'everus_user_id'=>$user->user_id,
                        'evr_address'=>($user->everus_eth_address=='empty') ? "" : $user->everus_eth_address ,
                        'eth_address'=>($user->eth_address=='empty') ? "" : $user->eth_address,
                        'btc_address'=>($user->btc_address=='empty') ? "" : $user->btc_address,
                        'ltc_address'=>($user->ltc_address=='empty') ? "" : $user->ltc_address,
                        'erc20_address'=>($user->erc20_address=='empty') ? "" : $user->erc20_address
                    );
                    Log::info("everus addresses ".json_encode($evraddrdata));
                    EverusAddresses::insert($evraddrdata);
                    /*if(!empty($userBal)){
                        foreach ($userBal as $ub) {
                           $ubBal=Balance::where("user_id",$user->user_id)->where('currency_symbol',$ub->cryptoname)->first();
                           if($ubBal!==null){
                                $mbal=$ubBal->main_balance;
                                $ubBal->main_balance=$mbal+$ub->balance;
                                $ubBal->save();
                           }

                        }
                    }*/
                }else{
                    $bstatus="A";
                    if($user->status==0){
                        $bstatus="D";
                    }
                    $udata=array(
                        'email'=>$user->email,
                        'password'=>$user->password,
                        'status'=>$bstatus,
                        'role'=>1,
                        'is_everus_user'=>1,
                        'created_at'=>date('Y-m-d H:i:s')
                    );
                    $userid=User::insertGetId($udata);
                    $evraddrdata=array(
                        'user_id'=>$userid,
                        'everus_user_id'=>$user->user_id,
                        'evr_address'=>($user->everus_eth_address=='empty') ? "" : $user->everus_eth_address ,
                        'eth_address'=>($user->eth_address=='empty') ? "" : $user->eth_address,
                        'btc_address'=>($user->btc_address=='empty') ? "" : $user->btc_address,
                        'ltc_address'=>($user->ltc_address=='empty') ? "" : $user->ltc_address,
                        'erc20_address'=>($user->erc20_address=='empty') ? "" : $user->erc20_address
                    );
                    Log::info("everus addresses2 ".json_encode($evraddrdata));
                    EverusAddresses::insert($evraddrdata);
                    $refUser=$everusMysql->table('users')->where('user_id',$user->referral_userid)->first();
                    $refcode="";
                    if($refUser!==null){
                        $refcode=$refUser->referral_code;
                    }
                    $userinfo=array(
                        "user_id"=>$userid,
                        "first_name"=>$user->first_name,
                        "last_name"=>$user->last_name,
                        "mobile_number"=>$user->mobile_no,
                        "birth_date"=>$user->dob,
                        "gender"=>$user->sex,
                        "nationality"=>$user->country,
                        "ref_code"=>$user->referral_code,
                        "google_2fa_key"=>$user->google2fa_secret,
                        "google2fa_qrcode_url"=>$user->google2fa_qrcode_url,
                        "applied_ref_code"=>$refcode

                    );
                    Userinfo::insert($userinfo);
                   //self::createAddresses($userid);
                    /*if(!empty($userBal)){
                        foreach ($userBal as $ub) {
                            $ubBalInsert=array(
                                "user_id"=>$userid,
                                "currency_symbol"=>strtoupper($ub->cryptoname),
                                "main_balance"=>$ub->balance,
                                "created_at"=>date("Y-m-d H:i:s")
                            );
                            Balance::insert($ubBalInsert);
                        }
                    }*/


                }

        }
        //print_r($everusUserData2);
    }
    public static function createAddresses(){
        $euser=User::where("is_everus_user",1)->where("is_duplicate_to_everus",0)->get()->toArray();

        foreach ($euser as $eu) {
            Log::info("before api call");
        $nres=NodeApiCalls::wallet_creation_in_cron($eu['user_id'],$eu['email'],'VkYp3s6v9y$B?E(H+MbQeThWmZq4t7w!');
           Log::info("migrate api res ".json_encode($nres));
           //$ndata=json_decode($nres);
           foreach ($nres->data as $nr) {
            Log::info("each data ".json_encode($nr));
                if($nr->coin_symbol=='ERC20'){
                    $coins=CoinListing::whereNotIn('coin_symbol',['BTC','ETH','LTC','ETC','BCH',])->where('status',1)->get()->toArray();
                    foreach ($coins as $c) {
                       $userAddr=array(
                            "user_id"=>$eu['user_id'],
                            "wallet_symbol"=>$c['coin_symbol'],
                            "wallet_address"=>$nr->address,
                            "status"=>1
                        );
                       UserAddresses::insert($userAddr);
                        $qrcode=DepositController::address_qrcode_generate($nr->address,$eu['user_id'],$c['coin_symbol']);
                    }
                }else{
                    $userAddr=array(
                        "user_id"=>$eu['user_id'],
                        "wallet_symbol"=>$nr->coin_symbol,
                        "wallet_address"=>$nr->address,
                        "status"=>1
                    );
                    UserAddresses::insert($userAddr);
                     $qrcode=DepositController::address_qrcode_generate($nr->address,$eu['user_id'],$nr->coin_symbol);
                }



           }
        }

    }
    //store products data in database
    public static function brexcoPackages(){
    Log::info("brexcoPackages : start");
        $cres=Country::where('dvs_portal_iso_code','!=',"")->orderBy("dvs_portal_iso_code",'ASC')->get();
        foreach ($cres as $c) {
            $services=array();
            for($i=1;$i<=10;$i++){
                $res=BrexcoController::curl_call_dvs("products?page=".$i."&per_page=100&country_iso_code=".$c['dvs_portal_iso_code']);
                $productsList=json_decode($res);
                Log::info("response ");
                Log::info($res);
                //print_r($productsList);exit;
                if(empty($productsList->errors)){
                    if(@count($productsList)>0){
                        foreach ($productsList as $p) {
                           $package= BrexcoPackages::where("package_id",$p->id)->first();
                           if($package===null){
                                $pdata=array(
                                "country_iso_code"=>$c['dvs_portal_iso_code'],
                                "service"=>$p->service->id,
                                "operator_id"=>$p->operator->id,
                                "operator_name"=>$p->operator->name,
                                "package_id"=>$p->id,
                                "packages"=>serialize($p)
                               );
                               BrexcoPackages::insert($pdata);
                           }else{
                            Log::info("package already exists ".$p->id);
                           }

                        }
                    }
                }else{
                    Log::info("close brexco packages");
                    //continue;
                }
            }
        }
        Log::info("brexcoPackages : end");
    }

    //store services data in database
    public static function brexcoServicesIntoDb(){
    Log::info("brexcoServicesIntoDb : start");
        $cres=Country::where('dvs_portal_iso_code','!=',"")->get();
        foreach ($cres as $c) {
            $services=array();$service = array();
            for($i=1;$i<=10;$i++){
                $res=BrexcoController::curl_call_dvs("products?page=".$i."&per_page=100&country_iso_code=".$c['dvs_portal_iso_code']);
                $productsList=json_decode($res);
                //Log::info("curl_call_dvs ".json_encode($res));
                //print_r($productsList);exit;
                if(empty($productsList->errors)){
                    if(@count($productsList)>0){
                        foreach ($productsList as $p) {
                            //if (!in_array($p->service->id, $services)) {
                                array_push($services,$p->service->id);
                            //}
                        }
                    }
                }else{
                    break;
                }
            }

            $result = array_unique($services);
            $update_data = array(
                'brexco_services'=>json_encode(array_values($result))
            );
            Log::info("Country ".$c['dvs_portal_iso_code']);
            Log::info("response_services");
            Log::info(json_encode(array_values($result)));
            if(!empty($result)){
                Country::where(['dvs_portal_iso_code'=>$c['dvs_portal_iso_code']])->update($update_data);
            }
        }
        Log::info("brexcoServicesIntoDb : end");
    }
    public static function addEverusUserstoReferrals(){
       $users= User::where('is_everus_user',1)->where('is_duplicate_to_everus',0)->get()->toArray();
       foreach ($users as $u) {
        Log::info("adding everus user to referrals ".$u['user_id']);
           $referrals=Referrals::where("ancestor_id","=",$u['user_id'])->where("descendant_id","=",$u['user_id'])->first();
           if($referrals===null){
                $ref=array(
                    "ancestor_id"=>$u['user_id'],
                    "descendant_id"=>$u['user_id']
                );
                Referrals::insert($ref);
           }
       }
    }
    public static function addingRefUserWithSrinivasAcc(){
       $users= Userinfo::where('applied_ref_code',"")->get();
       foreach ($users as $u) {
        Log::info("addingRefUserWithSrinivasAcc ".$u['user_id']);
        if($u->applied_ref_code==""){
            $u->applied_ref_code="SRBREXILY0010";
            $u->save();
            $referrals=Referrals::where("ancestor_id","=",$u->user_id)->where("descendant_id","=",$u->user_id)->first();
            if($referrals!==null){
                
                Referrals::where("ancestor_id","=",$u->user_id)->where("descendant_id","=",$u->user_id)->delete();
            }
            $refnode=DB::table('referrals_nodes')->where("ancestor_id","=",$u->user_id)->where("descendant_id","=",$u->user_id)->first();
            if($refnode!==null){
                DB::table('referrals_nodes')->where("ancestor_id","=",$u->user_id)->where("descendant_id","=",$u->user_id)->delete();
            }
            $insertObj = array(
                "ancestor_id"=>10,
                "descendant_id"=>$u->user_id
            );
            
            DB::table('referrals_nodes')->insert($insertObj);
            
           
            

        }
        
       }
    }

    public static function dynamicCron($pair){
        Log::info("dynamic cron function ".$pair);
    }
}
