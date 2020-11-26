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
    <!-- START HEADER/BANNER -->
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
                            src="<?php echo url('/');?>/img/brexily-email-logo.png" width="270" height="100"
                            alt="logo">
                        </td>
                      </tr>
                      <tr>
                        <td height="30"></td>
                      </tr>
                      <!-- <tr>
                        <td align="center"
                          style="font-family: 'Montserrat', sans-serif; font-size:18px; color:#000; line-height:20px; font-weight: bold; letter-spacing: 1px;">
                            ADMIN DASHBOARD
                          </span>
                        </td>
                      </tr> -->
                      <tr>
                        <td height="20"></td>
                      </tr>
                      <tr>
                        <td align="center"
                          style="font-family: 'Montserrat', sans-serif; font-size:18px; color:#000; line-height:20px; font-weight: bold; letter-spacing: 1px;">
                            Recover Your Password
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td height="60"></td>
                      </tr>
                      <tr>
                        <td align="left"
                          style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                            Dear <strong><?php echo $user["fullname"]; ?></strong>,
                        </td>
                      </tr>
                      <tr>
                        <td height="15"></td>
                      </tr>

                      <tr align="left"
                      style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                        <td>
                        We’ve received a request to reset your password.  
                        </td>
                      </tr>
                      <tr>
                        <td height="15"></td>
                      </tr>

                      <tr align="left"
                      style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:20px; font-weight: 300;">
                        <td>
                        If you didn’t make this request, just ignore this message. Otherwise, you can reset your password using this link below.  
                        </td>
                      </tr>

                      <tr>
                        <td height="150" style="text-align: center;">
                          <a href="{{ $user['url'] }}"><button style="cursor: pointer; background-color: #21D228; padding: 15px; border: none; border-radius: 5px; font-family: 'Montserrat', sans-serif; font-size:18px; color:#fff; line-height:20px; font-weight: 600;">Click here to recover password</button></a>
                        </td>
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
                        style="font-family: 'Montserrat', sans-serif; font-size:16px; color:#000; line-height:24px; font-weight: 300;">https://www.brexily.com</td>
                      </tr>

                      <tr>
                        <td height="50"></td>
                      </tr>

                      <tr>
                        <td align="center"
                        style="font-family: 'Montserrat', sans-serif; font-size:10px; color:#000; line-height:44px; font-weight: 300;"><a style="text-decoration: none;" href="">Privacy Policy</a> | <a style="text-decoration: none;" href="">Terms & Conditions</a></td>
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