<?
require("header.php");


if($_REQUEST['addnewform'])
{
	require('./simpleimage.php');
	//#######start upload #############
	if (!is_dir("./formsimages")) 
	{
	 mkdir("formsimages");
	}

	if($_FILES['fil']['name'])
	{
		$exten = strtolower(strrchr($_FILES['fil']['name'],'.'));
		$targetpic=$userinfo['user_id']."_".rand().$exten; 
		if($_FILES['fil']['type']!="image/pjpeg" and $_FILES['fil']['type']!="image/jpeg" and $_FILES['fil']['type']!="image/gif" and $_FILES['fil']['type']!="image/x-png")
		{
			$thanksmessage ="<font color=red>خطأ: يجب ان يكون نوع الصورة jpg او gif او png   </font> ";
			$redirectname = "صفحة ادخال النموذج  ";
			$redirecturl = "addform.php";
			require("./templates/thankyou_templet.php");
			exit();
		}
	 

		if($_FILES['fil']['size']>5200000)
		{
			$thanksmessage ="<font color=red>خطأ: اقصى حجم مسموح به 5.2ميجا يجب تقليل حجم الصورة  </font> ";
			$redirectname = "صفحة ادخال النموذج  ";
			$redirecturl = "addform.php";
			require("./templates/thankyou_templet.php");
			exit();
		}  


		if(!move_uploaded_file($_FILES['fil']['tmp_name'],"./formsimages/".$targetpic)) 
		{
			$thanksmessage ="<font color=red>لم استطع تحميل الملف</font> ";
			$redirectname = "صفحة ادخال النموذج  ";
			$redirecturl = "addform.php";
			require("./templates/thankyou_templet.php");
			exit();
		}
		else
		{
			if($maxpicsize2 < 1) {$maxpicsize2="2480";}
			$image = new SimpleImage();
			$image->load("./formsimages/".$targetpic);
			$image->resizeToWidth($maxpicsize2);
			$image->save("./formsimages/".$targetpic);
			$sql="insert into forms set 
				user_id = '". $userinfo['user_id'] ."',
				company_id = '". $userinfo['company_id'] ."',
				form_name = '". $_REQUEST['formname'] ."',
				form_image = '". $targetpic ."'";
			mysql_query($sql);
			$form_id = mysql_insert_id();
			$thanksmessage ="<font color=green>تم إضافة النموذج بنجاح  </font> ";
			$redirectname = "مصمم النماذج  ";
			$redirecturl = "addform.php?form_id=".$form_id;
			require("./templates/thankyou_templet.php");
			exit();
		}


	}
	//###########end upload ############
}


if(!$_REQUEST['form_id'])
{
	require("./templates/newform.php");
	exit();
}

$formdata=mysql_fetch_array(mysql_query("select * from forms where form_id='". intval($_REQUEST['form_id']) ."' and user_id='". $userinfo['user_id'] ."'"));

?>
<script type="text/javascript">
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
	.formelementhandel{position:absolute;z-index:150; left :120px; top:120px;filter:alpha(opacity=50); opacity:0.5;background-color:#ffffff;height:40px;width:180px;
		vertical-align: middle;padding:20px;cursor:move;float:left}
	.formelement{ filter:alpha(opacity=90); opacity:1;background-color:#00ffee;width:160px;overflow:hidden;}
	#toolboxform { position:fixed;top:10px ; right:10px; float:right; width: 160px; height: auto; padding: 0.5em;  margin: 0 10px 10px 0; font-size:14px}
	#toolboxdraghanel { background-color:red;cursor: move; }
	#mainformimage {position:absolute;left:0px ; top :0px;float:right; }
</style>

		

<img src="./formsimages/<?=$formdata['form_image'];?>" id="mainformimage" >



	
	<script>

	function initelement()
	{
		$(document).ready(function() {
			$(".formelement").resizable({
			  "resize" : function(event, ui) {
				var sid2 = ui.originalElement[0].id.replace('e','eh');
				$("#" + sid2).width( ui.size.width );
				$("#" + sid2).height( ui.size.height );
				$("#wwidth").val( ui.size.width );
				$("#hheight").val( ui.size.height );
				$("#nid").val( ui.originalElement[0].id );
			  } 
			  
			});
			
			$(".formelementhandel").click(function() {

				/*var sid2 = ui.originalElement[0].id.replace('e','eh');
				$("#" + sid2).width( ui.size.width );
				$("#" + sid2).height( ui.size.height );
				$("#wwidth").val( ui.size.width );
				$("#hheight").val( ui.size.height );
				$("#nid").val( ui.originalElement[0].id );*/
				var sid2 = $(this).attr('id');
				$("#xcordinate").val( $(this).offset().left );
				$("#ycordinate").val( $(this).offset().top );
				$("#wwidth").val( $(this).width() );
				$("#hheight").val( $(this).height() );
				$("#nid").val( $(this).attr('id') );

				$("#colorpicker").val( $('#' + sid2 ).data('obj').fontcolor );
				$("#colorpicker").css('background-color',"rgb(" + $('#' + sid2 ).data('obj').fontcolor.replace(/ /g,',') +  ")");
				$("#fontsize").val( $('#' + sid2 ).data('obj').fontsize );
				$("#fontname").val( $('#' + sid2 ).data('obj').fontname );
				$("#nname").val( $('#' + sid2 ).data('obj').nname );
				
				//alert();
		  
			});
			
			
			
			$(".formelement").css("overflow","hidden").css("background-color","#00ffee");
			
			
			
			$(".formelementhandel").draggable({
			  "drag" : function(event, ui) {
				$("#xcordinate").val( ui.offset.left );
				$("#ycordinate").val( ui.offset.top );

			  } 
			});
			
			$(".formelementhandel").hover(function(){$(this).css("background-color","#4895AA").css("opacity","0.5");},function(){$(this).css("background-color","transparent").css("opacity","1");});

			$(function() {
				$( "#toolboxform" ).draggable({ handle: "#toolboxdraghanel" });
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
					$("#" + $("#nid").val()).data('obj',{ 
						fontcolor : $("#colorpicker").val(),
						fontsize : $("#fontsize").val(),
						fontname : $("#fontname").val(),
						nname : $("#nname").val(),
						});
				}
			})
			.bind('keyup', function(){
				$(this).ColorPickerSetColor(this.value);
			});

			
			$(".property").bind('change keyup click', function(event) {
				$("#" + $("#nid").val()).data('obj',{ 
				fontcolor : $("#colorpicker").val(),
				fontsize : $("#fontsize").val(),
				fontname : $("#fontname").val(),
				nname : $("#nname").val(),
				});

				if(event.type=='change')
				{
					var currentelementh = "#" + $("#nid").val();
					var currentelement = currentelementh.replace('eh','e');
					$( currentelementh ).offset({ top: $("#ycordinate").val(), left: $("#xcordinate").val() }) ;
				}
				
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
		fontcolor : '255 255 225',
		fontsize : '12',
		fontname : 'arial',
		nname : 'new element',
		});
		
	}

	function removeelement()
	{
		$("#" + $("#nid").val()).remove( );
	}
	
	function formData(){
		$(".formelementhandel").each(function( index ) {
			console.log( index + ": " + $(this).data('obj') );
			$("#formdata").text($("#formdata").text() + index + ": " + "\r\n");
			$("#formdata").text($("#formdata").text() +  $(this).data('obj').fontcolor + "\r\n");
			$("#formdata").text($("#formdata").text() +  $(this).data('obj').fontsize + "\r\n");
			$("#formdata").text($("#formdata").text() +  $(this).data('obj').fontname + "\r\n");
			$("#formdata").text($("#formdata").text() +  $(this).data('obj').nname + "\r\n");
			$("#formdata").text($("#formdata").text() +  $(this).offset().left + "\r\n");
			$("#formdata").text($("#formdata").text() +  $(this).offset().top + "\r\n");
			$("#formdata").text($("#formdata").text() +  $(this).width() + "\r\n");
			$("#formdata").text($("#formdata").text() +  $(this).height()+ "\r\n" );
			
			
		});
	}
	</script>

 
 	<div id="toolboxform" class="ui-widget-content"   >
	<form id="mainForm" action="" method="post">
	<textarea id="formdata" name="formdata"></textarea>
	</form>
	<div id="toolboxdraghanel" class="ui-widget-header" >شريط الأدوات</div>

	<button onclick="addformelement();" style="font-size:10px">ab</button>
	<button onclick="removeelement();" style="font-size:10px">حذف</button>
	<br>
	العنصر:<br><input class='property' type="text" name="nid" id="nid"><br>
	X:<br><input class='property' type="text" name="xcordinate" id="xcordinate"><br>
	Y:<br><input class='property'  type="text" name="ycordinate" id="ycordinate"><br>
	W:<br><input class='property'  type="text" name="wwidth" id="wwidth"><br>
	H:<br><input class='property'  type="text" name="hheight" id="hheight"><br>
	الأسم:<br><input class='property'  type="text" name="nname" id="nname" ><br>
	الخط :<br>
	<select  class='property' name="fontname" id="fontname">
	<option>Arial</option>
	<option>Tahoma</option>
	</select>
	<input  class='property' type="text" name="fontsize" id="fontsize" size="2"><br>
	اللون : <br>
	<input  class='property' type="text" name="colorpicker" value="12" id="colorpicker">
	<br>
	<input type="text" value="حفظ" onclick="formData();">
	</div>
	
	
 
 
 





