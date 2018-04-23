<?php

/** Variable definitions - You can change this stuff */


$albumName = "";            // Name of this photo album
$thumbSize = "150";         // Size of thumbnails (max width/height) in pixels
$password  = "";            // Password for protected files
$thumbDir  = "./thumbs/"; 	// Directory where to put thumbnails */
$tagPattern= "<[a-zA-Z0-9_]{1,}>";  // Identify security tags

/*
* Don't mess with anything below this line - unless of course you know what you're doing.
*/

/*
* Title  : Roddzilla Gallery
* File   : index.php
* Created: 06/05/2005
* Updated: 10/14/2008
* Author : Ryan Rodd
* Info   : Single file application which indexes
*          and processes a directory of raw jpeg
*		   files. Lets users add meta to images.
* Version: 2.0
* Copyright: Ryan Rodd (c). 2005
*/

session_start();
ini_set("memory_limit","64M");
header ("Cache-control: private");

if(isset($_GET['uncache'])) {
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
}

$imgArr    = array();
$fileArr   = array();
$inform    = array();
$info      = &$inform;
$tagPattern= "<[a-zA-Z0-9_]{1,}>";  // Identify security tags
$image_ext = array("jpg","jpeg");   // Image types to accomodate */


if($albumName == "") $albumName = "Gallery: ".ucwords(array_pop(explode("/",substr($_SERVER['REQUEST_URI'],0,-1))));


/*
* Func: iptcMaketag
* Desc: writing data to image iptc data
*/
function iptcMaketag($rec,$dat,$val){
	$len = strlen($val);
	if ($len < 0x8000)
		return chr(0x1c).chr($rec).
		chr($dat).chr($len >> 8).
		chr($len & 0xff).$val;
	else
		return chr(0x1c).chr($rec).
		chr($dat).chr(0x80).chr(0x04).
		chr(($len >> 24) & 0xff).
		chr(($len >> 16) & 0xff).
		chr(($len >> 8 ) & 0xff).
		chr(($len ) & 0xff).$val;
}

/*
* Func: cutString
* Desc: shorten a string and add dots
*/
function cutString($str, $len, $dots = "...") {
    if (strlen($str) > $len) {
        $dotlen = strlen($dots);
        $str = substr_replace($str, $dots, $len-$dotlen);
    }
    return $str;
}

/*
* Func: redirect
* Desc: change page to the specified
*/
function redirect($place,$time = 0){
	print '<meta http-equiv="refresh"
		   content="'.$time.';url='.$place.'">';
}

/*
* Func: createThumb
* Desc: make a thumbnail from an images
*/
function createThumb($file,$new_size,$quality=85) {
	global $thumbDir;
	$simg = imagecreatefromjpeg($file);
    $old_x   = imageSX($simg);
    $old_y   = imageSY($simg);

    if ($old_x > $old_y) {
        $center = ($old_x/2);
        $src_x  = $center - ($old_y/2);
        $src_y  = 0;
        $src_size = $old_y;
    }
    if ($old_x < $old_y) {
        $center = ($old_y/2);
        $src_y  = $center - ($old_x/2);
        $src_x  = 0;
        $src_size = $old_x;
    }
    if ($old_x == $old_y) {
        $src_x = 0;
        $src_y = 0;
        $src_size = $old_y;
    }
    $dst_img = imagecreatetruecolor($new_size,$new_size);
    imagecopyresized ($dst_img, $simg, 0, 0, $src_x,
	$src_y,	$new_size, $new_size, $src_size, $src_size );
    imagejpeg($dst_img,$thumbDir.$file);
    imagedestroy($dst_img);
    imagedestroy($simg);
    if(is_file($thumbDir.$file)) {
    	chmod($thumbDir.$file, 0755);
		return true;
	}
    else return false;
}

/*
* Func: rotateImage
* Desc: turn an image around
*/
function rotateImage($file,$angle) {
	$src_img = imagecreatefromjpeg($file);
    $dst_img = imagerotate($src_img, $angle, 0);
    imagejpeg($dst_img,$file,95);
    imagedestroy($dst_img);
    imagedestroy($src_img);
    // Rotate the thumbnail too.
    $tname = $thumbDir.$file;
	$tsrc_img = imagecreatefromjpeg($tname);
    $tdst_img = imagerotate($tsrc_img, $angle, 0);
    imagejpeg($tdst_img,$tname,95);
    imagedestroy($tdst_img);
    imagedestroy($tsrc_img);
}

/*
* Func: scanDir
* Desc: supplement to non-PHP 5 systems
*/
if(!function_exists("scandir")) {
	function scandir($dir, $sortorder = 0) {
        if(is_dir($dir) && $dirlist = @opendir($dir)) {
            while(($file = readdir($dirlist)) !== false) {
                $files[] = $file;
            }
            closedir($dirlist);
            ($sortorder == 0) ? asort($files) : rsort($files);
            return $files;
        } else return false;
    }
}

/*
* Func: scanImg
* Desc: same as scan dir but return only images
*/
function scanImg($dir, $sortorder = 0) {
	global $image_ext;
    $cdir = scandir($dir,$sortorder);
	foreach($cdir as $file) {
		in_array(strtolower(end(explode(".", $file))),$image_ext)?
		$imgArr[] = $file : $fileArr[] = $file;
	}
	return $imgArr;
}

/*
* Func: iptc_maketag
* Desc: format tag meta to Base64
*/
function iptc_maketag($rec,$dat,$val){
	$len = strlen($val);
	if ($len < 0x8000)
		return chr(0x1c).chr($rec).chr($dat).
		chr($len >> 8).
		chr($len & 0xff).
		$val;
	else
		return chr(0x1c).chr($rec).chr($dat).
		chr(0x80).chr(0x04).
		chr(($len >> 24) & 0xff).
		chr(($len >> 16) & 0xff).
		chr(($len >> 8 ) & 0xff).
		chr(($len ) & 0xff).
		$val;
}



/** End Function set */



if($_GET['act']=="makethumb") {
	if(!is_file($thumbDir.$_GET['file'])) {
		if(createThumb($_GET['file'],$thumbSize,100)) echo 1;
		else echo 0;
	}
	else
		echo 0;

} elseif($_GET['act']=="embed") {

   if($_POST['password']!=$password)
    	die("Password incorrect. Please go back and try again.");

	$iptc_old["2#005"][0] = $_POST['title']."";
	$iptc_old["2#120"][0] = $_POST['desc']."";
	$iptc_old["2#080"][0] = $_POST['author']."";

	foreach (array_keys($iptc_old) as $s){
		$tag = str_replace("2#", "", $s);
		$iptc_new .= iptcMakeTag(2, $tag, $iptc_old[$s][0]);
	}

	$content = iptcembed($iptc_new, $_GET['file'],0);
	$fp = fopen($_GET['file'],"w");
	fwrite($fp, $content);
	fclose($fp);

	$content = iptcembed($iptc_new, $thumbDir.$_GET['file'],0);
	$fp = fopen($thumbDir.$_GET['file'],"w");
	fwrite($fp, $content);
	fclose($fp);
	$s = $_GET['r']+1;
	print "<html><head><script type=\"text/javascript\">
		window.opener.location.href='?v=g&uncache&r=".$s."#".reset(explode(".",$_GET['file']))."';</script></head>
		<body>Please wait...</body></html>";
	redirect($_SERVER['HTTP_REFERER']);

} elseif($_GET['act']=="image") {
	header("Content-Type: image/jpeg");
	if(isset($_GET['download']))
		header("Content-Disposition: attachment; filename=".$_GET['file']);
	$rec = imagecreatefromjpeg($_GET['file']);

	if(isset($_GET['maxsize'])) {
		$size = getimagesize($_GET['file']);

		if($size[0] > $size[1]) $divisor = $size[0] / $_GET['maxsize'];
		else $divisor = $size[1] / $_GET['maxsize'];

		$new_width  = round($size[0] / $divisor);
		$new_height = round($size[1] / $divisor);
     	// load original image
		$image_small = imagecreatetruecolor($new_width, $new_height);
		     // create new image
		imagecopyresampled($image_small, $rec, 0,0, 0,0, $new_width,$new_height, $size[0],$size[1]);
	}

    imagejpeg($image_small,"",100);
    imagedestroy($image_small);
    imagedestroy($rec);

} elseif($_GET['act']=="auth") {
	if($_POST['tag'])
		$_SESSION['gall_perm'] .= $_POST['tag'];
	if(isset($_GET['clear']))
		$_SESSION['gall_perm'] = "";
	redirect($_SERVER['HTTP_REFERER']);
}

if(isset($_GET['act'])) die;
?>

<html>
<head>
<style type="text/css">
body {
  font-family: tahoma;
  font-size: 12px;
  margin: 0;
  <?php echo ($_GET['v']=="meta")?"padding:0px;":"padding:10px;"; ?>

}
.cleared {
  margin: 0;
  padding-bottom: 4px;
  vertical-align: middle;
  border-collapse: collapse;
  font-size: 12px;
}
p,h1,form {
  margin:0;
  padding:0;
}
.area {
  border:1px solid #D4D0C7;
  font-size:11px;
  font-family:tahoma;
  padding:2px;
  margin:0;
}
img {
  vertical-align:middle;
  padding-bottom: 2px;
}
</style>

<script type="text/javascript">
var thumbCount = 0;
var processing = 0;
var processByt = 0;
var metaDebug  = 0;

function findPos(obj) {
	var curleft = curtop = 0;
	if (obj.offsetParent) {
		curleft = obj.offsetLeft
		curtop = obj.offsetTop
		while (obj = obj.offsetParent) {
			curleft += obj.offsetLeft
			curtop += obj.offsetTop
		}
	}
	return [curleft,curtop];
}

function makeRequestObject() {
	if (window.XMLHttpRequest) {
		return new XMLHttpRequest();
	}
	else if(window.ActiveXObject) {
    	return new ActiveXObject("Microsoft.XMLHTTP");
	}
}

function sendGetReq(reqOb,reqURL) {
	if (reqOb.readyState == 4 || reqOb.readyState == 0) {
		reqOb.open("GET", reqURL, true);
		reqOb.onreadystatechange = handlerFunc;
		reqOb.send(null);
	}
}

function makeThumb(file) {
	imgObj = makeRequestObject();
	imgReq = "?act=makethumb&file="+file;
	handlerFunc = function() { handleMakeThumb(); }
	sendGetReq(imgObj,imgReq);
	processing = 1;
}

function handleMakeThumb() {
	if (imgObj.readyState == 4) {
		processing = 0;
		return true;
	}
}

function generateThumbs(arr) {
	var thumbIndex = 0;      // Scope:function
	thumbCount = arr.length; // Scope:global
	timer = setInterval(function() {
		if(thumbCount < 1) {
			clearInterval(timer);
			location.href = '?v=g';
		}
		if(processing == 0) {
			document.getElementById('imagename').innerHTML = arr[thumbCount];
			document.getElementById('instImg').src = "thumbs/"+arr[thumbCount];
			document.getElementById('icount').innerHTML = thumbCount;
			document.getElementById('imageperc').innerHTML =
				Math.round(((arr.length-thumbCount)/arr.length)*100);
			donePixels = Math.round(((arr.length-thumbCount)/arr.length)*400);
			ndonePixels = 400-donePixels;
			try {
				document.getElementById('progressfull').width = donePixels+'px';
				document.getElementById('progressempty').width = ndonePixels+'px';
				makeThumb(arr[thumbCount-1]);
			} catch(e) {}
			thumbCount--;
		}
	},50);
}

function placeMeta() {
	if(metaDebug==1) {
		alert('test');
		metaDebug=0;
	}
	for(var i=0; i<document.images.length; i++) {
		thisImg = document.images[i];
		thisDiv = document.getElementById('info_'+thisImg.id);
		thisDiv.style.top = (findPos(thisImg)[1]) + thisImg.height + 20;
		thisDiv.style.left= (findPos(thisImg)[0]) + 15;
		thisDiv.width     = thisImg.width;
	}
}

function popUp(url,X,Y)
{
    day = new Date();
    //id = day.getTime(); // opens new window each time
    id = 1; // opens diff. pages in same window.
	eval("page_pop" + id + " = window.open(url, '" + id + "', 'toolbar=0,scrollbars=0,location=0,statusbar=1,"
		+"menubar=0,resizable=0,width="+X+",height="+Y+",left = 400,top = 300');");
}

window.onresize = function() {
	placeMeta();
}
</script>
</head>

<body>
<h1><?php if($_GET['v']!="meta") echo $albumName ?></h1>
<!-- Default Page -->
<?php
if($_GET['v']=="list") {
	$currDir = scandir("./");
	foreach($currDir as $file) {
		in_array(strtolower(end(explode(".", $file))),$image_ext)?
		$imgArr[] = $file : $fileArr[] = $file;
	}
?>
<?php if(!is_dir($thumbDir)) { print '[<a href="?v=mgm">Create Gallery</a>]'; }
	else { print '[<a href="?v=g">Gallery Mode</a>] [List View]'; } ?>
<p><br /></p>
<table class="cleared" style="width:100%">
<tr><td colspan="3" class="cleared"><p style="font-size:14px;"><b>Image Files:</b></p></td></tr>
<tr><td class="cleared" style="width:25%">Name</td>
<td class="cleared" style="width:100px;">Size</td>
<td class="cleared">Description</td></tr>
<tr><td colspan="3" class="cleared"><hr /></td></tr>
<?php
foreach($imgArr as $img) {
	$size = getimagesize($img,$info);
    $iptc = iptcparse($info["APP13"]);
    if($iptc["2#120"][0]=="") {
		try {
			if(is_file($thumbDir.$img)){
				$size = getimagesize($thumbDir.$img,$info);
	    		$iptc = iptcparse($info["APP13"]);
    		}
		} catch(Exception $err) { }
	}
	ereg($tagPattern,$iptc["2#120"][0],$arr);
	$iptc["2#120"][0] = str_replace("\\","",$iptc["2#120"][0]);
   	$iptc["2#120"][0] = strip_tags($iptc["2#120"][0]);
	if((isset($arr) && strstr($_SESSION['gall_perm'],$arr[0])) xor !isset($arr)) {
		print '<tr><td class="cleared">
		<a href="'.$img.'">'.$img.'</a></td>
		<td class="cleared">'.round(filesize($img)/pow(1024,1)).' KB</td>
		<td class="cleared">'.$iptc["2#120"][0].'</td></tr>';
	}
}
?>
</table>
<p><br /></p>
<table class="cleared" style="width:100%">
<tr><td colspan="3" class="cleared"><p style="font-size:14px;"><b>Other Files:</b></p></td></tr>
<tr><td class="cleared" style="width:25%">Name</td>
<td class="cleared" style="width:100px;">Size</td>
<td class="cleared">Description</td></tr>
<tr><td colspan="3" class="cleared"><hr /></td></tr>
<?php
foreach($fileArr as $file)
	if(is_file($file) && $file!="index.php")
	print '<tr><td class="cleared"><a href="'.$file.'">'.$file.'</a></td>
	<td class="cleared">'.round(filesize($file)/pow(1024,1)).' KB</td>
	<td class="cleared"></td></tr>';
?>
</table>
<?php } elseif($_GET['v']=="mgm") { ?>
<p style="width:500px;"><br />The installer is going to attempt to make thumbnails of your photos. In
order for this utility to work properly, please ensure the following:<br /><br />
<b>1.</b> Pop-ups are allowed for this domain.<br />
<b>2.</b> Files are readable and writable.<br />
<b>3.</b> You are using a compatible browser*.<br /><br />
<a href="?v=mg" style="font-weight:bold;">Continue</a><br /><br />
*Internet Explorer (On Windows), Firefox, Safari or Netscape.
</p>

<?php } elseif($_GET['v']=="mg") {
	/** Make directory for thumbs. */
	if(!is_dir($thumbDir)) mkdir($thumbDir);
	/** */
	$n = 0;
	$currImg = scanImg("./"); // Get list of images in current directory
	print '<script type="text/javascript">
	var imgArr = new Array();';
	foreach($currImg as $img) {
		print 'imgArr['.$n.'] = "'.$img.'";';
		$n++;
	}
	print 'generateThumbs(imgArr);';
	print '</script>';
?>

<p style="width:500px;"><br />Creating Thumbnails:<br /><br />
<table style="background-color:#FFF;width:100%;" class="cleared">
 <tr>
   <td class="cleared" style="border-top:1px solid #808080;padding:5px;vertical-align:top;">
     Now updating your thumbnail gallery with the new images...<br /><br />
	 Creating (<span id="icount"></span>) more thumbnails, please wait... (at <span id="imageperc"></span>%)
	 <br /><br />
     <table style="border:1px solid #808080;width:400px;"><tr>
     <td class="cleared" style="background-color:#0000FF;" id="progressfull">&nbsp;</td>
     <td class="cleared" id="progressempty">&nbsp;</td></tr>
	 </table>Processing: <span id="imagename"></span><br /><br /><br />
   </td>
   <td class="cleared" id="myImage" style="border-top:1px solid #808080;padding:5px; width:1%;vertical-align:middle;">
   <img id="instImg" style="width:100px;" /></td>
 </tr>
</table>

<div id="instOutput"></div>

<?php }elseif($_GET['v']=="g" || !$_GET['v'] || $_GET['v']=="") {
	if(is_dir($thumbDir)) {
	$thumbImg = scanImg($thumbDir);
	$origImg  = scanImg("./");
?>
<p>[Gallery Mode] [<a href="?v=list">List View</a>] <?php if(count($origImg) > count($thumbImg)){print 'New prints exist! [<a href="?v=mg">Update Gallery</a>]';} ?></p>
<p><br /></p><p style="font-size:14px;"><b>Image Files:</b></p>
<?php
	foreach($thumbImg as $img) {
		$size = getimagesize($thumbDir.$img,$info);
    	$iptc = iptcparse($info["APP13"]);
    	$name = reset(explode(".",$img));
    	$t = ($iptc["2#005"][0]!="")?$iptc["2#005"][0]:cutString($name,10);
    	ereg($tagPattern,$iptc["2#120"][0],$arr);
    	if((isset($arr) && strstr($_SESSION['gall_perm'],$arr[0])) xor !isset($arr)) { // checks for permission tag
			print '<div style="display:inline" id="d_'.$name.'">
			       <a name="'.$name.'" href="'.$img.'"><img id="'.$name.'" src="'.$thumbDir.$img.'"
				   style="border:15px solid #FFF;border-bottom:30px solid #FFF;width:'.$thumbSize.'px;height:'.$thumbSize.'px;" /></a></div>
				   <div style="position:absolute; top: -30px; left:0px;font-size:11px;" id="info_'.$name.'">
				   <table class="cleared" style="width:'.$thumbSize.'px;"><tr><td class="cleared" style="font-size:11px;">'.$t.'</td>
				   <td class="cleared" style="text-align:right;font-size:11px;"><a href="#'.$name.'"
				   onclick="popUp(\'?v=meta&amp;file='.$img.'&amp;r='.$_GET['r'].'\',225,323);">Info</a></td></tr></table></div>';
			echo "\n";
		}
		unset($arr);
	}
	}else{
		print '[<a href="?v=mgm">Create Gallery</a>]';
	}
?>
<script type="text/javascript">
placeMeta();
</script>


<div style="position:absolute;top: 10px; right:10px; padding:10px;background-color:#F5F5F5;border:1px solid #EEE;">
<?php if($_SESSION['gall_perm']!="") { ?><a href="?act=auth&clear">Hide Protected Files</a><?php } else {?>
<form method="post" action="?act=auth" id="authForm">
<input type="text" name="tag" style="border:1px solid #DDD;" /> &nbsp;
<input type="button" style="background-color:#FFF;border:1px solid #DDD;" value="filter"
onclick="document.getElementById('authForm').submit();">
</form>
<?php } ?>
</div>

<?php } elseif($_GET['v'] == "meta") {
	$size = getimagesize($thumbDir.$_GET['file'],$info);
	list($bwidth, $bheight, $btype, $battr) = getimagesize($_GET['file']);
	$megapixels = round(($bwidth*$bheight)/1000000,1);
    $iptc = iptcparse($info["APP13"]);
	$iptc["2#120"][0] = str_replace("\\,","",$iptc["2#120"][0]);

?>
<form method="post" action="?act=embed&amp;file=<?php echo $_GET['file'] ?>">
<table class="cleared" style="background-color:#D4D0C7;width:100%;"><tr>
<td style="padding:4px;border-bottom:1px solid #808080;">
<input type="text" class="area" name="title" style="background-color:#D4D0C7;width:100%;" value="<?php echo $iptc["2#005"][0] ?>" />
</td></tr></table>
<table class="cleared"><tr><td class="cleared" style="vertical-align:top;font-family:tahoma;">
<p style="padding:4px;"><img src="<?php echo $thumbDir.$_GET['file'] ?>" style="width:130px;" /></p></td>
<td style="vertical-align:top; padding: 4px;font-family:tahoma;"><b>MegaPixels:</b> <br /><?php echo $megapixels ?>
<br/><br /><b>Rotate (ccw):</b><br />
<a href="?action=rotate&amp;file=<?php echo $_GET['file'] ?>&amp;degrees=90">90</a>
<a href="?action=rotate&amp;file=<?php echo $_GET['file'] ?>&amp;degrees=180">180</a>
<a href="?action=rotate&amp;file=<?php echo $_GET['file'] ?>&amp;degrees=270">270</a>

</td></tr></table><table class="cleared" style="width:100%"><tr><td style="padding:5px;font-family:tahoma;">
<p><b>Details:</b><br /><textarea name="desc" class="area" style="height:50px;width:100%;">
<?php echo $iptc["2#120"][0] ?></textarea></p>
<p style="padding-top:3px;"><b>Photographer:</b><br /><input type="text" class="area" name="author" style="width:100%;" value="<?php echo $iptc["2#080"][0] ?>" /></p>
<p style="padding-top:5px;" id="changep"><a href="#" onclick="changeSubmit();">Change</a></p>
</td></tr></table></form>
<script type="text/javascript">
 function changeSubmit() {
    document.getElementById('changep').innerHTML='<b>Password:</b><br /><input type="password" name="password" class="area" /> <input type="submit" value="Edit" class="area" />';
 }
</script>
<?php } ?>
</body>
</html>
