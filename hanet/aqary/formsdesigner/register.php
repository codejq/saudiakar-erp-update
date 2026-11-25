<? 

require"header.php";

if($_REQUEST['qid'])
{
mysql_query("update users set uniqid='' where uniqid='".trim($_REQUEST['qid'])."'");
echo "<div align=center><h1>تم تفعيل حسابك بنجاح </h1>
<a href='index.php'>العودة للرئيسية </a>

</div>";

exit();
}



if($_POST['register'])
{
	$_POST['user_name']	=	addslashes(strip_tags($_POST['user_name']));
	$_POST['user_password']	=	addslashes(strip_tags($_POST['user_password']));
	$_POST['user_password2']	=	addslashes(strip_tags($_POST['user_password2']));
	$_POST['user_email']		=	addslashes(strip_tags($_POST['user_email']));
	$_POST['user_realname']	=	addslashes(strip_tags($_POST['user_name']));
	/*$_POST['user_name']	= 	str_replace(" ","",$_POST['user_name']);
	$_POST['user_password']	= 	str_replace(" ","",$_POST['user_password']);
	$_POST['user_password2']	= 	str_replace(" ","",$_POST['user_password2']);*/

	$sql="select count(userid) as dd from users where user_name='".trim($_POST['user_name'])."'";
	$row = mysql_fetch_array(mysql_query($sql));
	if($row['dd'] > 0 ) 
	{
		$error_str = " الرجاء اختيار اسم مستخدم آخر حيث ان اسم المستخدم موجود مسبقا ";
		require("./templates/register_form_templet.php");
		exit();
	}
	
	$sql="select count(userid) as dd from users where user_email='".trim($_POST['user_email'])."'";
	$row = mysql_fetch_array(mysql_query($sql));
	if($row['dd'] > 0 ) 
	{
		$error_str = " البريد مستخدم من قبل انقر هنا لارسال اسم
	<a href='recoveruser_password.php?user_email=".trim($_POST['user_email'])."'>	المستخدم وكلمة المرور لبريدك </a>	";
		require("./templates/register_form_templet.php");
		exit();
	}
	
	
	
	
	$sql="select count(userid) as dd from users where user_email='".trim($_POST['user_email'])."'";
	$row = mysql_fetch_array(mysql_query($sql));
	if($row['dd'] > 0 ) 
	{
		$error_str = " البريد المدخل مسجل مسبقا يمكنك   استعادة كلمة المرور من هنا <a href='recoveruser_password.php'>استعادة كلمة المرور </a> ";
		require("./templates/register_form_templet.php");
		exit();
	}
	
	if($_POST['user_name'] and $_POST['user_password'] and ($_POST['user_password'] == $_POST['user_password2'])) 
	{
		$uqid = rand().rand().rand().time();
		$sql="insert into users set user_name='".$_POST['user_name']."' , user_password = '" . md5($_POST['user_password']) . "' 
			   ,user_email = '" . $_POST['user_email'] .  "' , user_realname = '" . $_POST['user_realname'] . "' , uniqid = '".$uqid."' ";
		mysql_query($sql); 
		
		//*******activation mail 
		$charset='utf-8';
		$mailsendername = "موقع معلمات السعودية " ;
		$mailsendername="=?$charset?B?".base64_encode($mailsendername)."?=\n";
		$mailsubject = "تفعيل حسابك في موقع المعلمات   " ;
		$mailsubject="=?$charset?B?".base64_encode($mailsubject)."?=\n";
		$mailbody="<html dir=rtl><head>
					<meta http-equiv='Content-Language' content='ar-sa'>
					<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
					<body>
					السلام عليكم ورحمة الله وبركاته 
					<br>
					انقر على الرابط التالي لتفعيل حسابك في موقع المعلمات 
					<br>
					<a href=\"http://ksateachers.com/register.php?qid=$uqid\" target='_blank'>
					http://ksateachers.com/register.php?qid=$uqid</a>
					<br>
 
					<br>
					تحياتي 
					<br>
					$userinfo[user_realname]

				";	
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
		$headers .= 'From: '.$mailsendername.' <noreplay@bentvip.com>' . "\r\n" .
					'Reply-To: '.$userinfo['userid'] . "\r\n" .
					'X-Mailer: PHP/' . phpversion();

		mail(trim($_POST['user_email']), $mailsubject , $mailbody, $headers);	
		
		//************************
		
		
		
		echo "<script>location.replace(window.location.href + '&thankyou=1&user_name=".$_POST['user_name']."&user_password=".$_POST['user_password']."');</script>";
		
		require("./templates/register_form_templet.php");
		exit();
	}
	
	if($_POST['user_password'] != $_POST['user_password2'])
	{
		$error_str = "  كلمة المرور المدخلة مختلفة عن اعادة كلمة المرور ";
	}
 

}

if($_REQUEST['thankyou'])
{
	$thanksmessage = "شكرا لك تم تسجيلك بنجاح  <br> تم ارسال بريد التفعيل الي بريدك قم باتباع التعليمات لاستكمال التفعيل  ";
	require("./templates/thankyou_templet.php");
	exit();
}



?>



<?php 
if(!$logedin)
{
	require("./templates/register_form_templet.php");
}
else
{
	$thanksmessage = "انت مسجل بالموقع بالفعل   ";
	require("./templates/thankyou_templet.php");
}


include("footer.php");
?>




