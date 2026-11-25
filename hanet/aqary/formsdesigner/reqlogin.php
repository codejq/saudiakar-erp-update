<?
$logedin = "";
$_REQUEST['logedin'] = "";

if(isset($_COOKIE['ID_my_site']))
{
 	$user  = addslashes(strip_tags($_COOKIE['ID_my_site'])); 
 	$pass  = addslashes(strip_tags($_COOKIE['Key_my_site']));
	
	$userinfo = mysql_fetch_array(mysql_query("select * from users where user_name = '".$user."'  and user_password = '".md5(trim($pass))."' limit 1 "));
	if($userinfo['user_id'] > 0  and  $user !=""     and !$userinfo['uniqid'])
	{
		$logedin = 1 ; 
	}

}

if(isset($_REQUEST['user_name']) and isset($_REQUEST['user_password']) and  $_REQUEST['user_name'] !="" and $logedin != 1 )
{
	$user	= addslashes(strip_tags($_REQUEST['user_name']));
	$pass	= addslashes(strip_tags($_REQUEST['user_password']));
	
	$userinfo = mysql_fetch_array(mysql_query("select * from users where user_name = '".$user."'   and user_password = '".md5(trim($pass))."'  limit 1 "));

	if($userinfo['user_id'] > 0 and !$userinfo['uniqid'] )
	{
		$logedin = 1 ; 
		$hour = time() + 500000; 
		setcookie(ID_my_site, $user, $hour); 
		setcookie(Key_my_site,$pass, $hour);	

	}
	else
	{
		$logedin = "";
		$error_login = "  اسم المستخدم او كلمة المرور خاطئة 
		<a href='recoveruser_password.php'>انقر هنا لاعادة تعيين كلمة المرور </a>
		" ;
		$past = time() - 100;
		setcookie(ID_my_site, "", $past); 
		setcookie(Key_my_site,"", $past);
	}



}


if($userinfo['uniqid'])
{
	$error_login = "  لم تقم بتفعيل حسابك عبر البريد الإلكتروني 
	<a href='resendactivation.php?user_email=".$userinfo['user_email']."'>انقر هنا لاعادة ارسال  كود التفعيل الي بريدك الإلكتروني </a>
	" ;
	$past = time() - 100;
	setcookie(ID_my_site, "", $past); 
	setcookie(Key_my_site,"", $past);
}

if(isset($_REQUEST['logout']))
{
	$logedin = "";
	$past = time() - 100;
	setcookie(ID_my_site, "", $past); 
	setcookie(Key_my_site,"", $past);
		
}

