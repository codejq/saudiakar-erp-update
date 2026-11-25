<? 
require"header.php";

if(($_POST['user_password'] == $_POST['user_password2']) and $_POST['reqp'] )
{
mysql_query("update users set user_password = '".md5(trim($_POST['user_password']))."' , reqpass='' where  reqpass='".trim($_REQUEST['reqp'])."'");
echo "<div align=center style='color:green'><h1>تم اعادة تعيين كلمة المرور بنجاح  </h1></div>";
exit();
}

if($_POST['user_password'] != $_POST['user_password2'] )
{
echo "<div align=center style='color:red'><h1>كلمتي المرور المدخلة غير متطابقية </h1></div>";
}



if($_REQUEST['reqp'])
{
$row = mysql_fetch_array(mysql_query("select * from users where reqpass='".trim($_REQUEST['reqp'])."' limit 1 "));
	if(!$row['reqpass'])
	{
		echo "<div align=center><h1>لايوجد  معلومات مسجلة لهذاالبريد   </h1></div>";
		exit();
	}
?>
<form action="?"  style="padding:15px" method="post">
<input type="hidden" name="reqp"  value="<?=trim($_REQUEST['reqp'])?>">
اسم المستخدم هو : 
<br>
<?=$row['user_name'];?>
<br>
ادخل كلمة المرور الجديدة : 
<br>
<input type="user_password" name="user_password" >
<br>
اعد ادخال كلملة المرور مرة اخري: 
<br>
<input type="user_password" name="user_password2" >
<br>

<input type="submit" value="اعادة تعيين كلمة المرور ">
</form>


<?
exit();
}


if($_REQUEST['user_email'])
{
		$row = mysql_fetch_array(mysql_query("select * from users where user_email='".trim($_REQUEST['user_email'])."' limit 1 "));

		if(!$row['userid'])
		{	
			echo "<div align=center><h1>لايوجد  معلومات مسجلة لهذاالبريد   </h1></div>";
			exit();
		}
		
		$reqp =  rand().rand().rand().time();
		mysql_query("update users set reqpass = '".$reqp."' where userid='".$row['userid']."'");
		//*******activation mail 
		$charset='utf-8';
		$mailsendername = "موقع معلمات السعودية " ;
		$mailsendername="=?$charset?B?".base64_encode($mailsendername)."?=\n";
		$mailsubject = "معلومات حسابك بموقع معلمات السعودية   " ;
		$mailsubject="=?$charset?B?".base64_encode($mailsubject)."?=\n";
		$mailbody="<html dir=rtl><head>
					<meta http-equiv='Content-Language' content='ar-sa'>
					<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
					<body>
					السلام عليكم ورحمة الله وبركاته 
					<br>
					انقر على الرابط التالي لاعادة تفعيل حسابك في موقع معلمات السعودية 
					<br>
					<a href=\"http://ksateachers.com/recoveruser_password.php?reqp=$reqp\" target='_blank'>
					http://ksateachers.com/recoveruser_password.php?reqp=$reqp</a>
					<br>
 
					<br>
					تحياتي 
					<br>
 

				";	
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
		$headers .= 'From: '.$mailsendername.' <noreplay@bentvip.com>' . "\r\n" .
					'Reply-To: '.$userinfo['userid'] . "\r\n" .
					'X-Mailer: PHP/' . phpversion();

		mail(trim($_REQUEST['user_email']), $mailsubject , $mailbody, $headers);	
		echo "<div align=center><h1>تم ارسال بيانات التفعيل الى بريدك الالكتروني  </h1></div>";
		exit();
		//************************
		
		
		
		
}
?>

<form action="?" style="padding:15px" method="get">
ادخل بريدك الإلكتروني : 
<input type="text"  name="user_email">
<input type="submit" value="استعادة كلمة المرور " >
</form>


