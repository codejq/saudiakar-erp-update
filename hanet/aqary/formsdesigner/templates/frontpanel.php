<style type="text/css">	

	.txtbox{ outline: 0; margin:0 4px 0 0; padding: .4em 1em; text-decoration:none !important; cursor:pointer; position: relative; text-align: center; zoom: 1; }

	.fg-button { outline: 0; margin:0 4px 0 0; padding: .4em 1em; text-decoration:none !important; cursor:pointer; position: relative; text-align: center; zoom: 1; }

	.fg-button .ui-icon { position: absolute; top: 50%; margin-top: -8px; left: 50%; margin-left: -8px; }

	

	a.fg-button { float:left; }

	

	/* remove extra button width in IE */

	button.fg-button { width:auto; overflow:visible; }

	

	.fg-button-icon-left { padding-left: 2.1em; }

	.fg-button-icon-right { padding-right: 2.1em; }

	.fg-button-icon-left .ui-icon { right: auto; left: .2em; margin-left: 0; }

	.fg-button-icon-right .ui-icon { left: auto; right: .2em; margin-left: 0; }

	

	.fg-button-icon-solo { display:block; width:8px; text-indent: -9999px; }	 /* solo icon buttons must have block properties for the text-indent to work */	

	

	.fg-buttonset { float:left; position:relative; left:25%; text-align:left }

	.fg-buttonset .fg-button { float: left; }

	.fg-buttonset-single .fg-button, 

	.fg-buttonset-multi .fg-button { margin-right: -1px;}

	

	.fg-toolbar { padding: .5em; margin: 0;  }

	.fg-toolbar .fg-buttonset { margin-right:1.5em; padding-left: 1px; }

	.fg-toolbar .fg-button { font-size: 1em;  }



	/*demo page css*/

	h2 { clear: both; padding-top:1.5em; margin-top:0; } 

	.strike { text-decoration: line-through; }

</style>	



<script type="text/javascript">

	$(function(){

		//all hover and click logic for buttons

		$(".fg-button:not(.ui-state-disabled)")

		.hover(

			function(){ 

				$(this).addClass("ui-state-hover"); 

			},

			function(){ 

				$(this).removeClass("ui-state-hover"); 

			}

		)

		.mousedown(function(){

				$(this).parents('.fg-buttonset-single:first').find(".fg-button.ui-state-active").removeClass("ui-state-active");

				if( $(this).is('.ui-state-active.fg-button-toggleable, .fg-buttonset-multi .ui-state-active') ){ $(this).removeClass("ui-state-active"); }

				else { $(this).addClass("ui-state-active"); }	

		})

		.mouseup(function(){

			if(! $(this).is('.fg-button-toggleable, .fg-buttonset-single .fg-button,  .fg-buttonset-multi .fg-button') ){

				$(this).removeClass("ui-state-active");

			}

		});

	});

</script>



 

	<div align="center" class="fg-buttonset fg-buttonset-single" >

		<button class="fg-button ui-state-default ui-priority-primary ui-corner-left"  style="width:100% !important;" onclick="alert()">
		تسجيل شركة جديدة 
		</button>
		
		<button class="fg-button ui-state-default ui-priority-primary">
		تسجيل شركة جديدة 
		</button>

		<button class="fg-button ui-state-default ui-priority-primary">
		تسجيل شركة جديدة 
		</button>
		
		<button class="fg-button ui-state-default ui-priority-primary ui-corner-right">
		تسجيل شركة جديدة 
		</button>

	</div>	
 

<br><br>
<br><br>

<div align="center" >
	<div style="width:250px" align="right"  class="ui-widget-shadow  " >
 		<b>تسجيل الدخول :</b>
		<form class="" class="ui-widget-content">
		<b>اسم المستخدم : </b>
		<br>
		<input type="text" name="user_name" class="txtbox"  >
		<br>
		<b>كلمة المرور :</b>
		<br>
		<input type="text" name="user_password" class="txtbox" >
		<br><br>
		<input type="checkbox"   name="rememberme" id="rememberme" ><label for="rememberme"> تذكرني </label> <button class="fg-button ui-state-default ui-priority-primary">تسجيل الدخول</button>
		<br><br>
		</form>
 	</div>
</div>

<br><br>
<br><br>
