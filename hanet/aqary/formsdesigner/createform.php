<?php
header('X-UA-Compatible:  IE=8, IE=9, IE=10, IE=11, IE=EDGE, chrome=1');
require("header.php");

// Initialize variables
$formid = isset($_REQUEST['formid']) ? intval($_REQUEST['formid']) : 0;
$doSaveForm = isset($_POST['doSaveForm']) ? $_POST['doSaveForm'] : 0;
$javaEditForm = '';
$usebarcodeform = '';

if($formid){
	$formData  =mysql_fetch_array(mysql_query("select * from  forms_data where id='".$formid."'"));
	$_REQUEST['formfilename'] = $formData['form_file_name'];
	$_REQUEST['formtype'] = $formData['form_type'] ;
	if($formData['usebarcode']==1){
		$usebarcodeform = " $('#usebarcode').attr('checked','checked');
							$('#imageframe').contents().find('#qrimage').show(); ";
	}
	// Saved values are emitted as JSON literals: a line break, quote or backslash in
	// any saved value used to break the whole script, so no field was drawn.
	$jsFlags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	$qrX = intval($formData['qrxcordinate']);
	$qrY = intval($formData['qrycordinate']);
	$javaEditForm = "
		$('#form_name').val(" . json_encode((string)$formData['form_name'], $jsFlags) . ");
		$('#printingtype').val(" . json_encode((string)$formData['printingtype'], $jsFlags) . ");
		$('#qrxcordinate').val({$qrX});
		$('#qrycordinate').val({$qrY});
		{$usebarcodeform}
		$('#imageframe').contents().find('#qrimage').offset({ top: {$qrY}, left: {$qrX} });

	";
	$sql="select * from forms_fields where forms_data_id ='".intval($formData['id'])."'";
	$rs = mysql_query($sql);
	$i=0;
	while($row = mysql_fetch_array($rs)){
		$fieldArgs = array();
		foreach (array('fontcolor','fontsize','fontname','field_name','field_value','field_left','field_top','field_width','field_height') as $fieldKey) {
			$fieldArgs[] = json_encode((string)$row[$fieldKey], $jsFlags);
		}
		$javaEditForm .= "
			addSavedformelement(" . implode(',', $fieldArgs) . ");
		";
	}

}
if(!$_REQUEST['formfilename']){
	echo "لم تختر اي نموذج ";
	exit();
}

if($_REQUEST['formtype'] && !is_numeric($_REQUEST['formtype'])){
	$FormFileName = './formsimages/'.$_REQUEST['formtype'].'/'.$_REQUEST['formfilename'];
	$data = str_replace("\r","",file_get_contents('./formsdata/'.$_REQUEST['formtype'].'.txt'));
	$data = explode("\n" , $data);
	$data = array_values(array_filter($data));
	$selectHtml = "<select name='selfiledvalue' id='selfiledvalue' class='property form-select form-select-sm' >";
	for($i=0;$i<count($data);$i++){
		$v = str_replace("_"," ",$data[$i]) ;
		$selectHtml .= "<option value='{$data[$i]}'>{$v}</option>";
	}
	$selectHtml .= "</select>";
}


if(is_numeric($_REQUEST['formtype'])){
	$FormFileName = './formsimages/'.$_REQUEST['formtype'].'/'.$_REQUEST['formfilename'];
	$selectHtml = "<select name='selfiledvalue' id='selfiledvalue' class='property form-select form-select-sm' >";
	$sql="select * from department_field where dep_id='".$_REQUEST['formtype']."'";
	$selectHtml .= "<option value='نص_حر'>نص حر</option>";
	$rs =mysql_query($sql,$link);
	while($frow = mysql_fetch_array($rs)){
		$v = str_replace(" ","_",$frow['fld_name'])."_" ;
		$selectHtml .= "<option value='{$v}'>{$frow['fld_name']}</option>";

	}
	$selectHtml .= "</select>";
}


if($doSaveForm){
	require("sql.update.php");
	$sql="insert into forms_data set
			form_name ='".$_POST['form_name']."'  ,
			printingtype ='".$_POST['printingtype']."'  ,
			form_type ='".$_POST['formtype']."' ,
			usebarcode ='".$_POST['usebarcode']."' ,
			qrxcordinate ='".$_POST['qrxcordinate']."' ,
			qrycordinate ='".$_POST['qrycordinate']."' ,
			form_file_name = '".$_POST['formfilename']."',
			formuid = '".$_REQUEST['formuid']."'  ";
	if($_POST['formid']){
		$sql="update  forms_data set
			form_name ='".$_POST['form_name']."'  ,
			printingtype ='".$_POST['printingtype']."'  ,
			form_type ='".$_POST['formtype']."' ,
			usebarcode ='".$_POST['usebarcode']."' ,
			qrxcordinate ='".$_POST['qrxcordinate']."' ,
			qrycordinate ='".$_POST['qrycordinate']."' ,
			form_file_name = '".$_POST['formfilename']."',
			formuid = '".$_REQUEST['formuid']."'
			where id='{$_POST['formid']}'
			";
	}
	mysql_query($sql);
	$forms_data_id = mysql_insert_id();
	if($_POST['formid']){
		$forms_data_id = $_POST['formid'];
		mysql_query("delete from forms_fields where forms_data_id='{$_POST['formid']}' ");
	}
	$formdata = $_POST['formdata'];
	$formdata = array_values(array_filter(explode(':#!@#' , $formdata )));
	for($i=0;$i<count($formdata) ; $i++){
		$fields = explode('&^&' ,$formdata[$i]  );
		$sql="insert into forms_fields set forms_data_id='{$forms_data_id}',
				fontcolor = '{$fields[1]}',
				fontsize = '{$fields[2]}',
				fontname = '{$fields[3]}',
				field_name = '{$fields[4]}',
				field_value = '{$fields[5]}',
				field_left = '{$fields[6]}',
				field_top = '{$fields[7]}',
				field_width = '{$fields[8]}',
				field_height = '{$fields[9]}',
				formuid = '".$_REQUEST['formuid']."'  ";
		mysql_query($sql);
	}
	echo "<script>window.location.href='createform.php?formid={$forms_data_id}'</script>";
	exit();
}

	//fonts list
	$fonts_list = '<select class="property form-select form-select-sm" name="fontname" id="fontname" >	';
	$fontdir = 'GD/'; //Pick your folder
	$dimg = opendir($fontdir);//Open directory
		while($fontfile = readdir($dimg))
		{
			if(strtolower(substr($fontfile,-3)) =="ttf"){

				$fontfilev  = str_replace(array(".ttf","-","_")," ",$fontfile);
				$selectop = '';
				//if($fontfile == json_decode(urldecode($_COOKIE['cookie_selectedfont'])) ){$selectop =" selected ";}else{$selectop ="";}
				$fonts_list = $fonts_list . "<option value='{$fontfile}' $selectop>{$fontfilev}</option>";
			}
		}
	closedir($dimg);
	$fonts_list = $fonts_list ."</select>";

?>

	<?php
	$cfEsc = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
	$cfFormType = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_REQUEST['formtype']);
	$cfBackUrl = 'formselector.php?formtype=' . rawurlencode($cfFormType);
	?>
	<div id="loading" class="cf-loading"><div class="spinner-border text-success" role="status"></div><span>جارٍ التحميل...</span></div>
	<div class="cf-layout" dir="rtl">
 	<aside id="toolboxform2" class="cf-sidebar">
	<div id="toolboxdraghanel2" class="cf-sidebar-head">
		<a href="<?= $cfEsc($cfBackUrl) ?>" class="btn btn-sm btn-light w-100 mb-2"><i class="bi bi-arrow-right me-1"></i> عودة للنماذج</a>
		<div class="cf-sidebar-title"><i class="bi bi-tools me-1"></i> شريط الأدوات</div>
	</div>
	<form id="mainForm" action="" method="post" class="cf-sidebar-body">
	<input type="hidden" name="formtype" id="formtype" value="<?= $cfEsc($_REQUEST['formtype']) ?>">
	<input type="hidden" name="formfilename" id="formfilename" value="<?= $cfEsc($_REQUEST['formfilename']) ?>">
	<input type="hidden" name="doSaveForm" id="doSaveForm" value="1">
	<input type="hidden" name="formid" id="formid" value="<?=$formid?>">
	<input type="hidden" name="qrxcordinate" id="qrxcordinate" value="0">
	<input type="hidden" name="qrycordinate" id="qrycordinate" value="0">
	<textarea id="formdata" name="formdata" class="d-none"></textarea>

	<section class="cf-group">
		<div class="cf-group-title">بيانات النموذج</div>
		<label class="form-label" for="form_name">اسم النموذج</label>
		<input type="text" class="form-control form-control-sm" name="form_name" id="form_name" value="نموذج جديد">
		<label class="form-label mt-2" for="printingtype">نوع الطباعة</label>
		<select name='printingtype' id='printingtype' class="form-select form-select-sm">
			<option value='0'>طباعة كامل النموذج</option>
			<option value='1'>طباعة نصوص فقط </option>
		</select>
		<div class="form-check mt-2">
			<input type="checkbox" class="form-check-input" id="usebarcode" name="usebarcode" value='1'>
			<label class="form-check-label" for="usebarcode">استخدم الختم الرقمي (QR)</label>
		</div>
	</section>

	<section class="cf-group">
		<div class="cf-group-title">العناصر</div>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-sm btn-outline-success flex-fill" id='newelement' onclick="return false;"><i class="bi bi-plus-square"></i> إضافة حقل</button>
			<button type="button" class="btn btn-sm btn-outline-danger flex-fill" onclick="removeelement();return false;"><i class="bi bi-trash"></i> حذف المحدد</button>
		</div>
	</section>

	<section class="cf-group">
		<div class="cf-group-title">العنصر المحدد</div>
		<label class="form-label" for="nid">المعرف</label>
		<input class='property form-control form-control-sm' type="text" name="nid" id="nid" dir="ltr">
		<div class="row g-2 mt-1">
			<div class="col-6"><label class="form-label" for="xcordinate">X</label><input class='property form-control form-control-sm' type="text" name="xcordinate" id="xcordinate" dir="ltr"></div>
			<div class="col-6"><label class="form-label" for="ycordinate">Y</label><input class='property form-control form-control-sm' type="text" name="ycordinate" id="ycordinate" dir="ltr"></div>
			<div class="col-6"><label class="form-label" for="wwidth">العرض W</label><input class='property form-control form-control-sm' type="text" name="wwidth" id="wwidth" dir="ltr"></div>
			<div class="col-6"><label class="form-label" for="hheight">الارتفاع H</label><input class='property form-control form-control-sm' type="text" name="hheight" id="hheight" dir="ltr"></div>
		</div>
		<label class="form-label mt-2" for="selfiledvalue">الحقل</label>
		<?=$selectHtml;?>
		<label class="form-label mt-2" for="nname">الاسم</label>
		<input class='property form-control form-control-sm' type="text" name="nname" id="nname">
		<label class="form-label mt-2" for="elementvalue">القيمة</label>
		<input class='property form-control form-control-sm' type="text" name="elementvalue" value="" id="elementvalue">
	</section>

	<section class="cf-group">
		<div class="cf-group-title">الخط واللون</div>
		<label class="form-label" for="fontname">الخط</label>
		<?=$fonts_list;?>
		<label class="form-label mt-2" for="fontsize">حجم الخط</label>
		<input class='property form-control form-control-sm' type="text" name="fontsize" id="fontsize" dir="ltr" placeholder="20">
	</section>

	<section class="cf-group">
		<div class="cf-group-title">اللون</div>
		<label class="form-label" for="colorpicker">لون النص (R G B)</label>
		<div class="cf-color-row">
			<span id="cfColorSwatch" class="cf-color-swatch" title="معاينة اللون"></span>
			<input class='property form-control form-control-sm' type="text" name="colorpicker" value="12 48 74" id="colorpicker" dir="ltr">
		</div>
		<small class="text-muted d-block mt-1">اللون الافتراضي: <span class="cf-color-default" dir="ltr">12 48 74</span> - اضغط على الحقل لاختيار لون</small>
	</section>

	<div class="cf-sidebar-foot">
		<input type="button" id="saveformdata" class="btn btn-success w-100" onclick="$('#loading').show(); $('#saveformdata').hide();formData();return false;" value="حفظ النموذج">
	</div>
	</form>
	</aside>

	<img src="images/qr.png" id="qrimage" style="display:none;position:absolute;left:10px;top:10px;z-index:5000;border:5px solid #00ffee;"  >

<main class="cf-canvas">
	<iframe src="imageframe.php?imageid=<?= htmlspecialchars((string)$FormFileName, ENT_QUOTES, 'UTF-8') ?>" id="imageframe" frameborder="0" ></iframe>
</main>
</div>


<script type="text/javascript">
	 var fd = document.getElementById('imageframe').contentWindow || document.getElementById('imageframe').document;

	function addformelement()
	{
	elementcounts ++ ;
	//document.write("<div class=\"formelementhandel\" id='feh1' ><textarea type=text id='fe1' class=\"formelement\" ></textarea></div>");
	$('#mypage').prepend( '<div class="formelementhandel" id="feh' + elementcounts + '" ><textarea type=text id="fe' + elementcounts + '" class="formelement" ></textarea></div>' );
	//$('#mypage').prepend( '<div class=formelementhandel  >test</div>' );
	 initelement();
	$("#feh" + elementcounts).data('obj',{
		fontcolor : '12 48 74',
		fontsize : '20',
		fontname : 'FD-Bd.ttf',
		nname : 'new element',
		selfiledvalue : '',
		selfiledvalueText :'',
		});

	}


$(function() {
	$('#newelement').click(function() {
		var $f = $('#imageframe');

		fd.addelement();
	});

	$('#imageframe').css('height', '100%');
});

function addelement(){
		alert();
		elementcounts ++ ;
		var new_offset = {top:30, left:40};
		var new_width = 200;
		var new_height = 150;
		var newElement$ = $('<div class="drabablediv formelementhandel" id="feh' + elementcounts + '"><textarea id="fe' + elementcounts + '" class="formelement drababletextarea"></textarea></div>')
										.width(new_width)
						   			.height(new_height)
                                    .draggable({
                                        cancel: "text",
                                        start: function (){
                                            $('#textarea').focus();
                                         },
                                        stop: function (){
                                            $('#textarea').focus();
                                         }
                                     })
                                    .resizable()
									.css({
											'position'	        : 'absolute',
											'background-color'  : 'yellow',
											'border-color'      : 'black',
											'border-width'      : '1px',
											'border-style'	    : 'solid'
								     })
									 .offset(new_offset)
						             .appendTo('body');
					//initelement();
					$("#feh" + elementcounts).data('obj',{
						fontcolor : '12 48 74',
						fontsize : '20',
						fontname : 'FD-Bd.ttf',
						nname : 'new element',
						selfiledvalue : '',
						selfiledvalueText :'',
						});
	}


	$(function(){

		// Accordion
		$("#accordion").accordion({ header: "h3" });

		// Tabs
		$('#tabs').tabs();


		// Dialog
		$('#dialog').dialog({
			autoOpen: false,
			width: 600,
			buttons: {
				"Ok": function() {
					$(this).dialog("close");
				},
				"Cancel": function() {
					$(this).dialog("close");
				}
			}
		});

		// Dialog Link
		$('#dialog_link').click(function(){
			$('#dialog').dialog('open');
			return false;
		});

		// Datepicker
		$('#datepicker').datepicker({
			inline: true
		});

		// Slider
		$('#slider').slider({
			range: true,
			values: [17, 67]
		});

		// Progressbar
		$("#progressbar").progressbar({
			value: 20
		});

		//hover states on the static widgets
		$('#dialog_link, ul#icons li').hover(
			function() { $(this).addClass('ui-state-hover'); },
			function() { $(this).removeClass('ui-state-hover'); }
		);

	});


</script>
<style type="text/css">

	body{ font: Arial , 62.5% "Trebuchet MS", sans-serif; margin: 10px;}
	.demoHeaders { margin-top: 2em; }
	#dialog_link {padding: .4em 1em .4em 20px;text-decoration: none;position: relative;}
	#dialog_link span.ui-icon {margin: 0 5px 0 0;position: absolute;left: .2em;top: 50%;margin-top: -8px;}
	ul#icons {margin: 0; padding: 0;}
	ul#icons li {margin: 2px; position: relative; padding: 4px 0; cursor: pointer; float: left;  list-style: none;}
	ul#icons span.ui-icon {float: left; margin: 0 4px;}
	.xformelementhandel{position:absolute;z-index:150; left :120px; top:120px;filter:alpha(opacity=50); opacity:0.5;background-color:#ffffff;height:40px;width:180px;
		vertical-align: middle;padding:20px;cursor:move;float:left}
	.xformelement{ filter:alpha(opacity=90); opacity:1;background-color:#00ffee;width:160px;overflow:hidden;}
	#toolboxform { position:fixed;top:10px ; rxight:10px;z-index:5000; float:right; width: 160px; height: auto; padding: 0.5em;  margin: 0 10px 10px 0; font-size:14px}
	#toolboxdraghanel { background-color:red;cursor: move; }
	#mainformimage {position:absolute;left:0px ; top :0px;float:right; }
	/* Two-column editor: toolbox on the right (RTL), form canvas filling the rest. */
	.cf-layout{display:flex;gap:14px;align-items:stretch;padding:10px 12px;height:calc(100vh - 70px);font-family:inherit}
	.cf-sidebar{flex:0 0 290px;display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 4px 14px rgba(15,23,42,.06);overflow:hidden;font-size:13px}
	.cf-sidebar-head{background:linear-gradient(135deg,#0a7047 0%,#0d9488 100%);color:#fff;padding:12px}
	.cf-sidebar-title{font-weight:700;font-size:15px}
	.cf-sidebar-body{flex:1;overflow-y:auto;padding:0;margin:0;display:flex;flex-direction:column}
	.cf-group{padding:12px;border-bottom:1px solid #eef2f7}
	.cf-group-title{font-weight:700;color:#0f766e;margin-bottom:8px;font-size:13px}
	.cf-group .form-label{margin-bottom:2px;font-size:12px;color:#475569}
	.cf-sidebar-foot{position:sticky;bottom:0;margin-top:auto;padding:12px;background:#f8fafc;border-top:1px solid #e2e8f0}
	.cf-canvas{flex:1;min-width:0;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden}
	#imageframe{width:100%;height:100%;border:0;display:block;background:#fff}
	.cf-loading{position:fixed;inset:0;z-index:6000;background:rgba(255,255,255,.75);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;font-weight:700;color:#0f766e}
	.cf-sidebar .select2-container{width:100% !important}
	.cf-color-row{display:flex;gap:8px;align-items:center}
	.cf-color-swatch{flex:0 0 32px;height:32px;border-radius:8px;border:1px solid #cbd5e1;background:rgb(12,48,74);box-shadow:inset 0 0 0 2px #fff}
	.cf-color-default{font-weight:700;color:#0c304a}
	/* The legacy picker paints the input itself; the swatch shows the color, so keep the value readable. */
	.cf-color-row #colorpicker{background-color:#fff !important;color:#0f172a !important}
	@media (max-width: 900px){.cf-layout{flex-direction:column;height:auto}.cf-sidebar{flex-basis:auto}.cf-canvas{height:80vh}}


.drababletextarea {
    height:100%;
    background-color:beige;
    width:100%;
    resize:none; border:none;
    padding:0px; margin:0px;

}
.drabablediv { padding:0px; }

</style>






	<script>

	function initelement()
	{
		$(document).ready(function() {

			$(".formelement").css("overflow","hidden").css("background-color","#00ffee");

			$(".formelementhandel").hover(function(){$(this).css("background-color","#4895AA").css("opacity","0.5");},function(){$(this).css("background-color","transparent").css("opacity","1");});

			$(function() {

				$('#usebarcode').bind('change', function () {
				   if ($(this).is(':checked'))
					 $('#imageframe').contents().find('#qrimage').show();
				   else
					 $('#imageframe').contents().find('#qrimage').hide();
				});

				$('#imageframe').contents().find('#qrimage').draggable({
				  "drag" : function(event, ui) {
					$("#qrxcordinate").val( ui.offset.left );
					$("#qrycordinate").val( ui.offset.top );

				  }
				});

				//$( "#toolboxform" ).disableSelection();
			});

			$(function() {
				$( "input:submit,button" ).button();
			});



			$("#colorpicker").ColorPicker({
				onSubmit: function(hsb, hex, rgb, el) {
					$(el).val(rgb.r +' '+ rgb.g +' '+  rgb.b);
					$(el).css("background-color","#" + hex);
					$(el).ColorPickerHide();
				},
				onBeforeShow: function () {
					var colors = $(this).val().split(' ');
					$(this).ColorPickerSetColor({r:colors[0],g:colors[1],b:colors[2]});
				},
				onChange: function (hsb, hex, rgb) {
					$("#colorpicker").val(rgb.r +' '+ rgb.g +' '+  rgb.b);
					$("#colorpicker").css("background-color","#" + hex);
					fd.setDataValues($("#colorpicker").val(),$("#fontsize").val(),$("#fontname").val(),
					$("#nname").val(),$("#selfiledvalue").val(),$("#selfiledvalue").text());
				}
			})
			.bind('keyup', function(){
				$(this).ColorPickerSetColor(this.value);
			});


			$(".property").bind('change keyup click', function(event) {

				fd.setDataValues($("#colorpicker").val(),$("#fontsize").val(),$("#fontname").val(),
				$("#nname").val(),$("#selfiledvalue").val(),$("#selfiledvalue").text());

				if(event.type=='change')
				{
					var currentelementh = "#" + $("#nid").val();
					var currentelement = currentelementh.replace('eh','e');
					$( currentelementh ).offset({ top: $("#ycordinate").val(), left: $("#xcordinate").val() }) ;
				}
				$('#imageframe').contents().find("#" + ($("#nid").val()).replace('eh','e')).val($("#selfiledvalue").val());
			});


		});
	}

	 initelement();
	 var elementcounts = 0 ;

	function addformelement()
	{
	elementcounts ++ ;
	//document.write("<div class=\"formelementhandel\" id='feh1' ><textarea type=text id='fe1' class=\"formelement\" ></textarea></div>");
	$('#mypage').prepend( '<div class="formelementhandel" id="feh' + elementcounts + '" ><textarea type=text id="fe' + elementcounts + '" class="formelement" ></textarea></div>' );
	//$('#mypage').prepend( '<div class=formelementhandel  >test</div>' );
	 initelement();
	$("#feh" + elementcounts).data('obj',{
		fontcolor : '12 48 74',
		fontsize : '20',
		fontname : 'FD-Bd.ttf',
		nname : 'new element',
		selfiledvalue : '',
		selfiledvalueText :'',
		});

	}

	function addSavedformelement(vfontcolor ,vfontsize ,vfontname ,vnname ,vselfiledvalue, field_left,field_top,field_width,field_height  )
	{
		fd.addSavedformelement(vfontcolor ,vfontsize ,vfontname ,vnname ,vselfiledvalue, field_left,field_top,field_width,field_height  );

	}


	function removeelement()
	{
		$('#imageframe').contents().find("#" + $("#nid").val().replace('fe','feh')).remove( );
		return false;
	}

	function formData(){
		fd.formData();
		setTimeout(function(){ $("#mainForm").submit(); }, 3000);
	}



	// Color preview: the field holds "R G B"; the frame also fills it when a field is selected,
	// which fires no event, so the swatch is kept in sync on a short interval.
	function cfSyncColorSwatch(){
		var parts = String($('#colorpicker').val() || '').trim().split(/[\s,]+/);
		var ok = parts.length === 3 && parts.every(function (p) { return /^\d{1,3}$/.test(p) && +p <= 255; });
		$('#cfColorSwatch').css('background-color', ok ? 'rgb(' + parts.join(',') + ')' : 'transparent')
			.attr('title', ok ? 'rgb(' + parts.join(', ') + ')' : 'قيمة لون غير صحيحة');
	}
	$(function(){
		cfSyncColorSwatch();
		$('#colorpicker').on('input change keyup', cfSyncColorSwatch);
		setInterval(cfSyncColorSwatch, 400);
	});

	// Draw saved fields once the image frame has loaded (it can take longer than 4s on slow machines).
	var savedFormTries = 0;
	function loadSavedFormFields(){
		var frameReady = false;
		try {
			frameReady = fd && fd.document && fd.document.readyState === 'complete' && typeof fd.addSavedformelement === 'function';
		} catch (e) {}
		if (!frameReady && ++savedFormTries < 100) {
			setTimeout(loadSavedFormFields, 300);
			return;
		}
		try {
			<?=$javaEditForm;?>
		} catch (e) {
			if (window.console) console.error('createform: unable to draw saved fields', e);
		}
		$('#loading').hide();
	}
	$(function() {
		$('#imageframe').ready(function(){
			setTimeout(loadSavedFormFields, 4000);
		});
	});


	</script>






 <!--
	<div class="formelementhandel" id='feh1' ><textarea type=text id='fe1' class="formelement" ></textarea></div>
	<div class="formelementhandel" id='feh2' ><textarea type=text id='fe2' class="formelement" ></textarea></div>
	<div class="formelementhandel" id='feh3' ><textarea type=text id='fe3' class="formelement" ></textarea></div>
	<div class="formelementhandel" id='feh4' ><textarea type=text id='fe4' class="formelement" ></textarea></div>
-->









