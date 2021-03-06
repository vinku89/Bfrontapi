<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="shortcut icon" type="image/x-icon" href="images/favicon.ico" />
  <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png" />
  <!-- Fonts -->
  <link
    href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,700,700i,800,800i,900,900i|Roboto:400,500,700,900&display=swap"
    rel="stylesheet">
  <!-- font-family: 'Montserrat', sans-serif;
font-family: 'Roboto', sans-serif; -->
  <title>Brexily</title>
</head>
<body style="overflow-x: hidden;">
  <table width="100%" border="0" align="center" cellpadding="0" cellspacing="0">
    <!-- START HEADER/BANNER changes-->
    <tbody>
      <tr>
        <td align="center">
          <table class="col-600" width="600" border="0" align="center" cellpadding="0" cellspacing="0">
            <tbody>
              <tr>
                <td align="center" valign="top">
                  <table class="col-600" width="600" height="400" border="0" align="center" cellpadding="0"
                    cellspacing="0">
                    <tbody>
                      <tr>
                        <td height="40"></td>
                      </tr>
                      <tr>
                        <td align="center" style="line-height: 0px;">
                         <img style="display:block; line-height:0px; font-size:0px; border:0px;"
                            src="<?php echo url('/');?>/img/brexily-email-logo.png" width="244" height="82"
                            alt="logo">
                        </td>
                      </tr>
                      <tr>
                        <td height="30"></td>
                      </tr>
                
                      <tr>
                        <td align="center"
                          style="font-family: 'Montserrat', sans-serif; font-size:22px; color:#000; line-height:20px; font-weight: bold; letter-spacing: 1px; height: 50px;">
                         EARN CRYPTO Successful Withdrawal
                          </span>
                        </td>
                      </tr>
                      
                      <tr>
                        <td height="60"></td>
                      </tr>
                      <tr>
                        <td align="left"
                          style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                            Dear <strong><?php echo $first_name." ".$last_name; ?></strong>,
                        </td>
                      </tr>
                      <tr>
                        <td height="30"></td>
                      </tr>

                      <tr align="left"
                      style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                        <td>
                         <strong> Your withdrawal request was successful.</strong> The details are as below:
                        </td>
                      </tr>
                      <tr>
                        <td height="20"></td>
                      </tr>
                      <tr align="left"
                      style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                        <td>
                           <table border="0" align="left" cellpadding="0" cellspacing="0">
                             <tbody>
                               <tr>
                                 <td style="padding:5px 0">Withdrawal ID</td>
                                 <td width="20" style="text-align: center;padding:5px 0">:</td>
                                 <td style="padding:5px 0"><strong><?php echo $withdraw_id ?></strong></td>
                               </tr>
                               <tr>
                                <td style="padding:5px 0">Withdrawal Date</td>
                                <td width="20" style="text-align: center;padding:5px 0">:</td>
                                <td style="padding:5px 0"><strong><?php echo date("d-m-Y"); ?></strong></td>
                              </tr>
                              <tr>
                                <td style="padding:5px 0">Earning Payouts</td>
                                <td width="20" style="text-align: center;padding:5px 0">:</td>
                                <td style="padding:5px 0"><strong>$<?php echo number_format($payout_amount_usd,8).' | '.number_format($payout_amount_crypto,8).' '.$payout_crypto_type ?></strong></td>
                              </tr>
                              <tr>
                                <td style="padding:5px 0">Package Withdrawn</td>
                                <td width="20" style="text-align: center;padding:5px 0">:</td>
                                <td style="padding:5px 0"><strong>$<?php echo number_format($package_amount_usd,2); ?> USD</strong></td>
                              </tr>
                              <tr>
                                <td style="padding:5px 0">Amount Withdrawn</td>
                                <td width="20" style="text-align: center;padding:5px 0">:</td>
                                <td style="padding:5px 0"><strong><?php echo number_format($package_amount_crypto,8).' '.$package_crypto_type; ?></strong></td>
                              </tr>
                              <?php if(@$penalty_amount_crypto > 0) { ?> 
                              <tr>
                                <td style="padding:5px 0">Penalty Amount</td>
                                <td width="20" style="text-align: center;padding:5px 0">:</td>
                                <td style="padding:5px 0"><strong><?php echo number_format(@$penalty_amount_crypto,8).' '.$package_crypto_type; ?></strong></td>
                              </tr>
                            <?php }?>
                              
                              </tr>
                             </tbody>
                           </table>
                        </td>
                      </tr>
                      <tr>
                        <td height="30"></td>
                      </tr>
                       <?php if(@$penalty_amount_crypto > 0 && !empty(@$penalty_amount_crypto)) { ?> 
                      <tr>
                              <td style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                                The total amount will be credited to your wallet address within 24 hours. Please note that there was a penalty fee charged to your account as withdrawal was made before mature period. For more information, Please view the Terms & Condition
                              </td>
                            </tr>
                            <?php }
                             else {?>
                              <tr>
                              <td style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                                The total amount will be credited to your wallet address within 24 hours. To continue earning <?php echo $payout_crypto_type ?>, go to your Brexily account to re-subscribe.
                              </td>
                            </tr>
                            <?php } ?>
                      </tr>
                      <tr>
                        <td height="30">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                          If you have not made any withdrawal request, please contact our support team to clarify this issue at support@brexily.com
                        </td>
                      </tr>
                
                      <tr>
                        <td height="40"></td>
                      </tr>
                      <tr>
                        <td align="left"
                        style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:24px; font-weight: 300;">Regards,</td>
                          
                          
                      </tr>
                      <tr>
                        <td align="left"
                        style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:24px; font-weight: 300;">The BREXILY Team.</td>
                      </tr>
                      <tr>
                        <td align="left"
                        style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:24px; font-weight: 300;">
                        <a href="https://brexily.com/" target="_blank" style=" color:#000; text-decoration: none;">https://www.brexily.com</a>
                      </td>
                      </tr>

                      <tr>
                        <td height="50"></td>
                      </tr>

                      <tr>
                        <td align="center"
                        style="font-family: 'Montserrat', sans-serif; font-size:10px; color:#000; line-height:44px; font-weight: 300;"><a style="text-decoration: none; color:#000;" href="https://brexily.com/privacypolicy/" target="_blank">Privacy Policy</a> | <a style="text-decoration: none; color:#000;" href="https://brexily.com/termsofuse/" target="_blank">Terms & Conditions</a></td>
                      </tr>

                      <tr>
                        <td colspan="2">
                          <table border="0" align="center" cellpadding="0" cellspacing="0">
                            <tbody>
                              <tr>
                                <td style="width: 50px; text-align: center;"><a href="https://everus.org/" target="_blank"><img src="<?php echo url('/');?>/img/everus.png" alt=""></a></td>
                                <td style="width: 50px; text-align: center;"><a href="https://brexily.com/" target="_blank"><img src="<?php echo url('/');?>/img/brexily.png" alt=""></a></td>
                                <td style="width: 50px; text-align: center;"><a href="https://trullion.org/" target="_blank"><img src="<?php echo url('/');?>/img/trullion.png" alt=""></a></td>
                                <td style="width: 50px; text-align: center;"><a href="https://everuspay.com/" target="_blank"><img src="<?php echo url('/');?>/img/everuspay.png" alt=""></a></td>
                                <td style="width: 50px; text-align: center;"><a href="https://everusfinance.com/" target="_blank"><img src="<?php echo url('/');?>/img/everus-finance.png" alt=""></td>
                                <td style="width: 50px; text-align: center;"> <a href="http://www.everusremit.com/" target="_blank"><img src="<?php echo url('/');?>/img/everus-remit.png" alt=""></a></td>
                                
                              </tr>
                              
                            </tbody>
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td align="center" style="font-family: 'Montserrat', sans-serif; font-size:12px; color:#000;font-weight: 300;">
                          <a style="text-decoration: none; color:#000;" href="https://everus.org/" target="_blank">www.everusworld.com</a> | 
                          <a style="text-decoration: none; color:#000;" href="https://everus.org/" target="_blank">www.everus.org</a> |
                          <a style="text-decoration: none; color:#000;" href="https://brexily.com" target="_blank">www.brexily.com</a>
                        </td>
                      </tr>
                      <tr><td style="height:30px">&nbsp;</td></tr>

                      <tr align="center"
                      style="font-family: 'Montserrat', sans-serif; font-size:10px; color:#000; line-height:12px; font-weight: 300;">
                        <td>Copyright © 2020 Everus Technologies.<br> All trademarks and copyrights belong to their respective owners.</td>
                      </tr>

                      <tr>
                        <td></td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </tbody>
  </table>
</body>
</html>