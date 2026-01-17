<? 

require"header.php";

if($_REQUEST['qid'])
{
mysql_query("update users set uniqid='' where uniqid='".trim($_REQUEST['qid'])."'");
echo "<div align=center><h1>طھظ… طھظپط¹ظٹظ„ ط­ط³ط§ط¨ظƒ ط¨ظ†ط¬ط§ط­ </h1>
<a href='index.php'>ط§ظ„ط¹ظˆط¯ط© ظ„ظ„ط±ط¦ظٹط³ظٹط© </a>

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
		$error_str = " ط§ظ„ط±ط¬ط§ط، ط§ط®طھظٹط§ط± ط§ط³ظ… ظ…ط³طھط®ط¯ظ… ط¢ط®ط± ط­ظٹط« ط§ظ† ط§ط³ظ… ط§ظ„ظ…ط³طھط®ط¯ظ… ظ…ظˆط¬ظˆط¯ ظ…ط³ط¨ظ‚ط§ ";
		require("./templates/register_form_templet.php");
		exit();
	}
	
	$sql="select count(userid) as dd from users where user_email='".trim($_POST['user_email'])."'";
	$row = mysql_fetch_array(mysql_query($sql));
	if($row['dd'] > 0 ) 
	{
		$error_str = " ط§ظ„ط¨ط±ظٹط¯ ظ…ط³طھط®ط¯ظ… ظ…ظ† ظ‚ط¨ظ„ ط§ظ†ظ‚ط± ظ‡ظ†ط§ ظ„ط§ط±ط³ط§ظ„ ط§ط³ظ…
	<a href='recoveruser_password.php?user_email=".trim($_POST['user_email'])."'>	ط§ظ„ظ…ط³طھط®ط¯ظ… ظˆظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ظ„ط¨ط±ظٹط¯ظƒ </a>	";
		require("./templates/register_form_templet.php");
		exit();
	}
	
	
	
	
	$sql="select count(userid) as dd from users where user_email='".trim($_POST['user_email'])."'";
	$row = mysql_fetch_array(mysql_query($sql));
	if($row['dd'] > 0 ) 
	{
		$error_str = " ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ظ…ط¯ط®ظ„ ظ…ط³ط¬ظ„ ظ…ط³ط¨ظ‚ط§ ظٹظ…ظƒظ†ظƒ   ط§ط³طھط¹ط§ط¯ط© ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ظ…ظ† ظ‡ظ†ط§ <a href='recoveruser_password.php'>ط§ط³طھط¹ط§ط¯ط© ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± </a> ";
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
		$mailsendername = "ظ…ظˆظ‚ط¹ ظ…ط¹ظ„ظ…ط§طھ ط§ظ„ط³ط¹ظˆط¯ظٹط© " ;
		$mailsendername="=?$charset?B?".base64_encode($mailsendername)."?=\n";
		$mailsubject = "طھظپط¹ظٹظ„ ط­ط³ط§ط¨ظƒ ظپظٹ ظ…ظˆظ‚ط¹ ط§ظ„ظ…ط¹ظ„ظ…ط§طھ   " ;
		$mailsubject="=?$charset?B?".base64_encode($mailsubject)."?=\n";
		$mailbody="<html dir=rtl><head>
					<meta http-equiv='Content-Language' content='ar-sa'>
					<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
					<body>
					ط§ظ„ط³ظ„ط§ظ… ط¹ظ„ظٹظƒظ… ظˆط±ط­ظ…ط© ط§ظ„ظ„ظ‡ ظˆط¨ط±ظƒط§طھظ‡ 
					<br>
					ط§ظ†ظ‚ط± ط¹ظ„ظ‰ ط§ظ„ط±ط§ط¨ط· ط§ظ„طھط§ظ„ظٹ ظ„طھظپط¹ظٹظ„ ط­ط³ط§ط¨ظƒ ظپظٹ ظ…ظˆظ‚ط¹ ط§ظ„ظ…ط¹ظ„ظ…ط§طھ 
					<br>
					<a href=\"http://ksateachers.com/register.php?qid=$uqid\" target='_blank'>
					http://ksateachers.com/register.php?qid=$uqid</a>
					<br>
 
					<br>
					طھط­ظٹط§طھظٹ 
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
		$error_str = "  ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط§ظ„ظ…ط¯ط®ظ„ط© ظ…ط®طھظ„ظپط© ط¹ظ† ط§ط¹ط§ط¯ط© ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ";
	}
 

}

if($_REQUEST['thankyou'])
{
	$thanksmessage = "ط´ظƒط±ط§ ظ„ظƒ طھظ… طھط³ط¬ظٹظ„ظƒ ط¨ظ†ط¬ط§ط­  <br> طھظ… ط§ط±ط³ط§ظ„ ط¨ط±ظٹط¯ ط§ظ„طھظپط¹ظٹظ„ ط§ظ„ظٹ ط¨ط±ظٹط¯ظƒ ظ‚ظ… ط¨ط§طھط¨ط§ط¹ ط§ظ„طھط¹ظ„ظٹظ…ط§طھ ظ„ط§ط³طھظƒظ…ط§ظ„ ط§ظ„طھظپط¹ظٹظ„  ";
	require("t./templates/hankyou_templet.php");
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
	$thanksmessage = "ط§ظ†طھ ظ…ط³ط¬ظ„ ط¨ط§ظ„ظ…ظˆظ‚ط¹ ط¨ط§ظ„ظپط¹ظ„   ";
	require("./templates/thankyou_templet.php");
}


include("footer.php");
?>




