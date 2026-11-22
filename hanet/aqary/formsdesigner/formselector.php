<?php
require("../header.hnt");

// Initialize variables. Form types are short ASCII keys or department IDs and are used in file paths.
$formtype = isset($_REQUEST['formtype']) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_REQUEST['formtype']) : '';
$deleteFile = isset($_REQUEST['deleteFile']) ? basename((string)$_REQUEST['deleteFile']) : '';
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
<?php
// ---- Presentation -------------------------------------------------------
$fsEsc = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$fsTypeNames = array(
	'sellcontract' => 'نماذج عقود البيع',
	'rentcontract' => 'نماذج عقود الإيجار',
	'recivevoucher' => 'نماذج سند القبض',
	'paymentvaoucher' => 'نماذج سند الصرف',
	'check' => 'نماذج الشيكات',
	'letters' => 'نماذج الخطابات',
);
$fsTypeName = isset($fsTypeNames[$formtype]) ? $fsTypeNames[$formtype] : 'نماذج ' . $formtype;
if (ctype_digit($formtype)) {
	$fsDep = mysql_fetch_array(mysql_query("SELECT dep_name FROM departments_name WHERE dep_id='" . intval($formtype) . "' LIMIT 1", $link));
	if (is_array($fsDep) && $fsDep['dep_name'] !== '') $fsTypeName = 'نماذج ' . $fsDep['dep_name'];
}
$fsSavedForms = array();
$fsRs = mysql_query("select * from forms_data where form_type='" . $formtype . "' limit 100");
while ($fsRs && ($fsRow = mysql_fetch_array($fsRs))) $fsSavedForms[] = $fsRow;
$fsTemplates = glob($dir . "{*.jpg,*.png,*.gif}", GLOB_BRACE) ?: array();
?>
<style>
.fs-page{max-width:1400px;margin:0 auto;padding:16px}
.fs-hero{background:linear-gradient(135deg,#0a7047 0%,#0d9488 100%);color:#fff;border-radius:14px;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;box-shadow:0 6px 18px rgba(13,148,136,.18)}
.fs-hero h1{font-size:22px;margin:0;font-weight:700}
.fs-hero small{opacity:.85}
.fs-section{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin-bottom:20px}
.fs-section-title{display:flex;align-items:center;gap:8px;font-size:17px;font-weight:700;color:#0f3d3a;margin-bottom:14px}
.fs-section-title .badge{background:#ccfbf1;color:#0f766e;font-weight:600}
.fs-card{border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;height:100%;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s;background:#fff}
.fs-card:hover{box-shadow:0 8px 22px rgba(15,23,42,.10);transform:translateY(-2px)}
.fs-thumb{background:#f8fafc;display:flex;align-items:center;justify-content:center;padding:12px;height:220px;border-bottom:1px solid #eef2f7}
.fs-thumb img{max-width:100%;max-height:100%;object-fit:contain;box-shadow:0 1px 4px rgba(0,0,0,.12);background:#fff}
.fs-card-body{padding:12px;display:flex;flex-direction:column;gap:10px;flex:1}
.fs-card-name{font-weight:700;color:#1e293b;word-break:break-word}
.fs-card-actions{display:flex;gap:6px;margin-top:auto}
.fs-card-actions .btn{flex:1}
.fs-empty{color:#64748b;text-align:center;padding:26px 10px;border:1px dashed #cbd5e1;border-radius:12px}
.fs-upload{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.fs-upload input[type=file]{max-width:420px}
</style>

<div class="fs-page" dir="rtl">
	<div class="fs-hero">
		<div>
			<h1><i class="bi bi-ui-checks-grid me-2"></i><?= $fsEsc($fsTypeName) ?></h1>
			<small>مصمم النماذج - اختر نموذجاً محفوظاً لتعديله أو أنشئ نموذجاً جديداً من صورة</small>
		</div>
		<a href="index.php" class="btn btn-light"><i class="bi bi-arrow-right me-1"></i> عودة لمصمم النماذج</a>
	</div>

	<div class="fs-section">
		<div class="fs-section-title"><i class="bi bi-collection text-success"></i> نماذجك الحالية <span class="badge rounded-pill"><?= count($fsSavedForms) ?></span></div>
		<?php if (!$fsSavedForms) { ?>
			<div class="fs-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>لا توجد نماذج محفوظة لهذا النوع بعد. أنشئ نموذجاً من إحدى الصور أدناه.</div>
		<?php } else { ?>
		<div class="row g-3">
			<?php foreach ($fsSavedForms as $fsRow) { ?>
			<div class="col-6 col-md-4 col-lg-3 col-xl-2">
				<div class="fs-card">
					<div class="fs-thumb"><img src="formsimages/thumb/<?= $fsEsc($formtype) ?>/<?= $fsEsc($fsRow['form_file_name']) ?>" alt="" loading="lazy"></div>
					<div class="fs-card-body">
						<div class="fs-card-name"><?= $fsEsc($fsRow['form_name']) ?> <small class="text-muted">#<?= intval($fsRow['id']) ?></small></div>
						<div class="fs-card-actions">
							<a class="btn btn-sm btn-success" href="createform.php?formid=<?= intval($fsRow['id']) ?>"><i class="bi bi-pencil-square"></i> تعديل</a>
							<button type="button" class="btn btn-sm btn-outline-danger fs-delete" data-url="?formtype=<?= $fsEsc(rawurlencode($formtype)) ?>&amp;deleteForm=<?= intval($fsRow['id']) ?>" data-name="<?= $fsEsc($fsRow['form_name']) ?>"><i class="bi bi-trash"></i> حذف</button>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>
		</div>
		<?php } ?>
	</div>

	<div class="fs-section">
		<div class="fs-section-title"><i class="bi bi-cloud-arrow-up text-primary"></i> إضافة صورة نموذج جديدة</div>
		<form action="?" method="post" enctype="multipart/form-data" class="fs-upload">
			<input type="file" name="contractform" class="form-control" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" required>
			<input type="hidden" name="formtype" value="<?= $fsEsc($formtype) ?>">
			<button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> إضافة النموذج</button>
			<small class="text-muted w-100">الصيغ المسموحة: JPG أو PNG أو GIF، والحد الأقصى 5.2 ميجابايت. يفضل مسح النموذج الورقي بدقة عالية.</small>
		</form>
	</div>

	<div class="fs-section">
		<div class="fs-section-title"><i class="bi bi-file-earmark-plus text-warning"></i> إنشاء نموذج جديد من صورة <span class="badge rounded-pill"><?= count($fsTemplates) ?></span></div>
		<?php if (!$fsTemplates) { ?>
			<div class="fs-empty"><i class="bi bi-image fs-3 d-block mb-2"></i>لا توجد صور نماذج. أضف صورة النموذج من القسم السابق.</div>
		<?php } else { ?>
		<div class="row g-3">
			<?php foreach ($fsTemplates as $file) {
				$fileName = str_replace($dir, "", $file);
				$fsCanDelete = strpos(" " . $fileName, "user_") !== false; ?>
			<div class="col-6 col-md-4 col-lg-3 col-xl-2">
				<div class="fs-card">
					<div class="fs-thumb"><img src="<?= $fsEsc($file) ?>" alt="" loading="lazy"></div>
					<div class="fs-card-body">
						<div class="fs-card-actions">
							<a class="btn btn-sm btn-success" href="createform.php?formtype=<?= $fsEsc(rawurlencode($formtype)) ?>&amp;formfilename=<?= $fsEsc(rawurlencode($fileName)) ?>"><i class="bi bi-plus-circle"></i> إنشاء نموذج</a>
							<?php if ($fsCanDelete) { ?>
							<button type="button" class="btn btn-sm btn-outline-danger fs-delete flex-grow-0" title="حذف الصورة" data-url="?formtype=<?= $fsEsc(rawurlencode($formtype)) ?>&amp;deleteFile=<?= $fsEsc(rawurlencode($fileName)) ?>" data-name="الصورة"><i class="bi bi-trash"></i></button>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>
		</div>
		<?php } ?>
	</div>
</div>

<script>
$(function () {
	$('.fs-delete').on('click', function () {
		var url = $(this).data('url');
		var name = $('<div>').text($(this).data('name') || '').html();
		confirm('هل ترغب بالفعل في حذف ' + name + '؟', 'تأكيد الحذف', function () {
			window.location.href = url;
		});
	});
});
</script>
<?
require("../footer.hnt");
?>
