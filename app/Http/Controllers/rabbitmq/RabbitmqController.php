<?php
namespace App\Http\Controllers\rabbitmq;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
//use Amqp;
use Log;
use App\Http\Controllers\API\ExchangeController;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPConnection;
use App\Events\buy;
use DB;
use App\Biding;
use App\Balance;
use App\Events\WebsocketDemoEvent;
use App\Events\BuyordersEvent;
use App\Events\TradeHistoryEvent;
use App\Events\OrdersListEvent;
use App\Events\OrdersHistoryEvent;
use Carbon\Carbon;
use App\BidingLog;
use App\Events\BalanceEvent;
use App\Events\PairingDataEvent;
use App\Events\GetExchangeEvent;
use App\Coinhistory;
use App\BaseCurrency;
use App\CoinListing;
use App\DepositTransaction;
use App\UserAddresses;
use App\Withdraw;
use App\LatestTradeData;
use App\Last24hCoinhistory;
use App\RecentBidingLog;
use App\Referrals;
use App\Userinfo;
use App\User;

class RabbitmqController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public $WAIT_BEFORE_RECONNECT_uS = 1000000;

    

    public static function connect() {
      //Log::info("rabbitmq connect method");
    // If you want a better load-balancing, you cann reshuffle the list.
	     
      $conn=AMQPStreamConnection::create_connection([
		        ['host' => config('constants.Rabbitmq_host'), 'port' => config('constants.Rabbitmq_port'), 'user' => config('constants.Rabbitmq_username'), 'password' => config('constants.Rabbitmq_password')]
		    ],
		    [
		        'insist' => false,
		        'login_method' => 'AMQPLAIN',
		        'login_response' => null,
		        'locale' => 'en_US',
		        'connection_timeout' => 3.0,
		        'read_write_timeout' => 3.0,
		        'context' => null,
		        'keepalive' => false,
		        'heartbeat' => 0
		    ]);
      //Log::info("RM connection ".json_encode($conn));
      return $conn;
	}

	public static function cleanup_connection($connection) {
	    // Connection might already be closed.
	    // Ignoring exceptions.
	    try {
	        if($connection !== null) {
	            $connection->close();
	        }
	    } catch (\ErrorException $e) {
	    }
	}

 public function consume(){
	//Log::info("consume Started"); 	

	$connection = null;

	while(true){
	    try {
        //Log::info("Rabbitmq Connection before connect");
	        $connection = self::connect();
	        //register_shutdown_function('shutdown', $connection);
	        // Your application code goes here.
	        //Log::info("Rabbitmq Connection Started");
	        self::consumerChannels($connection);
	    } catch(AMQPRuntimeException $e) {
	    	//Log::info("Rabbitmq Connection stop1");
	        echo $e->getMessage() . PHP_EOL;
	        self::cleanup_connection($connection);
	        usleep($this->WAIT_BEFORE_RECONNECT_uS);
	    } catch(\RuntimeException $e) {
	    	//Log::info("Rabbitmq Connection stop2");
	        echo "Runtime exception " . PHP_EOL;
	        self::cleanup_connection($connection);
	        usleep($this->WAIT_BEFORE_RECONNECT_uS);
	    } catch(\ErrorException $e) {
	    	//Log::info("Rabbitmq Connection stop3");
        //Log::info(json_encode($e->getMessage()));
	        echo "Error exception " . PHP_EOL;
	        self::cleanup_connection($connection);
	        usleep($this->WAIT_BEFORE_RECONNECT_uS);
	    }
	}

}

    public static function consumerChannels($connection){

         $consumerTag = 'consumer';
         $exchange = 'router';
 
         //$connection = new AMQPConnection(config('constants.Rabbitmq_host'), config('constants.Rabbitmq_port'), config('constants.Rabbitmq_username'), config('constants.Rabbitmq_password'));
         //$channel = $connection->channel();
         //$connection = self::connect();
         $channel = $connection->channel();
 
  
          //$channel->queue_declare('buy_sell_queue', false, false, false, false);
         $channel->queue_declare('sell_queue', false, false, false, false);
         $channel->queue_declare('buy_queue', false, false, false, false); 
         $channel->queue_declare('buyorders_queue', false, false, false, false); 
         $channel->queue_declare('sellorders_queue', false, false, false, false); 
        /* $channel->queue_declare('tradehistory_queue', false, false, false, false); 
         $channel->queue_declare('orderslist_queue', false, false, false, false); 
         $channel->queue_declare('ordershistory_queue', false, false, false, false); 
         $channel->queue_declare('balance_queue', false, false, false, false); */
         $channel->queue_declare('pairingdata_queue', false, false, false, false); 
         //$channel->queue_declare('getexchange_queue', false, false, false, false);
         $channel->queue_declare('coinwatches', false, true, false, false);
         //$channel->queue_declare('bal_order_list_history_queue', false, false, false, false);
         //$channel->queue_declare('buy_sell_trade_exchange_queue', false, false, false, false);
          //Log::info("broadcast cosumer");
          /*$channel->basic_consume('buy_sell_queue', 'buy_sell_queue', false, true, false, false, function ($message)
         //{
           // self::matchengine($message);
         //});*/
         $channel->basic_consume('sell_queue', 'sell_queue', false, true, false, false, function ($message)
         {
            self::matchengine($message);
         });

         $channel->basic_consume('buy_queue', 'buy_queue', false, true, false, false, function($message){
          self::matchengine($message);
         });       
        /* $channel->basic_consume('bal_order_list_history_queue', 'bal_order_list_history_queue', false, true, false, false, function($message){
          self::bal_order_list_history($message);
         });*/
         /*$channel->basic_consume('buy_sell_trade_exchange_queue', 'buy_sell_trade_exchange_queue', false, true, false, false, function($message){
          self::buy_sell_trade_exchange($message);
         });*/
         $channel->basic_consume('buyorders_queue', 'buyorders_queue', false, true, false, false, function($message){
          self::buyorders($message);
         }); 

         $channel->basic_consume('sellorders_queue', 'sellorders_queue', false, true, false, false, function($message){
          self::sellorders($message);
         }); 
        /* $channel->basic_consume('tradehistory_queue', 'tradehistory_queue', false, true, false, false, function($message){
          Log::info("tradehistory consume");
            self::tradehistory_broadcast($message);
         });
         $channel->basic_consume('orderslist_queue', 'orderslist_queue', false, true, false, false, function($message){
            self::orderslist_broadcast($message);
         });
         $channel->basic_consume('ordershistory_queue', 'ordershistory_queue', false, true, false, false, function($message){
            self::ordershistory_broadcast($message);
         });
         $channel->basic_consume('balance_queue', 'balance_queue', false, true, false, false, function($message){
            self::balance_broadcast($message);
         });*/
         $channel->basic_consume('pairingdata_queue', 'pairingdata_queue', false, true, false, false, function($message){
            self::pairingdata_broadcast($message);
         });
         /*$channel->basic_consume('getexchange_queue', 'getexchange_queue', false, true, false, false, function($message){
          self::getexchange_broadcast($message);
         });*/
         $channel->basic_consume('coinwatches', 'coinwatches', false, false, false, false, function($message){
          //Log::info("blockchain watcher coinwatches");
          self::watcher_broadcast($message);
         });
         while ($channel->is_consuming()) {
	        $channel->wait();
	    }

         // while (count($channel ->callbacks)) {
         //             $channel->wait();
         //             //usleep(300000);
         //         } 

    }

    public static function matchengine($message)
         {

            Log::info("consume data");
            Log::info(json_encode($message));

           $res = json_decode($message->body);
           $exchange_id = $res->exchange_id;
           $user_id = $res->user_id;
           $market_symbol = $res->market_symbol;
           $coin_symbol = $res->coin_symbol;

           if($res->queue_type == "buy"){
                ExchangeController::BuyTradeMatchingEngine($exchange_id,$user_id,$market_symbol,$coin_symbol);
           }else{
                ExchangeController::sellTradeMatchingEngine($exchange_id,$user_id,$market_symbol,$coin_symbol);
           }
           Log:info("match engine ack ".$exchange_id);
           // $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
     
             // Send a message with the string "quit" to cancel the consumer.
             /*if ($message->body === 'quit') {
                 $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
             }*/
         }
      /*public static function deposit_broadcast($message){
        $res = json_decode($message->body);
        Log::info("deposit broadcast payload ".json_encode($res));
      }*/
    public static function watcher_broadcast($message){
      $res = json_decode($message->body);
      Log::info("blockchain watcher ".json_encode($res));
      if(empty($res->is_user_to_admin)){
        Log::info("blockchain watcher2 ");
        self::w_broadcast($message);
      }else{
        if($res->is_user_to_admin!=true){
          Log::info("blockchain watcher3 ");
          self::w_broadcast($message);
        }
        
      }
     
    }
    public static function w_broadcast($message){
      $res = json_decode($message->body);
        if(!empty($res->trans_type)){
          if($res->trans_type=="send"){
            Log::info("blockchain watcher withdraw ");
            self::withdraw_broadcast($message);
          }else if($res->trans_type=="receive"){
            Log::info("blockchain watcher deposit ");
            self::deposit_broadcast($message);

          }else{
            Log::info("blockchain watcher trans_type not available.".json_encode($res));
          }

        }else{
          Log::info("blockchain watcher trans_type param not available.".json_encode($res));
        }
        
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag'],true);
    }
    public static function deposit_broadcast($message){
      $res = json_decode($message->body);
      Log::info("deposit broadcast payload ".json_encode($res));

      $deposit_trans=DepositTransaction::where('transaction_hash',$res->transaction_hash)->first();
      if($deposit_trans!==null){
       // Log::info("deposit transaction ".json_encode($deposit_trans));
        if($deposit_trans['status']!=1){

          if($res->status=="completed"){
            $deposit_trans->status=1;
          }else{
            $deposit_trans->status=0;
          }
          $remarks="Reload transaction from others";
          if(!empty($res->remarks)){
            $remarks=$res->remarks;
          }
          $description="";
          if(!empty($res->description)){
            $description=$res->description;
          }
          $deposit_trans->bc_value=$res->amount;
          $deposit_trans->blockchain_status=$res->status;
          $deposit_trans->confirmations=$res->confirmations;
          $deposit_trans->total_confirmations=$res->confirmations;
          $deposit_trans->transaction_from="others";
          $deposit_trans->remarks=$remarks;
          $deposit_trans->description=$description;
          $deposit_trans->updated_at=date("Y-m-d H:i:s");
          $deposit_trans->save();
          if($res->status=="completed"){
            Log::info("depositbroadcastpayload ".$deposit_trans['user_id']);
              $balance = Balance::where('user_id', $deposit_trans['user_id'])->where('currency_symbol',$deposit_trans['crypto_symbol'])->first();
              //Log::info("deposit balance ".json_encode($balance));
              if($balance===null){
                //Log::info("deposit balance is null");
                  $bdata=array(
                          "user_id"=>$deposit_trans['user_id'],
                          "currency_symbol"=>$deposit_trans['crypto_symbol'],
                          "main_balance"=>$deposit_trans['internal_value'],
                          "created_at"=>date("Y-m-d H:i:s")
                      );
                      Balance::insert($bdata);
              }else{
                //Log::info("deposit balance available ".json_encode($balance));
                $balance->main_balance=floatval($balance['main_balance'])+floatval($deposit_trans['internal_value']);
                $balance->save();
              }

              $userinfo=Userinfo::join('users','users.user_id','=','userinfo.user_id')->select('userinfo.first_name','userinfo.last_name','users.email')->where("userinfo.user_id",$deposit_trans['user_id'])->first();
              $email=$userinfo['email'];
              $subject = "Deposit";
              $title = "Deposit";
              if($userinfo['first_name'] != ""){
                $username = ucwords($userinfo['first_name']." ".$userinfo['last_name']);
              }else{
                $username = $email;
              }

              if($res->transaction_hash==""){
                $hash_link="";
              }else{
                //$hash_link="hash_link";
                if($deposit_trans['crypto_symbol']=="BTC"){
                  $hash_link="https://live.blockcypher.com/btc/tx/".$res->transaction_hash;
                }else if($deposit_trans['crypto_symbol']=="LTC"){
                  $hash_link="https://live.blockcypher.com/ltc/tx/".$res->transaction_hash;
                }else if($deposit_trans['crypto_symbol']=="BCH"){
                  $hash_link="https://www.blockchain.com/bch/tx/".$res->transaction_hash;
                  
                }else if($deposit_trans['crypto_symbol']=="ETC"){
                  $hash_link="https://blockscout.com/etc/mainnet/tx/".$res->transaction_hash;
                  
                }else{
                  $hash_link="https://etherscan.io/tx/".$res->transaction_hash;
                }
                
              }
              Log::info("depositbroadcastpayload21".$deposit_trans['user_id']);
              $message = "Deposit amount <strong>".number_format_eight_dec($deposit_trans['internal_value'])." ".$deposit_trans['crypto_symbol']."</strong> has been added to your Main Wallet.<br/> Transaction hash is <a href=".$hash_link." target='_blank'>".$res->transaction_hash."</a>";
              $edata['useremail'] = array( 'username' => $username, 'email' => $email,"title"=>$title,'message'=>$message,"website_url"=>config('constants.APPLICATION_URL'));

              Mail::send(['html'=>'email_templates.deposit_email_new'], $edata, function($message) use ($userinfo,$email,$subject) {
                $message->to($email, $userinfo['first_name']." ".$userinfo['last_name'])->subject($subject);
                  $message->from('support@brexily.com ','Brexily');
                });
              Log::info("depositbroadcastpayload31".$deposit_trans['user_id']);
              //50 USDT deposit bonus for >= 500 deposit amount of BTC,ETH and USDT 
              self::depositBonus($deposit_trans['user_id'],$deposit_trans['crypto_symbol'],$deposit_trans['internal_value']);

            }

        }else{
          Log::error("deposit transaction hash  ".$res->transaction_hash." already completed.");
        }
        
      }else{
        $useraddress=UserAddresses::whereRaw('UPPER(wallet_symbol) = ?', [strtoupper($res->coin)])->whereRaw('UPPER(wallet_address) = ?', [strtoupper($res->to)])->first();
        if($useraddress==null){
          $wallet_symbol = strtoupper($res->coin);
          $user_id = $res->user_id;
          $wallet_address = $res->to;
          $idata=array(
              "user_id"=>$user_id,
              "wallet_symbol"=>$wallet_symbol,
              "wallet_address"=>$wallet_address,
              "status"=>1
            );
          UserAddresses::updateOrCreate(["user_id" => $user_id,"wallet_symbol"=>$wallet_symbol], $idata);
        }
          $coin_info=CoinListing::whereRaw('UPPER(coin_symbol) = ?', [strtoupper($useraddress->wallet_symbol)])->first();
            $depositId=time().rand(0,100);
            if($res->status=="completed"){
              $status=1;
            }else{
              $status=0;
            }
            $remarks="Reload transaction from others";
            if(!empty($res->remarks)){
              $remarks=$res->remarks;
            }
            $description="";
            if(!empty($res->description)){
              $description=$res->description;
            }
            $idata=array(
              "user_id"=>$useraddress->user_id,
              "deposit_id"=>$depositId,
              "crypto_type_id"=>$coin_info['id'],
              'crypto_symbol'=>$useraddress->wallet_symbol,
              "internal_value"=>exp2dec($res->amount),
              "bc_value"=>exp2dec($res->amount),
              "transaction_hash"=>$res->transaction_hash,
              "fiat_value"=>"",
              "fiat_currency"=>"",
              "description"=>$description,
              "status"=>$status,
              "blockchain_status"=>$res->status,
              "confirmations"=>$res->confirmations,
              "total_confirmations"=>$res->confirmations,
              "transaction_from"=>"others",
              "verify_email_status"=>1,
              "remarks"=>$remarks,
              "created_by"=>0,
              "created_at"=>date("Y-m-d H:i:s")
            );
            $dtId=DepositTransaction::insertGetId($idata);
            if($res->status=="completed"){
              Log::info("depositbroadcastpayload1 ".$useraddress->user_id);
              $balance = Balance::where('user_id', $useraddress->user_id)->where('currency_symbol',$useraddress->wallet_symbol)->first();
              if($balance===null){
                  $bdata=array(
                          "user_id"=>$useraddress->user_id,
                          "currency_symbol"=>$useraddress->wallet_symbol,
                          "main_balance"=>$res->amount,
                          "created_at"=>date("Y-m-d H:i:s")
                      );
                      Balance::insert($bdata);
              }else{
                $balance->main_balance=floatval($balance['main_balance'])+floatval($res->amount);
                $balance->save();
              }

              $userinfo=Userinfo::join('users','users.user_id','=','userinfo.user_id')->select('userinfo.first_name','userinfo.last_name','users.email')->where("userinfo.user_id",$useraddress->user_id)->first();
              $email=$userinfo['email'];
              $subject = "Deposit";
              $title = "Deposit";

              if($userinfo['first_name'] != ""){
                $username = ucwords($userinfo['first_name']." ".$userinfo['last_name']);
              }else{
                $username = $email;
              }

              if($res->transaction_hash==""){
                $hash_link="";
              }else{
                //$hash_link="hash_link";
                if($useraddress->wallet_symbol=="BTC"){
                  $hash_link="https://live.blockcypher.com/btc/tx/".$res->transaction_hash;
                }else if($useraddress->wallet_symbol=="LTC"){
                  $hash_link="https://live.blockcypher.com/ltc/tx/".$res->transaction_hash;
                }else if($useraddress->wallet_symbol=="BCH"){
                  $hash_link="https://www.blockchain.com/bch/tx/".$res->transaction_hash;
                  
                }else if($useraddress->wallet_symbol=="ETC"){
                  $hash_link="https://blockscout.com/etc/mainnet/tx/".$res->transaction_hash;
                  
                }else{
                  $hash_link="https://etherscan.io/tx/".$res->transaction_hash;
                }
                
              }
              $message = "Deposit amount <strong>".number_format_eight_dec($res->amount)." ".$useraddress->wallet_symbol."</strong> has been added to your Main Wallet.<br/> Transaction hash is <a href=".$hash_link." target='_blank'>".$res->transaction_hash."</a>";
              Log::info("depositbroadcastpayload2".$useraddress->user_id);
              $edata['useremail'] = array( 'username' => $username, 'email' => $email,"title"=>$title,'message'=>$message,"website_url"=>config('constants.APPLICATION_URL'));

              Mail::send(['html'=>'email_templates.deposit_email_new'], $edata, function($message) use ($userinfo,$email,$subject) {
                $message->to($email, $userinfo['first_name']." ".$userinfo['last_name'])->subject($subject);
                  $message->from('support@brexily.com ','Brexily');
                });
              Log::info("depositbroadcastpayload3".$useraddress->user_id);
              //50 USDT deposit bonus for >= 500 deposit amount of BTC,ETH and USDT 
              self::depositBonus($useraddress->user_id,$useraddress->wallet_symbol,$res->amount);
            }

        // }else{
        //   Log::error("deposit receiver address ".$res->to." not found");
        // }

      }
      
    }
    public static function depositBonus($user_id,$wallet_symbol,$amount){
      Log::info("start: depositBonus ");
        //$currency_arr = array('BTC','ETH','USDT');//coinlisting status for each coin for deposit
        $currency_symbol = strtoupper($wallet_symbol);
        $dep_cont = DB::table('deposit_bonus_constants')->first();
        if($dep_cont->status == 1){
          //deposit bonus flag enable/disable
        //check condition for paid deposit bonus
          $res = DB::table('deposit_bonus_transactions')->select('user_id')->where('user_id',$user_id)->where('from_user_id',$user_id)->first();
          Log::info("transactions: depositBonus ".json_encode($res));
          if(empty($res)){
               $cl_res = CoinListing::select('coin_price')->where('coin_symbol',$currency_symbol)->where('depositbonus_status',1)->first();
               Log::info("CoinListing: depositBonus ".json_encode($cl_res));
              if(!empty($cl_res)){
              //if(in_array($currency_symbol, $currency_arr)){
               
                $deposit_amt_in_usd = $amount*$cl_res->coin_price;
                Log::info("deposit_amt_in_usd: depositBonus ".$deposit_amt_in_usd);
                if($deposit_amt_in_usd >= $dep_cont->max_deposit_amt){

                   $upliner = Referrals::select('*')->where('descendant_id',$user_id)->whereIn('distance', array(0,1))->get();
                   Log::info("upliner: depositBonus ".json_encode($upliner));
                   if(!empty($upliner)){
                    Log::info("upliner1: depositBonus ");
                      foreach ($upliner as $key => $value) {
                        Log::info("upliner2: depositBonus ".json_encode($value));
                              $ancestor_id = $value->ancestor_id;//downliner user id
                //Log::info("user_id: depositBonus ".$ancestor_id." deposit_amount ".$deposit_amt_in_usd);
                              $bonus = $dep_cont->deposit_bonus;
                              Log::info("user_id: depositBonus ".$deposit_amt_in_usd);
                              $rs = DB::table('reward_incentives')->where('user_id',$ancestor_id)->where('reward_id',3)->first();
                              if($rs){
                                Log::info("bonus1: depositBonus ".$bonus);
                                $query = DB::table('reward_incentives')
                                          ->where('user_id',$ancestor_id)->where('reward_id',3);
                                $query->increment('total_qty',floatval($bonus));
                                $query->increment('balance',floatval($bonus));
                              }else{
                                Log::info("bonus2: depositBonus ".$bonus);
                                $insertObj = array(
                                  "user_id"=>$ancestor_id,
                                  "reward_id"=>3,
                                  "currency_symbol"=>'USDT',
                                  "total_qty"=>$bonus,
                                  "used"=>0,
                                  "balance"=>$bonus,
                                  "sufficient_evr"=>1,
                                );
                                      
                                $cron_id = DB::table('reward_incentives')->insertGetId($insertObj);
                              }
                              Log::info("bonusss: depositBonus ");

                            $insertObj1 = array(
                                      "user_id"=>$ancestor_id,
                                      "reward_id"=>3,
                                      "currency_symbol"=>'USDT',
                                      "total_qty"=>$bonus,
                                      "used"=>0,
                                      "balance"=>$bonus,
                                      "sufficient_evr"=>1,
                                      "job_name"=>'Deposit Bonus',
                                    );
                            $cron_id = DB::table('reward_incentives_audit')->insertGetId($insertObj1);

                            $insertObj2 = array(
                                  "user_id"=>$ancestor_id,                                  
                                  "deposit_wallet"=>$currency_symbol,
                                  "deposit_amt_in_usd"=>$deposit_amt_in_usd,
                                  "from_user_id"=>$user_id,
                                  "reward_id"=>3,
                                  "bonus_wallet"=>'USDT',
                                  "bonus"=>$bonus,
                                  "type"=>'IN'
                                );
                                      
                            $cron_id = DB::table('deposit_bonus_transactions')->insertGetId($insertObj2);
                            
                            $userinfo=Userinfo::join('users','users.user_id','=','userinfo.user_id')->select('userinfo.first_name','userinfo.last_name','users.email')->where("userinfo.user_id",$ancestor_id)->first();
                            $email=$userinfo['email'];
                            $subject = "Deposit Bonus";
                            $title = "Deposit Bonus";
                            if($userinfo['first_name'] != ""){
                              $username = ucwords($userinfo['first_name']." ".$userinfo['last_name']);
                            }else{
                              $username = $email;
                            }
                            $message = "Deposit Bonus <strong>".number_format_two_dec($bonus)." USDT</strong> has been added to your Deposit Wallet.";
                            $edata['useremail'] = array( 'username' => $username, 'email' => $email,"title"=>$title,'message'=>$message,"website_url"=>config('constants.APPLICATION_URL'));

                            Mail::send(['html'=>'email_templates.deposit_email_new'], $edata, function($message) use ($userinfo,$email,$subject) {
                              $message->to($email, $userinfo['first_name']." ".$userinfo['last_name'])->subject($subject);
                                $message->from('support@brexily.com ','Brexily');
                              });
                          }
                    }
              }
            }
          }
        }
        Log::info("end: depositBonus ");
    }
    public static function withdraw_broadcast($message){
        $res = json_decode($message->body);
        Log::info("withdraw broadcast payload ".json_encode($res));
        $withdraw_trans=Withdraw::where('transaction_hash',$res->transaction_hash)->get();
        if($withdraw_trans!==null){
          foreach ($withdraw_trans as $w) {
            if($res->status=="completed"){
              $status=3;
            }else{
              $status=1;
            }
            $w->bc_value=$res->amount;
            $w->blockchain_status=$res->status;
            $w->confirmations=$res->confirmations;
            $w->total_confirmations=$res->confirmations;
            $w->status=$status;
            $w->save();
          }
          
        }else{
          Log::error("withdraw record not found with the transaction hash ".$res->transaction_hash);
        }
    }
    public static function buy_sell_trade_exchange($message){
      Log::info("buy_sell_trade_exchange start ");
      self::buyorders($message);
      Log::info("buy_sell_trade_exchange1 start ");
      self::sellorders($message);
      Log::info("buy_sell_trade_exchange2 start ");
      self::getexchange_broadcast($message);
      Log::info("buy_sell_trade_exchange3 start ");
      self::tradehistory_broadcast($message);

    }
    public static function bal_order_list_history($message){
      Log::info("bal_order_list_history start ");
      self::balance_broadcast($message);
      Log::info("bal_order_list_history1 start ");
      self::orderslist_broadcast($message);
      Log::info("bal_order_list_history2 start ");
      self::ordershistory_broadcast($message);
      
      
    }
    public static function buy_sell($message){
      Log::info("buy_sell_trade_exchange start ");
      self::buyorders($message);
      Log::info("buy_sell_trade_exchange1 start ");
      self::sellorders($message);
    
    }
    public static function bal_order_list($message){
      Log::info("bal_order_list_history start ");
      self::balance_broadcast($message);
      Log::info("bal_order_list_history1 start ");
      self::orderslist_broadcast($message);
      
    }
    public static function buyorders($message){

      /*$res = json_decode($message->body);
      $market_symbol = $res->market_symbol;*/
      $msg = json_decode($message, true);
      $market_symbol = $msg['market_symbol'];
      //Log::info("buy orders start");
    
      /*$trades =DB::select(
          'call brexily_buyorders_list_proc(?, ?)',
          [
              2,$market_symbol
        
          ]
      );*/
      //Log::info("buy orders2 start ".json_encode($trades));
      $trades = Biding::select("*",DB::raw('SUM(bid_qty_available) as total_qty'),DB::raw('SUM(`bid_qty_available`*`bid_price`) as total_price'))
      ->where("status","=",2)
      ->where("market_symbol","=",$market_symbol)
      ->where("bid_type","=","BUY")
      ->groupBy('bid_price')
      ->orderBy('bid_price','desc')
      ->limit(15)
      ->get();
      $tradesArr = array();
      $bqty=array();
      if(@count($trades)){
        foreach($trades as $res){

          //$tradesArr[] = $res;
          $bqty[$res->bid_price.""]=floatval(@$bqty[$res->bid_price.""])+floatval($res->total_qty);
          $tradesArr[$res->bid_price.""] = array(
                  "id"=>$res->id,
                  "bid_price"=>number_format_eight_dec(exp2dec($res->bid_price)),
                  //"r_bid_price"=>$res->bid_price,
                  "total_qty"=>number_format_eight_dec($bqty[$res->bid_price.""]),
                  "total_price"=>number_format_eight_dec(floatval($res->bid_price)*floatval($bqty[$res->bid_price.""])),
                  );
        } 
      }
      $tradArr=array_values($tradesArr);
      //usort($tradArr, "bidprice_sort");
      broadcast(new BuyordersEvent(['Result' => $tradArr,"market_symbol"=>$market_symbol],$market_symbol))->toOthers();

     
    }

    public static function sellorders($message){

      /*$res = json_decode($message->body);
      $market_symbol = $res->market_symbol;*/

      $msg = json_decode($message, true);
      $market_symbol = $msg['market_symbol'];
      /*$trades =DB::select(
          'call brexily_sellorders_list_proc(?, ?)',
          [
              2,$market_symbol
        
          ]
      );*/
      $trades = Biding::select("*",DB::raw('SUM(bid_qty_available) as total_qty'),DB::raw(    'SUM(`bid_qty_available`*`bid_price`) as total_price'))
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
      
      broadcast(new WebsocketDemoEvent(['Result' => $tradArr,"market_symbol"=>$market_symbol],$market_symbol))->toOthers();

     
      
    }
    public static function getexchange_broadcast($message){
        /*$res = json_decode($message->body);
        $market_symbol = $res->market_symbol;*/
        $res = json_decode($message, true);
        $market_symbol = $res['market_symbol'];
        $coin_symbol = explode('_', $market_symbol);
        $buy = $coin_symbol[0];
        $sell = $coin_symbol[0];
        
        //Log::info("getexchange_broadcast start ");
        //$data['fee_to'] = $this->web_model->checkFees('BUY', $coin_symbol[1]);
        $coinListTo = CoinListing::select("id","coin_name","coin_image","coin_price")->where("coin_symbol","=",$coin_symbol[1])->where("status","=",1)->first();
        $coin_idTo = $coinListTo['id'];
        
        /*$feeto = BaseCurrency::select("*")->where("coin_id","=",$coin_idTo)->first();
        if(!empty($feeto)){
          $fee_to = ($feeto->trading_maker_fee)?$feeto->trading_maker_fee:0.00;
        }else{*/
          $fee_to = 0.00;
        //}
        
        
            //$data['fee_from']       = $this->web_model->checkFees('SELL', $coin_symbol[0]);
        
        $coinList = CoinListing::select("id","coin_name","coin_image","coin_price")->where("coin_symbol","=",$coin_symbol[0])->where("status","=",1)->first();
        $coin_id = $coinList['id'];
        $coin_name = $coinList['coin_name'];
        $coin_image = ($coinList['coin_image'])?$coinList['coin_image']:"";
        $coin_price_dollar = ($coinList['coin_price'])?$coinList['coin_price']:0;
        /*$feeFrom = BaseCurrency::select("*")->where("coin_id","=",$coin_id)->first();
        if(!empty($feeFrom)){
          $fee_from = ($feeFrom->trading_taker_fee)?$feeFrom->trading_taker_fee:0.00;
        }else{*/
          $fee_from = 0.0;
        //}
        
        $coinhistory=LatestTradeData::where('market_symbol', $market_symbol)->first(); 
        if($coinhistory===null){
          $coinhistory = Coinhistory::select('*')->where('market_symbol', $market_symbol)->orderBy("id","DESC")->LIMIT(1)->first();
        }

        
        
        if($coinhistory!==null){
          //DB::enableQueryLog(); 
          $coindata = Last24hCoinhistory::select(\DB::raw('MAX(open) as max_open,MAX(close) as max_close,MIN(open) as min_open,MIN(close) as min_close'))->where('market_symbol', $market_symbol)->first();
          //$coindata = Coinhistory::select(\DB::raw('MAX(price_high_24h) as price_high_24h,MIN(price_low_24h) as price_low_24h'))->where('date','>=',\DB::raw( 'DATE_SUB(NOW(), INTERVAL 24 HOUR)'))->where('market_symbol', $market_symbol)->orderBy('date')->first();
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
            
            $coindata = Coinhistory::select('last_price')->where('market_symbol', $market_symbol)->orderBy('date','desc')->first();
            //dd(DB::getQueryLog());
            if($coindata!==null){
              $high_price = $coindata->last_price;
              $low_price = $coindata->last_price;
            }else{
              $high_price = 0;
              $low_price = 0;
            }
          }
          $coin_last_price = $coinhistory['last_price'];
          $coinTo_usd_price=$coinListTo['coin_price'];
          $coin_usd_price=floatval($coin_last_price)*floatval($coinTo_usd_price);
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
          
          $coinhistoryArr = array(
                    "coin_price_dollar"=>number_format_six_dec_currency($coin_usd_price),
                    "coin_last_price"=>number_format_eight_dec(exp2dec($coin_last_price)),
                    "coin_change_price"=>number_format_two_dec($price_change_percent2),
                    "price_high_24h"=>($high_price)?number_format_eight_dec(exp2dec($high_price)):0,
                    "price_low_24h"=>($low_price)?number_format_eight_dec(exp2dec($low_price)):0,
                    "total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0]." / ".number_format_four_dec($volume)." ".$coin_symbol[1],
                    "coin_total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0],
                    "basecurrency_total_volume"=>number_format_four_dec($volume)." ".$coin_symbol[1]
                    );
          
        }else{
          $total_volume = 0;
          $coin_last_price = 0;
          $coinhistoryArr = array(
                    "coin_price_dollar"=>number_format_six_dec_currency($coin_price_dollar),
                    "coin_last_price"=>"0.00",
                    "coin_change_price"=>"0.00",
                    "price_high_24h"=>"0.00",
                    "price_low_24h"=>"0.00",
                    "total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0]." / ".number_format_four_dec($coin_last_price)." ".$coin_symbol[1],
                    "coin_total_volume"=>number_format_four_dec($total_volume)." ".$coin_symbol[0],
                    "basecurrency_total_volume"=>number_format_four_dec($coin_last_price*$total_volume)." ".$coin_symbol[1]
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
                "fee_to"=>$fee_to,
                "fee_from"=>$fee_from,
                "coinhistoryArr"=>$coinhistoryArr
                );
        //Log::info("getexchange_broadcast end ");
      broadcast(new GetExchangeEvent(['Result' => $exchangeArr,"market_symbol"=>$market_symbol],$market_symbol));

      // $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
     
      //  // Send a message with the string "quit" to cancel the consumer.
      //  if ($message->body === 'quit') {
      //      $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
      //  }
    }
    public static function pairingdata_broadcast($message){
      $res = json_decode($message->body);
      $market_symbol = $res->market_symbol;
      $coin_symbol=explode("_", $market_symbol);
      $price = $res->price;
      $volume = $res->volume;
      $change=$res->change;

      /*$res = json_decode($message, true);
      $market_symbol = $res['market_symbol'];
      $price = $res['price'];
      $volume = $res['volume'];
      $change = $res['change'];*/

      $pairingdataArr=array(
        "market_price"=>$price,
        "volume"=>$volume,
        "change"=>$change
       
      );
      //Log::info("pairingdata broadcast ".json_encode($pairingdataArr));
      broadcast(new PairingDataEvent(['Result' => $pairingdataArr,"market_symbol"=>$market_symbol],$coin_symbol[1]));

      /*$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
     
       // Send a message with the string "quit" to cancel the consumer.
       if ($message->body === 'quit') {
           $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
       }*/
    }
    
    public static function balance_broadcast($message){
      /*$res = json_decode($message->body);
      $market_symbol = $res->market_symbol;
      $user_id = $res->user_id;*/
      $res = json_decode($message, true);
      $market_symbol = $res['market_symbol'];
      $user_id = $res['user_id'];

      $coin_symbol = explode('_', $market_symbol);
      $buy = $coin_symbol[0];
      $sell = $coin_symbol[0];
      
      $balanceTo = Balance::where('user_id', $user_id)->where('currency_symbol', $coin_symbol[1])->first();
      if(!empty($balanceTo)){
        $balance_to = $balanceTo['balance'];
      }else{
        $balance_to = "0.00";
      }
      $balanceFrom = Balance::where('user_id', $user_id)->where('currency_symbol', $coin_symbol[0])->first();
    
      if(!empty($balanceFrom)){
        $balance_from = $balanceFrom['balance'];
      }else{
        $balance_from = "0.00";
      }
      $balanceArr=array(
        "balance_to"=>$balance_to,
        "balance_from"=>$balance_from
      );
      //Log::info("balance broadcast ".$market_symbol);
      broadcast(new BalanceEvent(['Result' => $balanceArr,"market_symbol"=>$market_symbol],$user_id,$market_symbol));

      /*$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
     
       // Send a message with the string "quit" to cancel the consumer.
       if ($message->body === 'quit') {
           $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
       }*/
    }

    public static function orderslist_broadcast($message){
      //Log::info("orderlist broadcast ".$message);
      /*$res = json_decode($message->body);
      $market_symbol = $res->market_symbol;
      $user_id = $res->user_id;*/
      $res = json_decode($message, true);
      $market_symbol = $res['market_symbol'];
      $user_id = $res['user_id'];
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
            $changed_market_symbol = str_replace("_", " / ", $value->market_symbol);
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
        //Log::info("orders list broadcast ".$market_symbol);
        broadcast(new OrdersListEvent(['Result' => $ordersListArr,"market_symbol"=>$market_symbol],$user_id,$market_symbol));
      }
      /*$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
     
       // Send a message with the string "quit" to cancel the consumer.
       if ($message->body === 'quit') {
           $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
       }*/
    }

    public static function ordershistory_broadcast($message){
      Log::info("order history broadcast ".$message);
        /*$res = json_decode($message->body);
        $market_symbol = $res->market_symbol;
        $user_id = $res->user_id;*/
        $res = json_decode($message, true);
        $market_symbol = $res['market_symbol'];
        $user_id = $res['user_id'];
        

        //Log::info("ordershistory_broadcast start");
        if(!empty($market_symbol)){
          if(empty($res['latest_bidding_log_orders'])){

            /*$ordersList = DB::table('dbt_biding as bidmaster')
             ->leftJoin('recent_biding_log as biddetail', 'biddetail.bid_id', '=', 'bidmaster.id')
              ->select('bidmaster.id','bidmaster.bid_type','bidmaster.bid_price','bidmaster.bid_qty','bidmaster.market_symbol','bidmaster.bid_qty_available','bidmaster.total_amount','bidmaster.amount_available','biddetail.complete_qty', 'biddetail.complete_amount', 'biddetail.success_time', 'biddetail.status')
             ->where("bidmaster.user_id","=", $user_id)
            ->where("biddetail.market_symbol","=",$market_symbol)->orderBy('biddetail.success_time','desc')->limit(1)
             ->get();*/
             $ordersList = RecentBidingLog::select("*")->where('market_symbol', $market_symbol)->where('user_id',$user_id)->orderBy("success_time","DESC")->limit(1)->get();
          }else{
            $latest_bidding_log_orders = $res['latest_bidding_log_orders'];
            /*$ordersList = DB::table('dbt_biding as bidmaster')
             ->leftJoin('recent_biding_log as biddetail', 'biddetail.bid_id', '=', 'bidmaster.id')
              ->select('bidmaster.id','bidmaster.bid_type','bidmaster.bid_price','bidmaster.bid_qty','bidmaster.market_symbol','bidmaster.bid_qty_available','bidmaster.total_amount','bidmaster.amount_available','biddetail.complete_qty', 'biddetail.complete_amount', 'biddetail.success_time', 'biddetail.status')
             ->where("bidmaster.user_id","=", $user_id)
             ->whereIn("biddetail.order_id", $latest_bidding_log_orders)
            ->where("biddetail.market_symbol","=",$market_symbol)->orderBy('biddetail.success_time','desc')
             ->get();*/
             $ordersList = RecentBidingLog::select("*")->where('market_symbol', $market_symbol)->whereIn('order_id',$latest_bidding_log_orders)->where('user_id',$user_id)->orderBy("success_time","DESC")->get();
                 
          }
         //Log::info("ordershistory_broadcast2 start");   
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
              $changed_market_symbol = str_replace("_", " / ", $value->market_symbol);
              $ordersListArr[] = array(
                    "id"=>$value->id,
                    "bid_type"=>$value->bid_type,
                    "bid_price"=>number_format_eight_dec($value->bid_price),
                    "bid_qty"=>number_format_eight_dec($value->complete_qty),
                    "order_date"=>date("d-m-Y H:i:s",strtotime($value->success_time)),
                    "pair"=>$changed_market_symbol,
                    "amount"=>number_format_eight_dec($value->complete_qty),
                    "fee"=>$fee,
                    "price"=>number_format_eight_dec($value->complete_qty),
                    "balance"=>number_format_eight_dec($value->complete_amount),
                    "status"=>$statusMsg,
                    
                    );
            } 
          }
          //Log::info("ordershistory_broadcast3 start");
          Log::info("orders history broadcast ".json_encode($ordersListArr));
          broadcast(new OrdersHistoryEvent(['Result' => $ordersListArr,"market_symbol"=>$market_symbol],$user_id,$market_symbol));
          
        }else{
          
        }
        /*$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
     
       // Send a message with the string "quit" to cancel the consumer.
       if ($message->body === 'quit') {
           $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
       }*/
    }


    public static function tradehistory_broadcast($message){
        /*$res = json_decode($message->body);
        $market_symbol = $res->market_symbol;*/
        $msg = json_decode($message, true);
        $market_symbol = $msg['market_symbol'];
        $latest_bidding_log_orders = $msg['latest_bidding_log_orders'];
         //Log::info("trade history data");
        //Log::info(json_encode($res));

         if(!empty($market_symbol)){
            $coin_symbol   = explode('_', $market_symbol);
            //$today = date("Y-m-d");
            //$todate = Carbon::parse($today)->format('Y-m-d H:i:s');
            //echo $todate;exit;
            //$tradehistory_info =  BidingLog::select("*")->where('market_symbol', $market_symbol)->where('success_time',">",$todate)->where("status",1)->where('trade_history_status',1)->orderBy("log_id","DESC")->limit(100)->get();//$this->web_model->tradeHistory($market_symbol);
            $tradehistory_info =  RecentBidingLog::select("*")->whereIn("order_id",$latest_bidding_log_orders)->where('market_symbol', $market_symbol)->where("status",1)->where('trade_history_status',1)->orderBy("log_id")->get();
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
            //Log::info("trade history ".$market_symbol);
            broadcast(new TradeHistoryEvent(['Result' => $tradehistory,"market_symbol"=>$market_symbol],$market_symbol))->toOthers();
           
            
          }else{
            
          }

          //$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
     
       /*// Send a message with the string "quit" to cancel the consumer.
       if ($message->body === 'quit') {
           $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
       }*/
        

    }
    
    public static function publish_channel($queue,$rabitmqData,$exchange){
      //$queue = 'sellorders_queue';
      //$exchange = 'router';
      //$connection = new AMQPConnection(config('constants.Rabbitmq_host'), config('constants.Rabbitmq_port'), config('constants.Rabbitmq_username'), config('constants.Rabbitmq_password'));
      $connection = self::connect();
      $channel = $connection->channel();
      $channel->queue_declare($queue, false, false, false, false);
      $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
      $channel->queue_bind($queue, $exchange);

      //$rabitmqData = json_encode(array("queue_type"=>"sellorders","market_symbol"=>$market_symbol));
      //Log::info("public channel data ".$queue);
      //Amqp::publish('', $rabitmqData , ['queue' => $queue]);
      $message = new AMQPMessage($rabitmqData);
      //Log::info("public channel data1 ".$queue);
      $channel->basic_publish($message, "", $queue);
      //Log::info("public channel data2 ".$queue);
      $channel->close();
      //Log::info("public channel data3 ".$queue);
      $connection->close();
      //Log::info("public channel data4 ".$queue);
    }

    
}
?>
