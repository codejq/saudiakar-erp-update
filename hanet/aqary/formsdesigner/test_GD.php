ï»؟<?php
    // Set the content-type
    header("Content-type: image/png");
    
    // Create the image
    //$im = @imagecreatefromgif('GD/payment.jpg');
    $im = @imagecreatefromjpeg('GD/payment.jpg');
    // Create some colors
	$green = imagecolorallocate($im, 34, 144, 72);
    $black = imagecolorallocate($im, 0, 0, 0);
    $blue  = imagecolorallocate($im, 0, 0, 255);
    $white = imagecolorallocate($im,255,255,255);
    
    // Replace by your own font full path and name
    $path  = substr($_SERVER['SCRIPT_FILENAME'], 0, 
                    strrpos($_SERVER['SCRIPT_FILENAME'], '/'));
    $font  = $path.'/GD/ae_AlHor.ttf';
    $font  = $path.'/GD/arial.ttf';
    // UTF-8 charset
    /*
	$text = 'بسم الله الرحمن الرحيم this is another text ';
    imagefill($im,0,0,$white);
    imagettftext($im, 20, 0, 10, 50, $blue, $font, 'UTF-8:');
    imagettftext($im, 20, 0, 200, 50, $black, $font, $text);
	*/
    include('Arabic.php');
    $Arabic = new Arabic('ArGlyphs');

    $text = 'مبلغ 2500 ريال الفان وخمسمائة ريال فقط لا غير  يتم عمل سطر جديد بعد خمسين حرف  تم  اللهم لك الحمد ولك الشكر يا كريم
مبلغ 2500 ريال الفان وخمسمائة ريال فقط لا غير  يتم عمل سطر جديد بعد خمسين حرف  تم  اللهم لك الحمد ولك الشكر يا كريم
مبلغ 2500 ريال الفان وخمسمائة ريال فقط لا غير  يتم عمل سطر جديد بعد خمسين حرف  تم  اللهم لك الحمد ولك الشكر يا كريم
مبلغ 2500 ريال الفان وخمسمائة ريال فقط لا غير  يتم عمل سطر جديد بعد خمسين حرف  تم  اللهم لك الحمد ولك الشكر يا كريم
مبلغ 2500 ريال الفان وخمسمائة ريال فقط لا غير  يتم عمل سطر جديد بعد خمسين حرف  تم  اللهم لك الحمد ولك الشكر يا كريم
مبلغ 2500 ريال الفان وخمسمائة ريال فقط لا غير  يتم عمل سطر جديد بعد خمسين حرف  تم  اللهم لك الحمد ولك الشكر يا كريم';
	//$text ="this is the most imprtant thing in my life please help me please and dont be silly ";
    $text = $Arabic->utf8Glyphs($text,500);

	imagefill($im,0,0,$white);
    //imagettftext($im, 20, 0, 10, 100, $blue, $font, 'ArGlyphs:');
    //imagettftextalign($im, 12, 0, 569, 237, $green, $font, $text , 'R');
	imageTextWrapped($im, 560, 236, 330, $font, $green, $text, 12, "r") ;
    
    // Using imagepng() results in clearer text compared with imagejpeg()
    imagepng($im);
    imagedestroy($im);
	
	function imagettftextalign($image, $size, $angle, $x, $y, $color, $font, $text, $alignment='L') 
{ 

   //check width of the text 
   $bbox = imagettfbbox ($size, $angle, $font, $text); 
   $textWidth = $bbox[2] - $bbox[0]; 
   switch ($alignment) { 
       case "R": 
           $x -= $textWidth; 
           break; 
       case "C": 
           $x -= $textWidth / 2; 
           break; 
   } 

   //write text 
   imagettftext ($image, $size, $angle, $x, $y, $color, $font, $text); 

} 

function imageTextWrapped($img, $x, $y, $width, $font, $color, $text, $textSize, $align="l") {
    //Recalculate X and Y to have the proper top/left coordinates instead of TTF base-point
    $y += $textSize;
    $dimensions = imagettfbbox($textSize, 0, $font, " "); //use a custom string to get a fixed height.
    $x -= $dimensions[4]-$dimensions[0] ;
	if($align=="r")
	{
		$y -= $textSize;
		$x -= $dimensions[4]-$dimensions[0] + $width;
	}
    $text = str_replace ("\r", '', $text); //Remove windows line-breaks
    $srcLines = explode ("\n", $text); //Split text into "lines"
    $dstLines = Array(); // The destination lines array.
    foreach ($srcLines as $currentL) {
        $line = '';
        $words = explode (" ", $currentL); //Split line into words.
		$words = array_reverse ($words);
        foreach ($words as $word) {
            $dimensions = imagettfbbox($textSize, 0, $font, $line.$word);
            $lineWidth = $dimensions[4] - $dimensions[0]; // get the length of this line, if the word is to be included
            if ($lineWidth > $width && !empty($line) ) { // check if it is too big if the word was added, if so, then move on.
			    $words1 = explode (" ", $line); //Split line into words.
				$words1 = array_reverse ($words1);
				$line =  implode (' ', $words1 );
                $dstLines[] = ' '.trim($line); //Add the line like it was without spaces.
                $line = '';
            }
            $line .= $word.' ';
        }
		$words1 = explode (" ", $line); //Split line into words.
		$words1 = array_reverse ($words1);
		$line =  implode (' ', $words1 );
        $dstLines[] =  ' '.trim($line); //Add the line when the line ends.
    }
    //Calculate lineheight by common characters.
    $dimensions = imagettfbbox($textSize, 0, $font, "MXQJPmxqjp123"); //use a custom string to get a fixed height.
    $lineHeight = $dimensions[1] - $dimensions[5]; // get the heightof this line

    $align = strtolower(substr($align,0,1)); //Takes the first letter and converts to lower string. Support for Left, left and l etc.
    foreach ($dstLines as $nr => $line) {
        if ($align != "l") {
            $dimensions = imagettfbbox($textSize, 0, $font, $line);
            $lineWidth = $dimensions[4] - $dimensions[0]; // get the length of this line
            if ($align == "r") { //If the align is Right
                $locX = $x + $width - $lineWidth;
            } else { //If the align is Center
                $locX = $x + ($width/2) - ($lineWidth/2);
            }
        } else { //if the align is Left
            $locX = $x;
        }
        $locY = $y + ($nr * $lineHeight);
        //Print the line.
        imagettftext($img, $textSize, 0, $locX, $locY, $color, $font, $line);
    }        
}
	
	
?>
