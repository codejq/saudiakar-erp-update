<?php
error_reporting(0);
session_start();
include('../../connectdb.hnt');

// Include centralized storage helpers
require_once(__DIR__ . '/../../lib/storage/helpers.php');

// Define application base path for URL generation
if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', '/aqary');
}

if($_POST['photoid']){
	$sql="update upfiles   set 	filedescription='".$_POST['photodescription']."' where id='{$_POST['photoid']}'";
	mysql_query($sql);
	echo " تم تعديل الوصف الي : " . $_POST['photodescription']."<ok>";
	exit();
}

define ("MAX_SIZE","900000"); 
function getExtension($str)
{
         $i = strrpos($str,".");
         if (!$i) { return ""; }
         $l = strlen($str) - $i;
         $ext = substr($str,$i+1,$l);
         return $ext;
}

function gd_create_scaled_image($file_path,  $options) {
        if (!function_exists('imagecreatetruecolor')) {
            error_log('Function not found: imagecreatetruecolor');
            return false;
        }

		// Convert upload path to thumbnail path
	$uploaddir = upload_path('uploadcenter');
	$thumdir = upload_path('uploadcenter.thumbnails');
	$new_file_path = str_replace($uploaddir, $thumdir, $file_path);

        $type = strtolower(substr(strrchr($file_path, '.'), 1));
        switch ($type) {
            case 'jpg':
            case 'jpeg':
                $src_func = 'imagecreatefromjpeg';
                $write_func = 'imagejpeg';
                $image_quality = isset($options['jpeg_quality']) ?
                    $options['jpeg_quality'] : 75;
                break;
            case 'gif':
                $src_func = 'imagecreatefromgif';
                $write_func = 'imagegif';
                $image_quality = null;
                break;
            case 'png':
                $src_func = 'imagecreatefrompng';
                $write_func = 'imagepng';
                $image_quality = isset($options['png_quality']) ?
                    $options['png_quality'] : 9;
                break;
            default:
                return false;
        }
        $src_img = $src_func($file_path);
        $image_oriented = false;

        $max_width = $img_width = imagesx($src_img);
        $max_height = $img_height = imagesy($src_img);
        if (!empty($options['max_width'])) {
            $max_width = $options['max_width'];
        }
        if (!empty($options['max_height'])) {
            $max_height = $options['max_height'];
        }
        $scale = min(
            $max_width / $img_width,
            $max_height / $img_height
        );
        if ($scale >= 1) {
            if ($image_oriented) {
                return $write_func($src_img, $new_file_path, $image_quality);
            }
            if ($file_path !== $new_file_path) {
                return copy($file_path, $new_file_path);
            }
            return true;
        }
        if (empty($options['crop'])) {
            $new_width = $img_width * $scale;
            $new_height = $img_height * $scale;
            $dst_x = 0;
            $dst_y = 0;
            $new_img = imagecreatetruecolor($new_width, $new_height);
        } else {
		
            if (($img_width / $img_height) >= ($max_width / $max_height)) {
                $new_width = $img_width / ($img_height / $max_height);
                $new_height = $max_height;
            } else {
                $new_width = $max_width;
                $new_height = $img_height / ($img_width / $max_width);
            }
            $dst_x = 0 - ($new_width - $max_width) / 2;
            $dst_y = 0 - ($new_height - $max_height) / 2;
            $new_img = imagecreatetruecolor($max_width, $max_height);
        }
        // Handle transparency in GIF and PNG images:
        switch ($type) {
            case 'gif':
            case 'png':
                imagecolortransparent($new_img, imagecolorallocate($new_img, 0, 0, 0));
            case 'png':
                imagealphablending($new_img, false);
                imagesavealpha($new_img, true);
                break;
        }
        $success = imagecopyresampled(
            $new_img,
            $src_img,
            $dst_x,
            $dst_y,
            0,
            0,
            $new_width,
            $new_height,
            $img_width,
            $img_height
        ) && $write_func($new_img, $new_file_path, $image_quality);
        //$this->gd_set_image_object($file_path, $new_img);
        return $success;
    }
	
function create_extention_image($ext){
	$thumdir = upload_path('uploadcenter.thumbnails');
	$ImagePath = $thumdir . DIRECTORY_SEPARATOR . $ext . ".png";
	if(file_exists($ImagePath)){
		return;
	}	
// Create the image
$im = imagecreatetruecolor(150, 150);

// Create some colors
$fill_color = imagecolorallocate($im, 87, 153, 191);
$grey = imagecolorallocate($im, 128, 128, 128);
$black = imagecolorallocate($im, 0, 0, 0);
$red = imagecolorallocate($im, 128, 0, 0);
$pink = imagecolorallocate($im, 255, 155, 224);
imagefilledrectangle($im, 0, 0, 150, 150, $fill_color );


// Replace path by your own font path
$font = '../../fonts/ftl.ttf';


$tb = imagettfbbox(25, 0, $font,  strtoupper($ext) . " FILE ");
$x = ceil((150 - $tb[2]) / 2);
imagettftext($im, 25, 0, $x , 85,  $grey, $font, strtoupper($ext) . " FILE ");
imagettftext($im, 25, 0, $x + 1, 86, $pink, $font, strtoupper($ext) . " FILE ");
imagettftext($im, 25, 0, $x + 2, 87, $pink, $font, strtoupper($ext) . " FILE ");
imagettftext($im, 25, 0, $x + 2, 88, $pink, $font, strtoupper($ext) . " FILE ");


imagefilledrectangle($im, 0, 0, 150, 50, $pink );
imagefilledrectangle($im, 0, 100, 150, 150, $pink );

// Using imagepng() results in clearer text compared with imagejpeg()
imagepng($im,$ImagePath,9);
imagedestroy($im);

}


// Use centralized storage
$uploaddir = upload_path('uploadcenter') . DIRECTORY_SEPARATOR;
$thumdir = upload_path('uploadcenter.thumbnails') . DIRECTORY_SEPARATOR;

// Ensure directories exist
ensure_dir($uploaddir);
ensure_dir($thumdir);

// Create error placeholder if it doesn't exist
$ImageErrorPath = $thumdir . "Error.png";
if(!file_exists($ImageErrorPath)){
	create_extention_image("Error");
}

// Helper function to get web URL for uploaded files
function get_upload_url($filename) {
	return upload_url('uploadcenter', $filename);
}

function get_thumbnail_url($filename) {
	return upload_url('uploadcenter.thumbnails', $filename);
}


$valid_formats = array("jpg", "png", "gif", "bmp","jpeg");
$invalid_formats = array("php", "inc", "exe", "bat","shl","dll","hnt","vbs","js","aspx","asp");
if(isset($_POST) and $_SERVER['REQUEST_METHOD'] == "POST") 
{
	

    foreach ($_FILES['photos']['name'] as $name => $value)
    {
	
        $filename = stripslashes($_FILES['photos']['name'][$name]);
        $size=filesize($_FILES['photos']['tmp_name'][$name]);
        //get the extension of the file in a lower case format
          $ext = getExtension($filename);
          $ext = strtolower($ext);
     	
         if(!in_array($ext,$invalid_formats))
         {
	       if ($size < (MAX_SIZE*1024))
	       {
		   $image_name=time()."_".rand(10000,100000)."_".rand(10000,100000).".".$ext;
		   $newname=$uploaddir.$image_name;
           
           if (move_uploaded_file($_FILES['photos']['tmp_name'][$name], $newname)) 
           {
	       $time=time();
		   $sql="insert into upfiles set 
					filename ='".$image_name."', 
					filedescription='".$_POST['photoscaption']."',
					ownerid='".$mostagerid."',
					userid='".$user_data['userid']."',
					category='temp' ";
			mysql_query($sql,$link);
			$id = mysql_insert_id();
			//if this file is image
			if(in_array($ext,$valid_formats)){
				$thumb_url = get_thumbnail_url($image_name);
				$file_url = get_upload_url($image_name);
				echo "
					<div class='imgList'>
						<img src='{$thumb_url}' onclick=\"window.open('{$file_url}');\"  class='imgListimg' ><br/>
						<form id='imageform-{$id}' method='post'  enctype='multipart/form-data'   action='/aqary/admin/include/ajaxImageUpload.php' style='clear:both'>
						<input type='hidden' name='photoid' value='{$id}' class='photoid' >
						<input type='text'	value='{$_POST['photoscaption']}'
						originalvalue='{$_POST['photoscaption']}' photoid='{$id}' id='photo-{$id}' name='photodescription' class='imagedescription' >
						</form>
						<div id='imageloadstatus-{$id}' style='display:none'><img src='/aqary/admin/images/loader.gif' alt='Uploading....'/></div>
					</div>
				";				
				$options['max_height'] = 150;
				$options['max_width'] = 150;
				$options['crop'] = true;
				gd_create_scaled_image($newname,  $options);
			}
			else{
				create_extention_image($ext);
				$thumb_url = get_thumbnail_url($ext . ".png");
				$file_url = get_upload_url($image_name);
				echo "
					<div class='imgList'>
						<img src='{$thumb_url}' onclick=\"window.open('{$file_url}');\" class='imgListimg' ><br/>
						<form id='imageform-{$id}' method='post'  enctype='multipart/form-data' action='/aqary/admin/include/ajaxImageUpload.php' style='clear:both'>
						<input type='hidden' name='photoid' value='{$id}' class='photoid'>
						<input type='text'	value='{$_POST['photoscaption']}'
						originalvalue='{$_POST['photoscaption']}' photoid='{$id}' id='photo-{$id}' name='photodescription' class='imagedescription' >
						</form>
						<div id='imageloadstatus-{$id}' style='display:none'><img src='/aqary/admin/images/loader.gif' alt='Uploading....'/></div>
					</div>
				";
			}


	       }
	       else
	       {
	        echo '<span class="imgList">You have exceeded the size limit! so moving unsuccessful! </span>';
            }

	       }
		   else
		   {
			echo '<span class="imgList">You have exceeded the size limit!</span>';
          
	       }
       
          }
          else
         { 
	     	echo '<span class="imgList">Unknown extension!</span>';
           
	     }
           
     }
}

?>