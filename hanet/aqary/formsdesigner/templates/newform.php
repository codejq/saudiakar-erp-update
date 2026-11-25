<style type="text/css">	

	.txtbox{ outline: 0; margin:0 4px 0 0; padding: .4em 1em; text-decoration:none !important; cursor:pointer; position: relative; text-align: center; zoom: 1; }
</style>
<div style="width:550px" align="right"  class="ui-widget-shadow  " >
	<form action="?" method='post' enctype="multipart/form-data" >
	<input type="hidden" value="1" name="addnewform" >
	صورة النموذج :<br>
	<input type='file' name='fil' >
	يجب ان يكون JPG او PNG او GIF
	<br><br>
	اسم النموذج : <br>
	<input type='text' class="txtbox" name='formname' value="نموذج جديد" onclick="this.select()" >
	<br><br>
	<input type="submit" value="التالي " >
	</form>
</div>