<script type="text/javascript">
<!--
function noSpace(obj) {
   //  obj.value = obj.value.replace (/\s/g, "");
}
//-->
</script>


<div align="center">

	<form action="?id=<?=$_REQUEST['id'];?>" method="post" style="text-align: center">
	<font color="red"><h2><?=$error_str;?></h2></font>
	<input type="hidden" name="register" value="1">
	<table border="0" width="100%">
	<tr><td width="120">اسم المستخدم </td><td><input type="text" name="user_name" value="<?=$_POST['user_name'];?>"  onkeyup="noSpace(this)"></td></tr>
	<tr><td width="120">كلمة المرور  </td><td><input type="user_password" name="user_password"  value="<?=$_POST['user_password'];?>"  onkeyup="noSpace(this)" ></td></tr>
	<tr><td width="120"> أعد كلمة المرور  </td><td><input type="user_password" name="user_password2"  value="<?=$_POST['user_password'];?>"  onkeyup="noSpace(this)" ></td></tr>
	<tr><td width="120">البريد الإلكتروني </td><td><input type="text" name="user_email"   value="<?=$_POST['user_email'];?>"> <br>استخدم بريد صحيح سيتم ارسال كود التفعيل اليه</td></tr>
	<tr><td align="center" colspan="2"><input type="submit" value="تسجيل " ></td></tr>
	</table>
	
	</form>

</div>



