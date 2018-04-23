<?php
/*
* Title   : Roddzilla Gallery
* File    : index.php
* Created : 6/05/2005
* Updated : 6/25/2009
* Author  : Ryan Rodd
* Info    : Single file application which indexes
*           and processes a directory of raw jpeg
*		    files. Lets users add meta to images.
* Version : 3.0
*/

/*
* Settings that can be changed
* WARNING : Don't change these values if you've already run the installation
*/

$albumName = "ryanrodd.com - Gallery";		// Defaults to directory name
$thumbSize = 750;				// Size of thumbnail max w or h
$thumbSqr  = 100;				// Size of square thumbnails
$thumbQlt  = 90;				// Thumbnail quality (0-100) 85-90 is suggested
$thumbPfx  = "th_";				// Filename prefix for regular thumbs
$thumbSfx  = "thsq_";  			// Filename prefix for square thumbs
$thumbDir  = "thumbs"; 	        // Directory where to put thumbnails
$password  = "";				// Unlock protected files - deprecated

$tagPattern = "<[a-zA-Z0-9_]{1,}>";            // Identify security tags - deprecated
$imageExt   = array("jpg","jpeg","png","gif"); // Supported image types
$videoExt	= array("avi","mkv","qt","mpg");   // Supported video types

/*
* End of changeable settings.
* Don't change anything below unless you know
* what you're doing.
*/

// Keep the session
session_start();
header ("Cache-control: private");

// Up the limit for image processing
ini_set("memory_limit","256M");
ini_set("max_execution_time", "3000");
set_time_limit(300);

// Default album name
if($albumName == "") {
	$scriptarr = explode("/",$_SERVER['SCRIPT_NAME']);
	$albumName = ucwords($scriptarr[sizeof($scriptarr)-2])." Gallery";
}

/*
* Func : iptcMakeTag
* Desc : For writing data to image iptc
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
* Func : cutString
* Desc : A favorite of mine
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
* Desc: Another favorite
*/
function redirect($place,$time = 0){
	print '<meta http-equiv="refresh"
		   content="'.$time.';url='.$place.'">';
}

/*
* Func: makeThumb
* Desc: Update, sexier createThumb function
*/
function makeThumb($img,$dest,$maxsize,$sqr=0,$qual=90) {
	if(!$info=getimagesize($img))
	    return false;

	// Import the file into gd
	switch ($info['mime']) {
		case 'image/jpeg':$src = imagecreatefromjpeg($img);break;
		case 'image/gif': $src = imagecreatefromgif($img); break;
		case 'image/png': $src = imagecreatefrompng($img); break;
		default: return false;
	}
	// Do the resizing - oh yes, sexy code
	$thx = $thy = 0;
	$thw = $thh = $maxsize;
	$aspect = $info[0]/$info[1];

	// Large thumbnails
	if(!$sqr) {
	   	if(max($info[0],$info[1]) > $maxsize) {
		   	if($aspect > 1) $thh = round($maxsize/$aspect);
		   	elseif($aspect < 1) $thw = round($maxsize*$aspect);
	    } else {
	        $thw = $info[0];
	        $thh = $info[1];
	    }
	}
	// Little square thumbnails - alter src_x and src_y
	else {
		if($aspect > 1) $thx = ($info[0]/2)-($info[1]/2);
		elseif($aspect < 1) $thy = ($info[1]/2)-($info[0]/2);
		$info[0] = $info[1] = min($info[0],$info[1]);
	}
    // Create the thumb
	$thumb=imagecreatetruecolor($thw,$thh);
	imagecopyresampled($thumb,$src,0,0,$thx,$thy,$thw,$thh,$info[0],$info[1]);
	imagejpeg($thumb,$dest,$qual);

	// Garbage collection
	imagedestroy($thumb);
	imagedestroy($src);
}

/*
* Func : dirScan
* Desc : Recursive directory scan... new in 3.0!
*/
function dirScan($dir,$filter=1) {
	global $imageExt,$thumbDir;
	$dirs   = array_diff(scandir($dir),array(".",".."));
	$dirarr = array();
	foreach($dirs as $d) {
		if(is_dir($path=$dir."/".$d) && !strstr($path,$thumbDir) && !strstr($path,"VIZ"))
			$dirarr = array_merge($dirarr,dirScan($path));
		else {
			if(in_array(strtolower(end(explode(".",$d))),$imageExt))
				$dirarr[$path] = array("dir"=>$dir,"file"=>$d,"full"=>$path);
		}
	}
	return $dirarr;
}

/*
* Func : simpScan
* Desc : Non-recursive directory scan.
*/
function simpScan($dir,$filter=1) {
	global $imageExt,$thumbDir;
	$dirs   = array_diff(scandir($dir),array(".",".."));
	$dirarr = array();
	foreach($dirs as $d) {
		if(is_dir($path=$dir."/".$d) && !strstr($path,$thumbDir))
			$dirarr[$path] = array("dir"=>$dir,"file"=>$d,"full"=>$path);
	}
	return $dirarr;
}

/*
* End function set and begin actions. Actions are called
* and performed by the script through $_GET['act'].
*/
$do = $_GET['act'];

if($do == "makethumb") {
	$allFiles =  dirScan('.');

	// Find and replace illegal characters
	$chars = array("&"=>"and","'"=>"sq");
	$old_file = $allFiles[$_GET['file']]["full"];
	$allFiles[$_GET['file']]["file"] = str_replace(
		array_keys($chars),
		array_values($chars),
		$allFiles[$_GET['file']]["file"]
	);
	rename($old_file,$allFiles[$_GET['file']]["full"]);

	// Make folder if it doesn't exist
	$folder = $allFiles[$_GET['file']]["dir"]."/".$thumbDir."/";
	if(!is_dir($folder)) mkdir($folder);

	$filename = $thumbSfx.$allFiles[$_GET['file']]["file"];
	$filename1= $thumbPfx.$allFiles[$_GET['file']]["file"];

	// Make large thumbnail
	if(isset($_GET['lg'])) {
		makeThumb($_GET['file'],$folder.$filename1,$thumbSize,$sqr=0,85); $msg.="Large thumbnail created.";
	}
	else {
		// Make square thumbnail from larger thumb if it exists
		$file=(is_file($filename1))?$filename1:$_GET['file'];
		// Make square thumbnail
		makeThumb($file,$folder.$filename,$thumbSqr,$sqr=1,75); $msg="Square thumbnail created. ";
	}

	echo json_encode(array(
		"file"=>$_GET['file'],
		"sqfile"=>$filename,
		"path"=>$folder.$filename,
		"dir" =>$folder,
		"msg" =>$msg
	));
}

/*
* Check if large/reg thumbnail exists
*/
if($do == "exist"){

	$ret=(is_file($_GET['d']."/".$thumbDir."/".$thumbPfx.$_GET['f']))?1:0;

	echo json_encode(array(
		"file"=>$_GET['f'],
		"thumb"=>$_GET['d']."/".$thumbDir."/".$thumbPfx.$_GET['f'],
		"ret"=>$ret
	));

}

/*
* Display embedded images
*/
if($do == "ico"){

	// Folder icon
	$img[1] = "R0lGODlhEAAOALMAAOazToeHh0tLS/7LZv/0jvb29t/f3//Ub/
	/ge8WSLf/rhf/3kdbW1mxsbP//mf///yH5BAAAAAAALAAAAAAQAA4AAARe8L1Ekyky67QZ1hLnjM5UUde0ECwLJoExKcpp
	V0aCcGCmTIHEIUEqjgaORCMxIC6e0CcguWw6aFjsVMkkIr7g77ZKPJjPZqIyd7sJAgVGoEGv2xsBxqNgYPj/gAwXEQA7";

	// Image icon
	$img[2] = "R0lGODlhEAAQAMQfAHipU2WWl5vHjoGzqsfOppet0Zqw0e7z9/P195i25Hmf0pTHZefu9tKkXIjB
	V4GIToa4cIip3cnU46bQfqm61cLcv5Ws0H+9U2aZP5+755l7NYCkxpC10Ozu8a+GP////yH5BAEA
	AB8ALAAAAAAQABAAQAWE4CeOZCkWR8YNkAMBgRIxhmghySBMSw/FNNuhQiwaGQURpYNoOp2HTu1z
	y/Ecl2xgNi10AGBNo+HBmIMfCcXAbhsKbYlIbbDY73eD/GPArXYTAjEKB1NVAzwLDg6CCmgoGToL
	WRc/M0kfkCoPnA8bEVxCRQQEDUdTSwcIB6ytrFImsSQhADs=";

	// Video icon
	$img[3] = "R0lGODlhEAAQAMQfAGdnZ1hYWKysrG+82nZ2djU1NYeHh0tLS3HF/J7hc9jYkmW7/JLeV5iYmHS8
	6mPJUKPiehEREZXhXGnF3XrYWPXecazMrmLHcFKvzFi4tl28TF7Af2rRQpzeb2y57P///yH5BAEA
	AB8ALAAAAAAQABAAQAWJ4CeOZDkCBBCsbHEUpCEQ24QsVgUIAbkHDwrnksEUGrARQaBirQ4NAInQ
	IASHxQDh4DMAarfcgZAUFQQF5yoiiMQIBpNKeko57h5F5QAof+BWGhMLCwMBAFxKMwkQEBKPAQ2J
	IlQECJcICgoBBpMfhwAagoQDBy8kBX0dEAkMrhEAbiMvnmYRsiEAOw==";

	// Roddzilla logo
	$img[4] = "R0lGODlhGgAVAOYAAAAAAP///8XGxrO0tKSlpdPR0dLQ0MjGxuzr6+no6Ojn5+Xk5OTj4+Pi4uLh
	4eDf39/e3t3c3Nzb29va2tnY2NjX19fW1tbV1dXU1NTT09PS0tLR0dHQ0NDPz8/Ozs7Nzc3MzMzL
	y8vKysrJycjHx8fGxsbFxcTDw8PCwsLBwcC/v7++vr69vb28vLy7u7u6urq5ubi3t7a1tbW0tLSz
	s7OysrCvr6+urq6tra2srKyrq6uqqqqpqainp6WkpKOiou3t7ezs7Ovr6+rq6unp6ejo6Ofn5+bm
	5uXl5eTk5OPj4+Li4uHh4eDg4N/f397e3t3d3dzc3Nvb29ra2tnZ2djY2NfX19bW1tXV1dPT09LS
	0tDQ0M/Pz87OzszMzMvLy8fHx8XFxcTExMPDw8LCwr6+vr29vby8vLq6uri4uLa2trW1tbS0tLOz
	s7KysrGxsbCwsK+vr66urq2traysrKqqqqmpqaenp6ampqKiov///wAAAAAAAAAAAAAAAAAAACH5
	BAEAAHoALAAAAAAaABUAAAf/gHqCg4SCQEZSYGtraGNZQYWRg0ZZZjg7KhkTRpKdSGByOzAeUEWC
	Q09XWFSmkkJednRkHUxDek9hazs4bWoxaBpAhU0DeCocGA6CTV9cWRscGhvQIFWEWHc1IBkZC50S
	0dMaEoNbPi4aGRsWVCgrXZyEHh3TG1GCV3krGdIbGSIoSJwgc2+QkhbqNihbUueFOg0WJECQcOEC
	vw9IBhGhMa3CECBlbETDICGeHiEVommQMujIjQ4akuhxcocEhgtLBCWBJGjENAvC9FDRwWGCMAFt
	uDHQo+UNHDNPBGk5cZNnGRkWiAhqkwJDEz1iCLT4wKGDrSsxMkwRFOUHiYyCnuSEsCLEyo8D/DRc
	kMlFRoalROSsUUKIDi09cVhgqKfBWxkXERAMaZODSSE6BZAA6dHhAQVpGBIYwePFCBA2cxpEcmOA
	8AzCU0iM03OmRhIhbWjALRQGBDlvesbIwKDgCo8mSdqMsSWJSQoLVoAA2ZLHBJImMppMSbOl0yAp
	GzSgaOMDDBInJZxYGCHT+yAkEqhgcCKkSBYhTibwdB8IADs=";

	// Thinking animated gif
	$img[5] = "R0lGODlhDAAMAOZ8AO7u7pWVlaqqqr+/v3FxcdLS0tLS04GBgoKCgoKBgm5ubuPj4+Pi4uPj4pub
	m7m5uXFycaampt7e3t/f33h4d4GCgnp6e+Lj45qamsTExOrq6uLi45CQkODf4MfHx3Fycs/Pz25t
	bYWFhdPT03V1dbCvr5mZmWxsbOzs66SlpHV2dcXFxX1+fdXV1tfX1nJycczNzZGRkra2ttnZ2dLT
	04iIh83MzaGhobu8vN3c3NjX2Nzc3OXl5a6urZubmnd2dnZ2dn19fYmJioeGhomKiZ6dnuvs7Obm
	5p6enY+Pjuzt7dbX19PT0nx9fMvKyujo6LGwsNXW1cPCwm9vb3Jycs7Nzq6urru7u39+fs7OzoKC
	gbq5ueXm5t7f37q6uqSlpevr683OzZaVlefn5+vr7LOzs9fX1+bm57a2t4iHh8LDw3JxcX5/f6+v
	r42MjYeHhoaGhnR0dKSjpMXGxuzs7bOzsrGxsezs7MrLy6Wlpb6/v46Ojv///wAAAAAAAAAAACH/
	C05FVFNDQVBFMi4wAwEAAAAh+QQFAAB8ACwAAAAADAAMAAAHO4B8goOEhYIkL4Z8FWx8PwR8AACE
	IgiEkoqZHEmGT2OCAQGGFwuZijdfhAVmhBECfGgDfFkGig+ypoqBACH5BAUAAHwALAEAAQAKAAoA
	AAc2gHyCfHAIg4IBMXw1hhRxgiYBhyQfh5aCeXKXAAACApsAl5YyW4cLR4JXA3x4BXwTDZY2BoeB
	ACH5BAUAAHwALAEAAQAKAAoAAAc3gHyCfBgBg4ICEXwOhkIiglYCh0MJh5aCXg+XFEB6A5cfBJeW
	TlWHAACCIAV8ORt8d6mHEg2HgQAh+QQFAAB8ACwBAAEACgAKAAAHN4B8gnwlAoOCAzh8UIZFJoJq
	A4c+AYeWgmEwl0RpBjSXBwmXljtdh1Qqgh0XfBoAfAoQlmSvg4EAIfkEBQAAfAAsAQABAAoACgAA
	BzeAfIJ8GQODgiMgfCuGZT2CUUyHJQKHloITEpdIDgwLlwEBl5YaRocVb4JKAHwnEHxBWpYhBIeB
	ACH5BAUAAHwALAEAAQAKAAoAAAc4gHyCfC4Gg4IMHXw6BXweUoI8DYcZA4eXgihgmHV2AACYAgKY
	lycKh2IYglNrfBYHfBwBl02wg4EAIfkEBQAAfAAsAQABAAoACgAABzeAfIJ8XAyDggB0fGcbfDMt
	iACHSyOHloIKIZcecwQElwMDl5YWLIcCbYJYCXxuAXwpApZ7r4OBADs=";

	header("Content-type: image/gif");
	echo base64_decode($img[$_GET['img']]);
}


// Kill output on actions
if(isset($_GET['act'])) die();

?>

<!-- begin html output -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head><title><?php echo $albumName; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />

<!-- style information  -->

<style type="text/css">
body {
	font-family: lucida grande,helvetica,verdana,arial;
	font-size:11px;margin:0;padding:0px;line-height:18px;
}
.thumbholder a:link,
.thumbholder a:visited,
.thumbholder a:active {
	color:#fff;
	text-decoration: none;
}
.cl {
	margin:0;padding-bottom:4px;vertical-align: middle;
	border-collapse: collapse;
}
p,h1,form {
	margin:0;padding:0;
}
.installtop {
	width:100%;background-color:#eee;
	vertical-align:bottom;border-bottom:1px solid #ccc;
}
.area {
	border:1px solid #D4D0C7;font-size:12px;
	font-family:tahoma;padding:2px;margin:0;
}
img {
	vertical-align:middle;
	padding-bottom: 2px;border:0px;
}
#progress {
	background-color:blue;line-height:22px;
	height:22px;width:0.1%;
}
#progBarHolder{
	visibility:hidden;
}
.sqthumbs {
	float:left;padding:8px;
}
#shadbg
{
	background-color:#000;filter:alpha(opacity=86);-moz-opacity:0.86;opacity:0.86;
	visibility:hidden;position:absolute;width:100%;color:#fff;z-index:5;
	cursor:pointer;
}

.thumbholder
{
	position:absolute;z-index:6;cursor:pointer;
	width:100%;text-align:center;visibility:hidden;
}

</style>


<!-- going to use the google hosted jquery library -->
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
<!-- url parser helper -->
<script type="text/javascript" src="http://github.com/cowboy/jquery-bbq/raw/master/jquery.ba-bbq.js"></script>

<!-- scripts -->
<script type="text/javascript">
// Processing semaphore
var aid = 0;
var iarr = new Array();
var bover = 0;
var processing = false;
var lastimg;
var timer;

/*
* Func: getObj(name) - return an object
*/
function getObj(name) {
	obj = document.getElementById(name);
	if(obj==null){ alert("Object ID: "+name+" could not be found."); }
	return obj;
}

/*
* Func: redirect(loc,time) - pretty basic
*/
function redirect(mylocation,mytime) {
	var rDTime = setTimeout( function(){
		location.href = mylocation.replace('#','');
	},mytime);
}

/*
* Func: popWindow(id,url,x,y)
*/
function popWindow(id,url,x,y) {
	day = new Date();
    eval(id+"=window.open(url,'"+id+"','toolbar=0,scrollbars=0,"
    	+ "location=0,statusbar=1,menubar=0,resizable=0,"
    	+ "width="+x+",height="+y+",left=400,top=300');");
}

/*
* Func: makeThumb(file)
*/
function makeThumb(file,type) {
	processing = true;
	console("Processing image: "+file);

	var lg=(type=="lg")?"&lg":"";
	$.getJSON("?act=makethumb"+lg+"&file="+file,function(data) {
		lastimg = data.dir+data.sqfile;
		processing=false;
	});
}

/*
* Func: install()
*/
function install() {
	var count = 0;
	var total = ($('#lgthumbs').attr('checked'))?
			(lgfiles.length+sqfiles.length):sqfiles.length;

	// Combine large and small thumbs
	var files = new Array();

	if($('#lgthumbs').attr('checked') && lgfiles.length>0) {
		for(var j=0;j<lgfiles.length;j++) {
			files.push({"name":lgfiles[j],"type":"lg"});
		}
	}

	if(sqfiles.length>0) {
		for(var i=0;i<sqfiles.length;i++)
			files.push({"name":sqfiles[i],"type":"sq"});
	}

	if(files.length==0) {
		$('#installmsg').html("Thumnails are all already made!");
		return;
	}

	// Button feedback
	$('#continue').val("Installing...");
	$('#continue').attr('disabled', 'disabled');
	$('#lgthumbs').attr('disabled', 'disabled');
	$('#showcons').attr('disabled', 'disabled');
	$('#installmsg').html("Please do not navigate away from this page during installation...");

	// Reveal progress bar and info
	getObj("progBarHolder").style.visibility="visible";
	if(getObj("showcons").checked) getObj("wholeconsole").style.visibility="visible";

	// The installation loop
	timer = setInterval(function() {
		// Track the install progress
		var donePerc = ((count/total)*100).toFixed(2);
		$('#countHolder').html(count+" of "+total+" complete.");
		$('#antiprog').html(donePerc+"%");
		$('#progress').width(donePerc+"%");

		// When finished
		if(count >= total) {
			clearInterval(timer);
			$('#installmsg').html("Install successful! Please wait while you're redirected...");
			redirect("?view=gallery",3000);
		}

		// Make the thumbnails
		if(!processing) {
			// Show images made
			if(count!=0) $('#imgholder').append("<img src='"+lastimg+"'>&nbsp;");
			if(count%6==0&&count!=0) $('#imgholder').html('');

			makeThumb(files[count].name,files[count].type);
			count++;
		}
	},50);
}

/*
* Func: console()
*/
function console(msg) {
	if($('#showcons').attr('checked')) {
		if(getObj("console").scrollHeight > 5000)
			$('#consoletxt').html('');
		$('#consoletxt').append(msg+"<br>");
		getObj("console").scrollTop = getObj("console").scrollHeight;
	}
}

/*
* Func: showimg
* Desc: show large thumb or actual image.
*/
function showimg(file) {
	if(file=="undefined")
		return;

	var dir = "<?php echo $_GET['dir'];?>";
	$('#shadbg').height($(document).height());
	$('#shadbg').css("visibility","visible");

	//check if lg thumbnail exists, else show orig
	$.getJSON("?act=exist&d="+dir+"&f="+file,function(data){
		if(!data.ret)
			location.href=dir+"/"+file;
		else {
			// show lg thumb
			$('#thumbholder').css("visibility","visible");
			$('#lgthumb').attr("src","?act=ico&img=5");
			$('#thumbholder').css("top",$(window).scrollTop());
			$('#origlink').attr("href",dir+"/"+file);

			$('#imgloader').attr("src",data.thumb);
			$('#imgloader').load(function(){
                $('#lgthumb').css({width:'auto',height:'auto'});
				$('#lgthumb').attr("src",data.thumb);
			});

			// show prev and next links
			aid=parseInt(aid);
			$('#prevlink').attr("href","#img="+iarr[(aid-1)]+"&aid="+(aid-1));
			$('#nextlink').attr("href","#img="+iarr[(aid+1)]+"&aid="+(aid+1));
			if(aid < 1) $('#prevlink').html(""); else $('#prevlink').html("&laquo;");
			if(aid == iarr.length-1) $('#nextlink').html(""); else $('#nextlink').html("&raquo;");
		}
	});
}

function unshowimg() {
	if(bover==1) { bover=0; return; }
	$(this).css("visibility","hidden");
	$('#thumbholder').css("visibility","hidden");
	$('#shadbg').css("visibility","hidden");
	$.bbq.pushState("img","");
	$.bbq.pushState("aid","");
}

/*
* Run-time operations
*/
$(document).ready(function(){

	var qsf = $.deparam.fragment();
	if(typeof(qsf.img)=="string" && qsf.img!='') {
		aid = qsf.aid;
		showimg(qsf.img);
	}
	$('#shadbg').height($(document).height());
	$('#shadbg').click(function(){unshowimg();});
	$('#thumbholder').click(function(){unshowimg();});

	$(window).scroll(function(){
		//$('#thumbholder').css("top",$(this).scrollTop());
	});

	// enables the back button in the browser - sweet
	$(window).bind('hashchange', function(e) {
		qsf = $.deparam.fragment();
		if(typeof(qsf.img)=="string" && qsf.img!="") {
			aid = qsf.aid;
			showimg(qsf.img);
		}
		else
			unshowimg();
	});

});

</script>

</head>
<body>

<div style="visibility:hidden;position:absolute;z-index:-1;">
	<img id="imgloader" style="width:1px;height:1px;" />
</div>

<div class="transparent" style="" id="shadbg"></div>
<div class="thumbholder" id="thumbholder">
	<div style="padding:30px;color:#fff;font-size:45px;">
		<div style="display:inline;position:relative;" onclick="showimg(iarr[aid-1]);aid--;bover=1;">
		<a id="prevlink"></a></div>
		<img id="lgthumb" style="border:4px solid #333;padding:0px;vertical-align:middle;width:<?php echo $thumbSize;?>px;height:<?php echo $thumbSize;?>px;" />
		<div style="display:inline;position:relative;" onclick="showimg(iarr[aid+1]);aid++;bover=1;">
		<a id="nextlink"></a></div>
		<p style="cursor:pointer;color:#fff;font-size:13px;padding:top:5px;">
			[<a href="#" id="origlink" style="color:#fff;">Full Size</a>]
		</p>
	</div>
</div>

<div style="position:absolute;right:20px;top:10px;font-size:9px;color:#aaa;
	line-height:12px;cursor:default;" title="Roddzilla Webstudios Copyright � 2010">
	<table class="cl"><tr>
		<td style="text-align:right;padding-right:8px;">
		Photo Insta-Gallery</td>
		<td style="padding-top:3px;vertical-align:middle;"><img src="?act=ico&img=4" /></td>
	</tr></table>
</div>

<?php

/*
* $_GET['view'] controls what content is seen on a per page basis. Pages:
* Start page: opening page decides where to redirect
* Install page: makes thumbnails
* Gallery page: displays images
*/

if($_GET['view'] == "install") {
	// Get a list of only directories (in order)
	//print_r($dirs);
	$n = 0;
	$m = 0;
	foreach(dirScan('.') as $k=>$f) {
		$dirs[$f["dir"]] = 1;

		//echo $f['full']." ".filesize($f['full'])."<br>";
		if(filesize($f['full']) > 8388608)
			continue;

		// Get list of files that need square thumbs
		if(!is_file($f['dir']."/".$thumbDir."/".$thumbSfx.$f['file'])) {
			$sqbuff .= "sqfiles[".$n."]=\"".$f['full']."\"; \n";
			$n++;
		}

		// Get list of files that need regular (larger) thumbs
		if(!is_file($f['dir']."/".$thumbDir."/".$thumbPfx.$f['file'])) {
			$lgbuff .= "lgfiles[".$m."]=\"".$f['full']."\"; \n";
			$m++;
		}
	}
	$dirs=array_keys($dirs);
?>

<div class="installtop">
	<div style="padding:15px 0px 20px 20px;">
		<p style="font-size:15px;padding:3px 0px;">Gallery Insta-llation</p>
		<p style="padding-bottom:5px;line-height:24px;">The installer is going to attempt to make thumbnails of your
			photos. In order for this utility to work correctly, please ensure the following: </p>
			1. Files and folders are readable and writable.<br />
			2. The GD image library is installed and enabled.<br />
			3. You are using IE, Firefox, Safari or Netscape.
	</div>
</div>

<table class="cl" style="width:100%;"><tr><td style="width:350px;">
	<div style="padding:15px 20px;line-height:17px;">
		<input type="checkbox" id="allsubs" style="vertical-align:top;" checked disabled="disabled" />
		<span style="">Process images in subfolders. </span><br />
		<input type="checkbox" id="lgthumbs" style="vertical-align:top;" />
		<span style="">Make additional larger thumbnails. </span><br />
		<input type="checkbox" id="private" style="vertical-align:top;" disabled="disabled" />
		<span style="">Make gallery private. </span><br />
		<input type="checkbox" id="optimize" style="vertical-align:top;" disabled="disabled" />
		<span style="">Optimize original files for web. </span><br />
		<input type="checkbox" id="showcons" style="vertical-align:top;" />
		<span style="">Show console during installation (debugging). </span><br /><br />
		<input type="button" value="Continue &raquo;" style="font-size:12px;"
			id="continue" onclick="javascript:install();"  />&nbsp;&nbsp;
			<span id="installmsg" style="color:#888;"></span><br />&nbsp;
	</div>
</td><td style="vertical-align:top;padding:20px 30px;overflow-x:hidden;overflow:auto;" id="imgtd">
	<div style="width:100%;" id="imgholder"></div>
</td></tr></table>

<div id="progBarHolder" style="padding:0px 20px;width:95%;height:30px;">
	<table class="cl" style="width:100%;"><tr><td id="progress"></td>
	<td id="antiprog"></td></tr></table>
	<div id="countHolder" style="padding:8px 0px;"></div>
</div>

<div style="padding:20px;line-height:17px;visibility:hidden;" id="wholeconsole">
	<div id="console" style="width:500px;height:200px;position:relative;
		padding: 0px;overflow-y:auto;overflow-x:hidden;overflow:auto;">
		<div style="padding:0px;background-color:#efefef;" id="consoletxt" ></div>
	</div>

	<input type="button" value="Stop Processing" style="font-size:12px;"
		 onclick="clearInterval(timer);"  /> &nbsp;
	<input type="button" value="Clear Console" style="font-size:12px;"
		 onclick="getObj('consoletxt').innerHTML='';"  />
</div>


<script type="text/javascript">
var sqfiles = new Array();
var lgfiles = new Array();
<?php echo $sqbuff . $lgbuff; ?>
</script>

<?php

/*
* $_GET['view'] controls what content is seen on a per page basis. Pages:
* Start page: opening page decides where to redirect
* Install page: makes thumbnails
* Gallery page: displays images
*/

} elseif($_GET['dir']||$_GET['dir']=="") { ?>

	<div class="installtop">
		<?php if(strlen($sqbuff)>0) { ?>
			<div style="padding:10px 10px 10px 20px;position:absolute;right:0px;">
				<p style="font-size:12px;padding:3px 0px;">
				<?php echo "Found new subjects. <a href='?view=install'>Install them</a>."; ?>
				</p>
			</div>
		<?php } ?>
		<div style="padding:10px 0px 10px 20px;">
			<p style="font-size:15px;padding:3px 0px;">
			<?php
				echo "<a href='?dir='>".$albumName."</a>";
				$dirpieces = explode("/",$_GET['dir']);
				foreach($dirpieces as $piece) {
					if($piece!=end($dirpieces)) {
						$path =($piece==reset($dirpieces))?$piece:$path."/".$piece;
						echo " &raquo; <a href='?dir=".$path."'>".$piece."</a>";
					}
					else
						echo " &raquo; ".$piece;
				}
			?>
			</p>
		</div>
	</div>

	<?php

	/*
	* Need to separate files in current directory and
	* immediate sub folders.
	*/
	$n=0;
	$skip=false;

	$dir=($_GET['dir'])?$_GET['dir']:".";

	// Go through all files and all subfolders
	// Still computationally heavy for lots of images :(

	foreach(dirScan($dir) as $key=>$file) {

		// Filter current and prev directory pointers
		if(substr($file['dir'],0,2)=="./")
			$file['dir'] = substr($file['dir'],2);

		// Get files in current directory. Should work when !$_GET['dir'] too
		if(str_replace(".","/",$_GET['dir'])==$file['dir']) {
			$curdir[$key] = $file;
		}
		// Get next dirs (folders within this one)
		$getarr = explode("/",$_GET['dir']);
		$dirarr = explode("/",$file['dir']);
		$diff   = array_diff($dirarr,$getarr);

		//print_r($getarr); echo " "; print_r($dirarr); echo " ";
		//print_r($diff); echo "<br>";

		// Filter out current dirs from dir array

		//Um, Bugfix?
		if(!$_GET['dir']) {
			$nxtdir[$dirarr[0]] = array(
				"name"=>$dirarr[0],
				"full"=>$dirarr[0]
			);
		}
		else
			$nxtdir[reset($diff)] = array(
				"name"=>reset($diff),
				"full"=>$_GET['dir']."/".reset($diff)
			);

		$n++;

	}
	//$nxtdir=@array_unique($nxtdir);
	//print_r($nxtdir);
	/*
	* Whew - got to be an easier way for nxtdirs but anyway
	* time to start showing some stuff!
	*/
	?>

	<div style="padding:15px 20px;">

	<?php

		if(sizeof($curdir)==0) {
			// Awe, no pictures.
			if($nxtdir) {
				foreach($nxtdir as $dir) {
					if($dir['name'])
					echo "<img src='?act=ico&img=1'> &nbsp;<a href='?dir=".$dir['full']."'>".$dir['name']."</a><br> ";
				}
			}
		} else {
			if($nxtdir) {
				echo "<p style='padding-bottom:6px;'>";
				foreach($nxtdir as $dir) {
					if($dir['name'])
					echo "<img src='?act=ico&img=1'> &nbsp;<a href='?dir=".$dir['full']."'>".$dir['name']."</a><br> ";
				}
				echo "</p>";
			}
			// We have pictures... lets display them
			$n=0;
			foreach($curdir as $file) {
				if(is_file($sqth=$file['dir']."/".$thumbDir."/".$thumbSfx.$file['file'])) {
					echo "<div class='sqthumbs'><a href='#img=".$file['file']."&aid=".$n."'
					onclick='showimg(\"".$file['file']."\");aid=".$n.";'>
					<img src='".$sqth."' style='cursor:pointer;' /></a></div>
					<script type='text/javascript'>iarr[".$n."]=\"".$file['file']."\";</script>";
					$n++;
				}
			}
		}
	?>
	</div>



<?php } ?>
