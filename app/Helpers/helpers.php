<?php
if (! function_exists('exp2dec')) {
    function exp2dec($number) {
        //echo $number." ";
        if(preg_match('/(.*)E-(.*)/', str_replace(".", "", $number), $matches)){
            $num = "0.";
            //print_r($matches);
            $dec=$matches[2];
            while ($matches[2] > 1) {
                $num .= "0";
                $matches[2]--;
            }
            //return rtrim(number_format($num . $matches[1],8), "0");
            if($dec==8){
                return $num . $matches[1][0];
            }else{
                return $num . $matches[1];
            }
            
        }else{
            //return rtrim(number_format($number,8),"0");
            return $number;
        }
       
    }
}
if (! function_exists('exp2decimal')) {
    function exp2decimal($number) {
        //echo $number." ";
        if(preg_match('/(.*)E-(.*)/', str_replace(".", "", $number), $matches)){
            $num = "";
            //print_r($matches);
            $dec=$matches[2];
            while ($matches[2] > 1) {
                $num .= "0";
                $matches[2]--;
            }
            //return rtrim(number_format($num . $matches[1],8), "0");
            if($dec==8){
                return $num . $matches[1][0];
            }else{
                return $num . $matches[1];
            }
            
        }else{
            //return rtrim(number_format($number,8),"0");
            return $number;
        }
       
    }
}
if (! function_exists('number_format_eight_dec')) { 
    function number_format_eight_dec($amount){
        $str=substr($amount, 0, 1);
        $f="";
        if($str=='-'){
            $f="-";
            $amount = ltrim($amount, '-');
        }
        $amount=exp2dec($amount);
        $t=explode(".",$amount);
        if($t[0]==="-0"){
            $beforeDecimal=$t[0];
        }else{
            $beforeDecimal=number_format(floatval($t[0]));
        }
        if(!empty($t[1])){
            $afterDecimal=substr($t[1],0,8);
            if(rtrim($afterDecimal,0)!=""){
                 $afterDecimal = rtrim($afterDecimal,0);   
            }else{
                $afterDecimal = "00";
            }
            $formatAmt=$beforeDecimal.".".$afterDecimal;
        }else{
            $formatAmt=$beforeDecimal;
        }
        if (strpos($formatAmt, '.') !== false) {
            $amttemp=explode(".",$formatAmt);
            if(strlen($amttemp[1])==1){
                $formatAmt = $formatAmt."0";
            }else{
                
            }
        }else{
            
            $formatAmt = $formatAmt.".00";
        }

        return $f.$formatAmt;
    }
}
if (! function_exists('number_format_four_dec')) { 
    function number_format_four_dec($amount){
        $str=substr($amount, 0, 1);
        $f="";
        if($str=='-'){
            $f="-";
            $amount = ltrim($amount, '-');
        }
        $amount=exp2dec($amount);
        $t=explode(".",$amount);
        if($t[0]==="-0"){
            $beforeDecimal=$t[0];
        }else{
            $beforeDecimal=number_format(floatval($t[0]));
        }
        if(!empty($t[1])){
            $afterDecimal=substr($t[1],0,4);
            if(rtrim($afterDecimal,0)!=""){
                 $afterDecimal = rtrim($afterDecimal,0);   
            }else{
                $afterDecimal = "00";
            }
            $formatAmt=$beforeDecimal.".".$afterDecimal;
        }else{
            $formatAmt=$beforeDecimal;
        }
        if (strpos($formatAmt, '.') !== false) {
            $amttemp=explode(".",$formatAmt);
            if(strlen($amttemp[1])==1){
                $formatAmt = $formatAmt."0";
            }else{
                
            }
        }else{
            
            $formatAmt = $formatAmt.".00";
        }

        return $f.$formatAmt;
    }
}
if (! function_exists('number_format_two_dec')) { 
    function number_format_two_dec($amount){
        $str=substr($amount, 0, 1);
        $f="";
        if($str=='-'){
            $f="-";
            $amount = ltrim($amount, '-');
        }
        $amount=exp2dec($amount);
        $t=explode(".",$amount);
        if($t[0]==="-0"){
            $beforeDecimal=$t[0];
        }else{
            $beforeDecimal=number_format(floatval($t[0]));
        }
        if(!empty($t[1])){
            $afterDecimal=substr($t[1],0,2);
            if(rtrim($afterDecimal,0)!=""){
                 $afterDecimal = rtrim($afterDecimal,0);   
            }else{
                $afterDecimal = "00";
            }
            $formatAmt=$beforeDecimal.".".$afterDecimal;
        }else{
            $formatAmt=$beforeDecimal;
        }
        if (strpos($formatAmt, '.') !== false) {
            $amttemp=explode(".",$formatAmt);
            if(strlen($amttemp[1])==1){
                $formatAmt = $formatAmt."0";
            }else{
                
            }
        }else{
            
            $formatAmt = $formatAmt.".00";
        }

        return $f.$formatAmt;
    }
}
if(! function_exists('randomstring')){
    function randomstring($len)
    {
        $string = "";
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        for($i=0;$i<$len;$i++)
        $string.=substr($chars,rand(0,strlen($chars)),1);
        return $string;
    }
}

if (! function_exists('number_format_eight_dec_currency')) { 
    function number_format_six_dec_currency($amount){
        $str=substr($amount, 0, 1);
        $f="";
        if($str=='-'){
            $f="-";
            $amount = ltrim($amount, '-');
        }
        $amount=exp2dec($amount);
        if($amount>1){
            $formatAmt= number_format($amount,2);
            return $f.$formatAmt;
        }
        $t=explode(".",$amount);
        if($t[0]==="-0"){
            $beforeDecimal=$t[0];
        }else{
            $beforeDecimal=number_format(floatval($t[0]));
        }
        if(!empty($t[1])){
            $afterDecimal=substr($t[1],0,6);
            if(rtrim($afterDecimal,0)!=""){
                 $afterDecimal = rtrim($afterDecimal,0);   
            }else{
                $afterDecimal = "00";
            }
            $formatAmt=$beforeDecimal.".".$afterDecimal;
        }else{
            $formatAmt=$beforeDecimal;
        }
        if (strpos($formatAmt, '.') !== false) {
            $amttemp=explode(".",$formatAmt);
            if(strlen($amttemp[1])==1){
                $formatAmt = $formatAmt."0";
            }else{
                
            }
        }else{
            
            $formatAmt = $formatAmt.".00";
        }

        return $f.$formatAmt;
    }
}
if(! function_exists('walletMbal_compare')){
    function walletMbal_compare($a, $b)
    {   
        if ($a["m_balance"]==$b["m_balance"]) return 0;
            return ($a["m_balance"]>$b["m_balance"])?-1:1;
        //return strcmp($a["m_balance"], $b["m_balance"]);
    }   
}
if(! function_exists('sortByTime')){
    function sortByTime($a, $b){
      $a = strtotime($a['created_at']);
      $b = strtotime($b['created_at']);
      return $a - $b;
    }
}
if(! function_exists('bidprice_sort')){
    function bidprice_sort($a, $b){
      if ($a["r_bid_price"]==$b["r_bid_price"]) return 0;
            return ($a["r_bid_price"]>$b["r_bid_price"])?-1:1;
    }
}
if (! function_exists('pwdDecrypt')) {  
    function pwdDecrypt($email,$pwd){
        $arr = explode('@',$email);
        $enc_mail = $arr[0];
        //base64_encode($enc_mail.$pwd.'8965424321'); //encryption
        $enc = base64_decode($pwd);

        if (strstr($enc, $enc_mail) && strstr($enc, '8965424321')) {
            $arr = explode($enc_mail,$enc);
            $arr1 = explode('8965424321',$arr[1]);
            return $decrypted_PIN = $arr1[0];
        }else{
            return "wrong_pwd";
        }
    }
}
if (! function_exists('mathematical_zeros_after_dot')) {  
    function mathematical_zeros_after_dot($float) {
        $float = abs($float);  // remove any signs
        $float -= (int)$float;  // remove whole numbers from float
        if ($float == 0) {
            return "Rendered as 0";
        }
        $max = 20;
        for ($x = 0; $x < $max; ++$x) {
            $float *= 10;
            if ($float >= 1) {
                return $x;
            }
        }
        return "$max {exceeded}";
        
    }

}
if (! function_exists('pwdDecrypt')) {  
    function pwdDecrypt($email,$pwd){
        $arr = explode('@',$email);
        $enc_mail = $arr[0];
        //base64_encode($enc_mail.$pwd.'8965424321'); //encryption
        $enc = base64_decode($pwd);

        if (strstr($enc, $enc_mail) && strstr($enc, '8965424321')) {
            $arr = explode($enc_mail,$enc);
            $arr1 = explode('8965424321',$arr[1]);
            return $decrypted_PIN = $arr1[0];
        }else{
            return "wrong_pwd";
        }
    }
}