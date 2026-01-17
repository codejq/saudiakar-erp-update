<? 
require"header.php";

if(($_POST[user_password] == $_POST[user_password2]) and $_POST[reqp] )
{
mysql_query("update users set user_password = '".md5(trim($_POST[user_password]))."' , reqpass='' where  reqpass='".trim($_REQUEST['reqp'])."'");
echo "<div align=center style='color:green'><h1>طھظ… ط§ط¹ط§ط¯ط© طھط¹ظٹظٹظ† ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط¨ظ†ط¬ط§ط­  </h1></div>";
exit();
}

if($_POST[user_password] != $_POST[user_password2] )
{
echo "<div align=center style='color:red'><h1>ظƒظ„ظ…طھظٹ ط§ظ„ظ…ط±ظˆط± ط§ظ„ظ…ط¯ط®ظ„ط© ط؛ظٹط± ظ…طھط·ط§ط¨ظ‚ظٹط© </h1></div>";
}



if($_REQUEST['reqp'])
{
$row = mysql_fetch_array(mysql_query("select * from users where reqpass='".trim($_REQUEST['reqp'])."' limit 1 "));
	if(!$row['reqpass'])
	{
		echo "<div align=center><h1>ظ„ط§ظٹظˆط¬ط¯  ظ…ط¹ظ„ظˆظ…ط§طھ ظ…ط³ط¬ظ„ط© ظ„ظ‡ط°ط§ط§ظ„ط¨ط±ظٹط¯   </h1></div>";
		exit();
	}
?>
<form action="?"  style="padding:15px" method="post">
<input type="hidden" name="reqp"  value="<?=trim($_REQUEST['reqp'])?>">
ط§ط³ظ… ط§ظ„ظ…ط³طھط®ط¯ظ… ظ‡ظˆ : 
<br>
<?=$row['user_name'];?>
<br>
ط§ط¯ط®ظ„ ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط§ظ„ط¬ط¯ظٹط¯ط© : 
<br>
<input type="user_password" name="user_password" >
<br>
ط§ط¹ط¯ ط§ط¯ط®ط§ظ„ ظƒظ„ظ…ظ„ط© ط§ظ„ظ…ط±ظˆط± ظ…ط±ط© ط§ط®ط±ظٹ: 
<br>
<input type="user_password" name="user_password2" >
<br>

<input type="submit" value="ط§ط¹ط§ط¯ط© طھط¹ظٹظٹظ† ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ">
</form>


<?
exit();
}


if($_REQUEST['user_email'])
{
		$row = mysql_fetch_array(mysql_query("select * from users where user_email='".trim($_REQUEST['user_email'])."' limit 1 "));

		if(!$row[userid])
		{	
			echo "<div align=center><h1>ظ„ط§ظٹظˆط¬ط¯  ظ…ط¹ظ„ظˆظ…ط§طھ ظ…ط³ط¬ظ„ط© ظ„ظ‡ط°ط§ط§ظ„ط¨ط±ظٹط¯   </h1></div>";
			exit();
		}
		
		$reqp =  rand().rand().rand().time();
		mysql_query("update users set reqpass = '".$reqp."' where userid='".$row['userid']."'");
		//*******activation mail 
		$charset='utf-8';
		$mailsendername = "ظ…ظˆظ‚ط¹ ظ…ط¹ظ„ظ…ط§طھ ط§ظ„ط³ط¹ظˆط¯ظٹط© " ;
		$mailsendername="=?$charset?B?".base64_encode($mailsendername)."?=\n";
		$mailsubject = "ظ…ط¹ظ„ظˆظ…ط§طھ ط­ط³ط§ط¨ظƒ ط¨ظ…ظˆظ‚ط¹ ظ…ط¹ظ„ظ…ط§طھ ط§ظ„ط³ط¹ظˆط¯ظٹط©   " ;
		$mailsubject="=?$charset?B?".base64_encode($mailsubject)."?=\n";
		$mailbody="<html dir=rtl><head>
					<meta http-equiv='Content-Language' content='ar-sa'>
					<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
					<body>
					ط§ظ„ط³ظ„ط§ظ… ط¹ظ„ظٹظƒظ… ظˆط±ط­ظ…ط© ط§ظ„ظ„ظ‡ ظˆط¨ط±ظƒط§طھظ‡ 
					<br>
					ط§ظ†ظ‚ط± ط¹ظ„ظ‰ ط§ظ„ط±ط§ط¨ط· ط§ظ„طھط§ظ„ظٹ ظ„ط§ط¹ط§ط¯ط© طھظپط¹ظٹظ„ ط­ط³ط§ط¨ظƒ ظپظٹ ظ…ظˆظ‚ط¹ ظ…ط¹ظ„ظ…ط§طھ ط§ظ„ط³ط¹ظˆط¯ظٹط© 
					<br>
					<a href=\"http://ksateachers.com/recoveruser_password.php?reqp=$reqp\" target='_blank'>
					http://ksateachers.com/recoveruser_password.php?reqp=$reqp</a>
					<br>
 
					<br>
					طھط­ظٹط§طھظٹ 
					<br>
 

				";	
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
		$headers .= 'From: '.$mailsendername.' <noreplay@bentvip.com>' . "\r\n" .
					'Reply-To: '.$userinfo['userid'] . "\r\n" .
					'X-Mailer: PHP/' . phpversion();

		mail(trim($_REQUEST['user_email']), $mailsubject , $mailbody, $headers);	
		echo "<div align=center><h1>طھظ… ط§ط±ط³ط§ظ„ ط¨ظٹط§ظ†ط§طھ ط§ظ„طھظپط¹ظٹظ„ ط§ظ„ظ‰ ط¨ط±ظٹط¯ظƒ ط§ظ„ط§ظ„ظƒطھط±ظˆظ†ظٹ  </h1></div>";
		exit();
		//************************
		
		
		
		
}
?>

<form action="?" style="padding:15px" method="get">
ط§ط¯ط®ظ„ ط¨ط±ظٹط¯ظƒ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ : 
<input type="text"  name="user_email">
<input type="submit" value="ط§ط³طھط¹ط§ط¯ط© ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± " >
</form>


