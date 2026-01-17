<?php
/**
 * Final fix for editemara.hnt - replace entire broken display section
 */

$file = 'Y:\admin\include\emara\editemara.hnt';
$content = file_get_contents($file);

// Backup
copy($file, $file . '.backup');

// Find and replace the entire broken loop section (lines 1429-1467)
// Start marker: for($i=0;$i<15;$i++){
// End marker: } right before the second for loop

$old_section = <<<'OLD'
for($i=0;$i<15;$i++){
if($file_path = upload_path('properties.images', $emaraid."_".$i."_.jpg")){echo"<tr><td ><a href=pic/".$emaraid."_".$i."_.jpg target='_blank'  >صورة </a>
<img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.jpg'; this.style.display='none';return false;} \"> <br> "; }
if($file_path = upload_path('properties.images', $emaraid."_".$i."_.gif")){echo"<tr><td ><a href=pic/".$emaraid."_".$i."_.gif target=_blank>صورة </a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.gif'; this.style.display='none';return false;} \"> <br> "; }
if($file_path = upload_path('properties.images', $emaraid."_".$i."_.png")){echo"<tr><td ><a href=pic/".$emaraid."_".$i."_.png target=_blank>صورة </a><img class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.png'; this.style.display='none';return false;} \"><br> "; }
if($file_path = upload_path('properties.images', $emaraid."_".$i."_.bmp")){echo"<tr><td ><a href=pic/".$emaraid."_".$i."_.bmp target=_blank>صورة </a><img class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.bmp'; this.style.display='none';return false;} \"><br> "; }

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.pdf")){echo"<tr><td align=right ><a href=pic/".$emaraid."_".$i."_.pdf target=_blank >ملف اكروبات pdf </a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.pdf'; this.style.display='none';return false;} \"><br>"; }
if($file_path = upload_path('properties.images', $emaraid."_".$i."_.tif")){echo"<tr><td  align=right><a href=pic/".$emaraid."_".$i."_.tif target=_blank >ملف tif</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.tif'; this.style.display='none';return false;} \"><br>"; }
if($file_path = upload_path('properties.images', $emaraid."_".$i."_.doc")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.doc target=_blank >ملف وورد</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.doc'; this.style.display='none';return false;} \"><br>"; }

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.ppt")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.ppt target=_blank >ملف باوربوينت</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.ppt'; this.style.display='none';return false;} \"><br>"; }

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.pps")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.pps target=_blank >ملف باور بوينت</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.pps'; this.style.display='none';return false;} \"><br>"; }

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.avi")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.avi target=_blank >ملف فيديو</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.avi'; this.style.display='none';return false;} \"><br>";}

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.mepg")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.mepg target=_blank >ملف فيديو</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.mepg'; this.style.display='none';return false;} \"><br>";}

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.wmv")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.wmv target=_blank >ملف فيديو</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.avi'; this.style.display='none';return false;} \"><br>";}

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.swf")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.swf target=_blank >ملف فلاش</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.swf'; this.style.display='none';return false;} \"><br>";}

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.xls")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.xls target=_blank >ملف اكسل </a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.xls'; this.style.display='none';return false;} \"><br>";}

if($file_path = upload_path('properties.images', $emaraid."_".$i."_.mdi")){echo"<tr><td align=right><a href=pic/".$emaraid."_".$i."_.mdi target=_blank >ملف mdi </a><img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/".$emaraid."_".$i."_.mdi'; this.style.display='none';return false;} \"> <br>";}




}

for($i=0;$i<15;$i++){

if(file_exists(upload_path('properties.images', "go".$emaraid."_".$i."_.jpg")){echo"<tr><td><a href=pic/go".$emaraid."_".$i."_.jpg   target=_blank>كروكي </a><img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile=emara/pic/go".$emaraid."_".$i."_.jpg'; this.style.display='none';return false;} \"> <br> "; }



}
OLD;

$new_section = <<<'NEW'
for($i=0;$i<15;$i++){
// JPG
$file_path = upload_path('properties.images', $emaraid."_".$i."_.jpg");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.jpg");
if(file_exists($file_path)){echo"<tr><td ><a href={$file_url} target='_blank'  >صورة </a>
<img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"> <br> "; }

// GIF
$file_path = upload_path('properties.images', $emaraid."_".$i."_.gif");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.gif");
if(file_exists($file_path)){echo"<tr><td ><a href={$file_url} target=_blank>صورة </a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"> <br> "; }

// PNG
$file_path = upload_path('properties.images', $emaraid."_".$i."_.png");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.png");
if(file_exists($file_path)){echo"<tr><td ><a href={$file_url} target=_blank>صورة </a><img class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br> "; }

// BMP
$file_path = upload_path('properties.images', $emaraid."_".$i."_.bmp");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.bmp");
if(file_exists($file_path)){echo"<tr><td ><a href={$file_url} target=_blank>صورة </a><img class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br> "; }

// PDF
$file_path = upload_path('properties.images', $emaraid."_".$i."_.pdf");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.pdf");
if(file_exists($file_path)){echo"<tr><td align=right ><a href={$file_url} target=_blank >ملف اكروبات pdf </a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>"; }

// TIF
$file_path = upload_path('properties.images', $emaraid."_".$i."_.tif");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.tif");
if(file_exists($file_path)){echo"<tr><td  align=right><a href={$file_url} target=_blank >ملف tif</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>"; }

// DOC
$file_path = upload_path('properties.images', $emaraid."_".$i."_.doc");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.doc");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف وورد</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>"; }

// PPT
$file_path = upload_path('properties.images', $emaraid."_".$i."_.ppt");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.ppt");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف باوربوينت</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>"; }

// PPS
$file_path = upload_path('properties.images', $emaraid."_".$i."_.pps");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.pps");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف باور بوينت</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>"; }

// AVI
$file_path = upload_path('properties.images', $emaraid."_".$i."_.avi");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.avi");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف فيديو</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>";}

// MEPG
$file_path = upload_path('properties.images', $emaraid."_".$i."_.mepg");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.mepg");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف فيديو</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>";}

// WMV
$file_path = upload_path('properties.images', $emaraid."_".$i."_.wmv");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.wmv");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف فيديو</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>";}

// SWF
$file_path = upload_path('properties.images', $emaraid."_".$i."_.swf");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.swf");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف فلاش</a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>";}

// XLS
$file_path = upload_path('properties.images', $emaraid."_".$i."_.xls");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.xls");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف اكسل </a> <img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"><br>";}

// MDI
$file_path = upload_path('properties.images', $emaraid."_".$i."_.mdi");
$file_url = upload_url('properties.images', $emaraid."_".$i."_.mdi");
if(file_exists($file_path)){echo"<tr><td align=right><a href={$file_url} target=_blank >ملف mdi </a><img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"> <br>";}

}

for($i=0;$i<15;$i++){
// Blueprint/Diagram - JPG
$file_path = upload_path('properties.images', "go".$emaraid."_".$i."_.jpg");
$file_url = upload_url('properties.images', "go".$emaraid."_".$i."_.jpg");
if(file_exists($file_path)){echo"<tr><td><a href={$file_url}   target=_blank>كروكي </a><img  class='policy_{$sc_id}_deleetes' src=\"../../images/delete.gif\" border=0 onclick=\"if(confirm('هل ترغب بالفعل فى حذف هذا الملف ')){gopicv.location.href='../mypreview.hnt?delef=1&sfile={$file_path}'; this.style.display='none';return false;} \"> <br> "; }

}
NEW;

$content = str_replace($old_section, $new_section, $content);

file_put_contents($file, $content);

echo "✓ Fixed editemara.hnt\n";
echo "Backup: editemara.hnt.backup\n";
