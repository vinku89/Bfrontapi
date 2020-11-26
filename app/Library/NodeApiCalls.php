<?php
namespace App\Library;
require_once __DIR__ . '/../../vendor/autoload.php';

use \GuzzleHttp\Client;
use \GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use App\ApiSessions;
class NodeApiCalls{
	

    public static function apiSessionsCheck($user_id){
        $apisession=ApiSessions::where('user_id',$user_id)->where('is_expired',0)->first();
        if($apisession!==null){
            return $apisession;
        }else{
            
            return config('constants.NODE_TOKEN_EXPIRED');
        }
    }

    public static function auth($user_id){
        $apisession=self::apiSessionsCheck($user_id);
        if($apisession==config('constants.NODE_TOKEN_EXPIRED')){
            return config('constants.NODE_TOKEN_EXPIRED');
        }
        $reqParamArray=array("userId"=>$user_id,"sessionId"=>$apisession['session_key'],"isAdmin"=>false);
        $res=self::apiCalls("auth/token",$reqParamArray);
        $resArr = json_decode($res->getBody());
        return $resArr;
       
        
    }
    public static function auth_at_withdraw($user_id,$session_key){
        
        $reqParamArray=array("userId"=>$user_id,"sessionId"=>$session_key,"isAdmin"=>false);
        $res=self::apiCalls("auth/token",$reqParamArray);
        $resArr = json_decode($res->getBody());
        return $resArr;
       
        
    }
    public static function wallet_creation_at_withdraw($user_id,$authtoken,$wallet_symbol,$is_erc20){
        
        $reqParamArray=array("coin"=>$wallet_symbol);
        $endpoint="";
        if($wallet_symbol=="BTC"){
            $endpoint="bitcoin/wallet";
        }else if($wallet_symbol=="LTC"){
            $endpoint="litecoin/wallet";
        }else if($wallet_symbol=="ETH"){
            $endpoint="ethereum/wallet";
        }else if($wallet_symbol=="BCH"){
            $endpoint="bitcoincash/wallet";
        }else if($wallet_symbol=="ETC"){
            $endpoint="ethereumclassic/wallet";
        }else if($is_erc20==1){
            $endpoint="erc20/wallet";
        }else{
            return (object)["success"=>false];
        }
        $res=self::apiCalls($endpoint,$reqParamArray,"POST",$authtoken);
        $resArr = json_decode($res->getBody());
        return $resArr;
        
    }
    public static function wallet_creation($user_id,$authtoken,$wallet_symbol,$is_erc20){
        $apisession=self::apiSessionsCheck($user_id);
        if($apisession==config('constants.NODE_TOKEN_EXPIRED')){
            return config('constants.NODE_TOKEN_EXPIRED');
        }
        $reqParamArray=array("coin"=>$wallet_symbol);
        $endpoint="";
        if($wallet_symbol=="BTC"){
            $endpoint="bitcoin/wallet";
        }else if($wallet_symbol=="LTC"){
            $endpoint="litecoin/wallet";
        }else if($wallet_symbol=="ETH"){
            $endpoint="ethereum/wallet";
        }else if($wallet_symbol=="BCH"){
            $endpoint="bitcoincash/wallet";
        }else if($wallet_symbol=="ETC"){
            $endpoint="ethereumclassic/wallet";
        }else if($is_erc20==1){
            $endpoint="erc20/wallet";
        }else{
            return (object)["success"=>false];
        }
        $res=self::apiCalls($endpoint,$reqParamArray,"POST",$authtoken);
        $resArr = json_decode($res->getBody());
        return $resArr;
        
    }
    
	public static function apiCalls($endpoint,$reqParamArray,$method="POST",$api_key=""){

        $client = new Client([
            'headers' => ['Content-Type' => 'application/json','API-KEY'=>$api_key],

        ]);
        
        $params[] = $reqParamArray;
        if($method=='GET'){
            $response = $client->request($method,config('constants.NODE_API_URL').$endpoint);
        }else{
            $response = $client->request($method,config('constants.NODE_API_URL').$endpoint, 
                ['json' => $reqParamArray] );
        }
        
        return $response;
    }
	
}
