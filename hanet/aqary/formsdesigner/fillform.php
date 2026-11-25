<!DOCTYPE html >
<html dir="rtl">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>jQuery UI Example Page</title>
		<link type="text/css" href="css/ui-darkness/jquery-ui-1.8.16.custom.css" rel="stylesheet" />
		<link type="text/css" href="css/colorpicker.css"  rel="stylesheet" />
		
		<script type="text/javascript" src="js/jquery-1.6.2.min.js"></script>
		<script type="text/javascript" src="js/jquery-ui-1.8.16.custom.min.js"></script>
		<script type="text/javascript" src="js/colorpicker.js"></script>

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
			 
			body{ font: Arial , 62.5% "Trebuchet MS", sans-serif; margin: 50px;}
			.demoHeaders { margin-top: 2em; }
			#dialog_link {padding: .4em 1em .4em 20px;text-decoration: none;position: relative;}
			#dialog_link span.ui-icon {margin: 0 5px 0 0;position: absolute;left: .2em;top: 50%;margin-top: -8px;}
			ul#icons {margin: 0; padding: 0;}
			ul#icons li {margin: 2px; position: relative; padding: 4px 0; cursor: pointer; float: left;  list-style: none;}
			ul#icons span.ui-icon {float: left; margin: 0 4px;}
			.formelementhandel{position:absolute;z-index:150; left :120px; top:120px;filter:alpha(opacity=50); opacity:0.5;background-color:#ffffff;height:40px;width:180px;
				vertical-align: middle;padding:20px;cursor:move;float:left}
			.formelement{ filter:alpha(opacity=90); opacity:1;background-color:#00ffee;width:160px;overflow:hidden;}
			#toolboxform { position:absolute;float:right; width: 160px; height: auto; padding: 0.5em;  margin: 0 10px 10px 0; font-size:14px}
			#toolboxdraghanel { background-color:red;cursor: move; }
			#mainformimage {position:absolute;left:20px ; top :20px;}
		</style>	
	</head>
	<body id="mypage">

<img src="./images/forms/payment.jpg" id="mainformimage" >


	
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
	
	</script>

 
 	<div id="toolboxform" class="ui-widget-content"   >
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
	</div>
	
	
 
 
 <!--
	<div class="formelementhandel" id='feh1' ><textarea type=text id='fe1' class="formelement" ></textarea></div>
	<div class="formelementhandel" id='feh2' ><textarea type=text id='fe2' class="formelement" ></textarea></div>
	<div class="formelementhandel" id='feh3' ><textarea type=text id='fe3' class="formelement" ></textarea></div>
	<div class="formelementhandel" id='feh4' ><textarea type=text id='fe4' class="formelement" ></textarea></div>
-->
	
	
 





