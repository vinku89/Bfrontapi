<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\WebsocketDemoEvent;
use DB;
use Illuminate\Support\Facades\Redis;
use App\CoinListing;
use App\BaseCurrencyPairing;
use App\Coinhistory;
class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function home(){
        broadcast(new WebsocketDemoEvent("Hello i am Rama Rao"))->toOthers();
        return response()->json(["Success"=>true,'status' => 200,'Result' => []], 200);
    }

    public function test(){
        $res = DB::table('users')->get();
        print_r($res);
    }

    public function redis_refresh(){
        Redis::flushDb();
        //$dataarr = Redis::lrange('coins',0,100);
        //$dataarr = json_decode($dataarr);
        //print_r($dataarr);exit;
            //$allKeys = Redis::keys('*');
            //return response()->json(["Success"=>true,'status' => 200,'Result' => $dataarr], 200);
            $coinList = CoinListing::where("is_base_currency","=",1)->where("status","=",1)->orderBy("coin_name", "ASC")->get()->toArray();
            $mdata['coins']=array();
            if(@count($coinList)>0){
            foreach ($coinList as $ck => $cv) {
                Redis::rpush("coins",$cv['coin_symbol']);
                Redis::rpush("info:".$cv['coin_symbol'], json_encode($cv));
                 //$mdata['coins'][$cv['coin_symbol']]=$cv;
                 
                
                   $coin_id = $cv['id'];
                    $cryptolistfrom = $cv['coin_symbol'];   
                    $basePairingCurrency = BaseCurrencyPairing::where("coin_id","=",$coin_id)->where("status","=",1)->get();
                    $pairingarr=array();
                    if(@count($basePairingCurrency) > 0){
                        $x=0;
                        foreach($basePairingCurrency as $pair){
                            $pairingArrPrice = array();
                            $pair_id = $pair['pairing_id'];

                            //get cv
                            $coinList_pair = CoinListing::where("id","=",$pair_id)->where("status","=",1)->first();
                            
                            if(@count($coinList_pair) > 0){
                                $cryptolistfrom = $cv['coin_symbol']; 
                                $cryptolistto = $coinList_pair['coin_symbol'];

                                
                                //$base_piar = $cryptolistfrom."_".$cryptolistto;
                                $base_piar = $cryptolistto."_".$cryptolistfrom;
                                //$pairingArrPrice['pair']=$pair;
                                $market_data = Coinhistory::select("*")->where("market_symbol","=",$base_piar)->orderBy("id","DESC")->LIMIT(1)->first();
                                if(!empty($market_data)){
                                
                                    $price = $market_data->last_price;
                                    $volume = round($market_data->total_coin_supply*100)/100;
                                    
                                    $change = $market_data->change_perc;
                                    $price_change_percent = $change;
                                    $pairingArrPrice["coin_id"]=$coinList_pair['id'];
                                    $pairingArrPrice["coin_name"]=$coinList_pair['coin_name'];
                                    $pairingArrPrice["coin_symbol"]=$coinList_pair['coin_symbol'];
                                    $pairingArrPrice["market_symbol"]=$coinList_pair['coin_symbol']."_".$cv['coin_symbol'];
                                    $pairingArrPrice["coin_image"]=$coinList_pair['coin_image'];
                                    $pairingArrPrice["market_price"]=number_format($price,8);
                                    $pairingArrPrice["volume"]=$volume;
                                    $pairingArrPrice["change"]=$price_change_percent;
                                    
                                    
                                }else{
                                    $pairingArrPrice["coin_id"]=$coinList_pair['id'];
                                    $pairingArrPrice["coin_name"]=$coinList_pair['coin_name'];
                                    $pairingArrPrice["coin_symbol"]=$coinList_pair['coin_symbol'];
                                    $pairingArrPrice["market_symbol"]=$coinList_pair['coin_symbol']."_".$cv['coin_symbol'];
                                    $pairingArrPrice["coin_image"]=$coinList_pair['coin_image'];
                                    $pairingArrPrice["market_price"]=number_format($coinList_pair['coin_price'],8);
                                    $pairingArrPrice["volume"]=0;
                                    $pairingArrPrice["change"]=0;
                                    
                                }
                                $pairingarr[]=$pairingArrPrice;
                                Redis::set('coinspairs:'.$coinList_pair['coin_symbol']."_".$cv['coin_symbol'], $x );
                                Redis::rpush("pairing:".$cv['coin_symbol'], json_encode($pairingArrPrice));
                                // $mdata['coins'][$cv['coin_symbol']]["pairing"]=$pairingArrPrice;
                               $x++; 
                            }
                        }
                            //Redis::rpush("pairing:".$cv['coin_symbol'], json_encode($pairingarr));

                    }
                    
                    // $basecurrencyList[] = array(
                    //             "base_currency_id"=>$cv['id'],
                    //             "base_currency_name"=>$cv['coin_name'],
                    //             "base_currency_symbol"=>$cv['coin_symbol'],
                    //             "pairing_list"=>$pairingArr,
                                
                    //             );
                }
                
            }
            //echo json_encode($mdata);
           // Redis::set('trading_pair', $mdata);
            //$name = Redis::get('trading_pair');
            //Redis::get('trading_pair')
            //Redis::flushDb();
            /*$data = array('btc-eth-val', 'betc-ltc-val', 'btc-evr-val');
        
        for ($x = 0; $x < count($data); $x++) {
            //echo "The number is: $x <br>";
            Redis::rpush('coins:btc', $data[$x]);
            Redis::set('coinspairs:'.$data[$x], $x+1 );
        }
*/
       
       //$result = Redis::lrange('coins', 0, 10);

        //$dataarr = array();
        //$dataarr = Redis::get('btc');
        Redis::persist('coins');
            $allKeys = Redis::keys('*');
            return response()->json(["Success"=>true,'status' => 200,'Result' => $allKeys], 200);
        /*$redis->set('key', array('btc-eth-val', 'betc-ltc-val', 'btc-evr-val'));
        
       
       
       $result = $redis->get('key');
       return response()->json(["Success"=>true,'status' => 200,'Result' => $result], 200);*/
    }
    public function redis_coins(){
        $allKeys = Redis::lrange('pairing:EVR',0,1000);
        return response()->json(["Success"=>true,'status' => 200,'Result' => $allKeys], 200);
        //$index=Redis::get('coinspairs:LTC_BTC');
        //$pairing=Redis::lindex('pairing:BTC', $index);
        //$pairinginfo = Redis::lrange('pairing:BTC',0,1000);
        //print_r($pairinginfo);
        //echo "<br>";
        //$pairing=json_decode($pairing);
        //echo $pairing->market_price;
        //var_dump($pairing);exit;
        //$pairing->market_price= "3.05400000";
        //Redis::lSet('pairing:BTC', $index, json_encode($pairing));
        //$pairing=Redis::lindex('pairing:BTC', $index);
        //print_r(json_decode($pairing));exit;
        //echo $index;exit;
        $base_currency_name = "BTC";
        if(!empty($base_currency_name)){
            $coin_symbol =  $base_currency_name;
        }else{
            $coin_symbol = "BTC";
        }
        echo $coin_symbol;
        $coins = Redis::lrange('coins',0,1000);
print_r($coins);exit;
        $basecurrencyList = array();
        if(@count($coins)>0){
            
            foreach($coins as $res){
                $infocoins = Redis::lrange('info:'.$res,0,1000);
                $infocoins=json_decode($infocoins[0]);
                
                $pairinginfo = Redis::lrange('pairing:'.$res,0,1000);
                //print_r($pairinginfo);exit;
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
            }
            return response()->json(["Success"=>true,'status' => 200,'Result' => $basecurrencyList], 200);
        }else{
            return response()->json(["Success"=>false,'Status' => 422, 'Result' => $basecurrencyList], 200);
        }
    }
}
