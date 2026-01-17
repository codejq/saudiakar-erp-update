<?php
require("../header.hnt");

// Initialize variables
$formtype = isset($_REQUEST['formtype']) ? $_REQUEST['formtype'] : '';
$deleteFile = isset($_REQUEST['deleteFile']) ? $_REQUEST['deleteFile'] : '';
$deleteForm = isset($_REQUEST['deleteForm']) ? intval($_REQUEST['deleteForm']) : 0;

$dir = './formsimages/thumb/'.$formtype.'/';

if($deleteFile){
	if(strpos(" ".$deleteFile, "user_")){
		@unlink($dir.$deleteFile);
		@unlink(str_replace("thumb/","",$dir.$deleteFile));
	}
}


if(!$formtype){
	echo "<div class='col_12 center uifont red '>لم تختر اي نموذج </div>";
	exit();
}

if($deleteForm){
	mysql_query("delete from forms_data where id='".$deleteForm."' ");
}


if(isset($_FILES['contractform']['name']) && $_FILES['contractform']['name']){
	require('./simpleimage.php');
	//#######start upload #############
	if (!is_dir("./formsimages")){
		mkdir("formsimages");
	}
	if (!is_dir("./formsimages/thumb")){
		mkdir("formsimages/thumb");
	}
	if (!is_dir("./formsimages/thumb/".$formtype)){
		mkdir("formsimages/thumb/".$formtype);
	}

	if (!is_dir("./formsimages/".$formtype)){
		mkdir("formsimages/".$formtype);
	}


	$exten = strtolower(strrchr($_FILES['contractform']['name'],'.'));
	$targetpic="user_".time().rand().$exten;
	$image_type = strtolower ($_FILES['contractform']['type']);
	if($image_type!="image/pjpeg" and $image_type!="image/jpeg"
		and $image_type!="image/gif" and $image_type!="image/x-png"
		and $image_type!="image/png")
	{
		$thanksmessage ="<span color=red>خطأ: يجب ان يكون نوع الصورة jpg او gif او png   </span> ";
		$redirectname = "شاشة ادخال النموذج  ";
		$redirecturl = "formselector.php?formtype=".$formtype;
		require("./templates/thankyou_templet.php");
		exit();
	}


	if($_FILES['contractform']['size']>5200000){
		$thanksmessage ="<span color=red>خطأ: اقصى حجم مسموح به 5.2ميجا يجب تقليل حجم الصورة  </span> ";
		$redirectname = "شاشة ادخال النموذج  ";
		$redirecturl = "formselector.php?formtype=".$formtype;
		require("./templates/thankyou_templet.php");
		exit();
	}


	if(!move_uploaded_file($_FILES['contractform']['tmp_name'],"./formsimages/".$formtype."/".$targetpic))
	{
		$thanksmessage ="<span color=red>لم استطع تحميل الملف</span> ";
		$redirectname = "شاشة ادخال النموذج  ";
		$redirecturl = "formselector.php?formtype=".$formtype;
		require("./templates/thankyou_templet.php");
		exit();
	}
	else
	{
		if($maxpicsize2 < 1) {$maxpicsize2="2480";}
		$image = new SimpleImage();
		$image->load("./formsimages/".$formtype."/".$targetpic);
		$image->resize(300,400);
		$image->save("./formsimages/thumb/".$formtype."/".$targetpic);
		$thanksmessage ="<span color=green>تم إضافة النموذج بنجاح  </span> ";
		$redirectname = "مصمم النماذج  ";
		$redirecturl = "formselector.php?formtype=".$formtype;
		require("./templates/thankyou_templet.php");
		exit();
	}

}

?>

<style>
.iconcolmn{
	/*max-width:150px !important;
	min-width:150px !important;*/
	padding-right: 5px;
    padding-left: 5px;
	min-height:150px !important;
	clear:none !important;
}

</style>
<script>
$( document ).ready(function() {
	$(".btncreate").click(function(){
		window.location.href='createform.php?formtype=<?=$formtype;?>&formfilename=' + $(this).attr('imagefile');
	});

	$(".btndelete").click(function(){
		jqmsbox('<div class="uifont" style=\"direction:rtl\"> هل ترغب بالفعل في حذف هذا النموذج <br><br> <a class="no btn uifont18" href="?formtype=<?=$formtype;?>&deleteFile='
		+ $(this).attr('imagefile') + '"  >موافق</a> <a class="no btn uifont18">الغاء الامر</a></div>');
	});

	$(".btnedit").click(function(){
		window.location.href='createform.php?formid=' + $(this).attr('formid');
	});

	$(".btndeleteٍSaved").click(function(){
		jqmsbox('<div class="uifont" style=\"direction:rtl\"> هل ترغب بالفعل في حذف هذا النموذج <br><br> <a class="no btn uifont18" href="?formtype=<?=$formtype;?>&deleteForm='
		+ $(this).attr('formid') + '"  >موافق</a> <a class="no btn uifont18">الغاء الامر</a></div>');
	});


});

</script>


<div class="col_12  ">
	<div class="col_12 uifont ">
		<div class="col_6 uifont ">نماذجك الحالية:</div>
		<div class="col_6 uifont14 left "><a href="index.php" class="button uifont14 ">عودة لمصصم النماذج </a></div>
	</div>
	<?
	//createform.php?formid=3
	$sql = "select  * from  forms_data where form_type='".$formtype."' limit 100 " ;
	$rs = mysql_query($sql);
	while($row = mysql_fetch_array($rs)){
			$fileName = "formsimages/thumb/".$formtype."/" .$row['form_file_name'];
			echo "<div class='col_3 center uifont'>{$row['form_name']}<br>";
			echo "<img src='". $fileName . "' style='clear:both;margin:10px;padding:10px;border:1px solid #808080'><br> ";
			echo "<button class='uifont18 btnedit' formid='{$row['id']}' >تعديل</button>";
			echo " <button class='uifont18 btndeleteٍSaved' formid='{$row['id']}' >حذف</button>";
			echo "</div>";
		}

	?>
</div>
<hr />
<div class="col_12  ">
	<div class="col_12">
		<form action="?" class="uifont" method='post' enctype="multipart/form-data" >
			<span>إضافة نموذج جديد:</span>
			<input type="file" name="contractform">
			<input type="hidden" name="formtype" value="<?=$formtype;?>">

			<input type="submit" class="uifont" value="إضافة النموذج">
		</form>
	</div>
</div>
<hr />
<div class="col_12  ">
	<div class="col_12 uifont ">إنشاء نموذج جديد :</div>
	<?
	foreach(glob($dir."{*.jpg,*.png,*.gif}", GLOB_BRACE) as $file) {
		$fileName = str_replace($dir ,"",$file);
		echo "<div class='col_3 center'>";
		echo "<img src='". $file . "' style='margin:10px;padding:10px;border:1px solid #808080'> ";
		echo "<button class='uifont18 btncreate' imagefile='{$fileName}' >انشاء نموذج من هذا الملف</button>";
		if(strpos(" ".$fileName, "user_")){
			echo " <button class='uifont18 btndelete' imagefile='{$fileName}' >حذف</button>";
		}
		echo "</div>";
	}

	?>

</div>
<?
require("../footer.hnt");
?>
