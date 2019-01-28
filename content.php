<?php
/********************************
Simple PHP File Browser
Copyright John Campbell (jcampbell1)

Liscense: MIT
********************************/

//Disable error report for undefined superglobals
//error_reporting( error_reporting() & ~E_NOTICE );
ini_set('display_errors',1); 
error_reporting(E_ALL);


//Security options
$allow_delete = true; // Set to false to disable delete button and delete POST request.
$allow_upload = true; // Set to true to allow upload files
$allow_create_folder = true; // Set to false to disable folder creation
$allow_direct_link = true; // Set to false to only allow downloads and not direct link
$allow_show_folders = true; // Set to false to hide all subdirectories

$hidden_Entries = ['$RECYCLE.BIN','System Volume Information','msx'];
$vedio_ext = ['mp4','mpg','mpeg','vob','avi'];
$audio_ext = ['mp3','audio'];
$image_ext = ['gif','jpg','jpeg','png','ico'];

$hidden_extensions = ['php','json','zip','exe','7z','ini','cab']; // must be an array of lowercase file extensions. Extensions hidden in directory index

$PASSWORD = '';  // Set the password, to access the file manager... (optional)

if($PASSWORD) {

	session_start();
	if(!$_SESSION['_sfm_allowed']) {
		// sha1, and random bytes to thwart timing attacks.  Not meant as secure hashing.
		$t = bin2hex(openssl_random_pseudo_bytes(10));
		if($_POST['p'] && sha1($t.$_POST['p']) === sha1($t.$PASSWORD)) {
			$_SESSION['_sfm_allowed'] = true;
			header('Location: ?');
		}
		echo '<html><body><form action=? method=post>PASSWORD:<input type=password name=p /></form></body></html>';
		exit;
	}
}

$tmp_dir = dirname($_SERVER['SCRIPT_FILENAME']);
if(DIRECTORY_SEPARATOR==='\\') $tmp_dir = str_replace('/',DIRECTORY_SEPARATOR,$tmp_dir);
$file = ".";
if(isset($_REQUEST['path'])){
	$file = $_REQUEST['path'];
}

$tmp = get_absolute_path($tmp_dir . '/' .$file);

if($tmp === false)
	err(404,'File or Directory Not Found');
if(substr($tmp, 0,strlen($tmp_dir)) !== $tmp_dir)
	err(403,"Forbidden");
if(strpos($file, DIRECTORY_SEPARATOR) === 0)
	err(403,"Forbidden");

if(!$_COOKIE['_sfm_xsrf'])
	setcookie('_sfm_xsrf',bin2hex(openssl_random_pseudo_bytes(16)));
$file = $file ?: '.';
$headline = $file ?: 'Home Server';

	if (is_dir($file)) {
		$responseObj = new stdClass();
		$responseObj->headline =  $headline;
		$responseObj->type = "list";
		//$responseObj->pages = [];
		//$itemArray = new stdClass();
		$responseObj->template = new stdClass();
		$responseObj->template->type = "separate";
		$responseObj->template->layout = "0,0,2,4";
		$responseObj->template->icon = "msx-white-soft:movie";
		

		$directory = $file;
		$result = [];
		$files = array_diff(scandir($directory), ['.','..']);
		foreach ($files as $entry){
			$i = $directory . '/' . $entry;
			if (!is_entry_ignored($entry, $allow_show_folders, $hidden_extensions)) {
				$stat = stat($i);
				if(is_dir($i)){
					$result[] = [
						'mtime' => $stat['mtime'],
						'size' => $stat['size'],
						'label' => basename($i),
						'action' => get_directory_action($i,$allow_show_folders,$hidden_extensions),
						'icon'=>'folder'
					];
				}else{
					$result[] = [
						'mtime' => $stat['mtime'],
						'size' => $stat['size'],
						'label' => basename($i),
						'action' =>  get_action($directory,$entry),
						'icon'=>get_icon($directory,$entry),
						'imageLabel'=>basename($i)
					];
				}
			}
		}
	} else {
		err(412,"$file is not a Directory");
	}
			//$itemArray->items = $result;

	$responseObj->items = $result;
	header('Content-Type: application/json');

	echo json_encode($responseObj);
	exit;
	
	
function is_entry_ignored($entry, $allow_show_folders, $hidden_extensions) {
	if ($entry === basename(__FILE__)) {
		return true;
	}
	
	if(startsWith($entry,".")){
		return true;
	}

	if (is_dir($entry) && !$allow_show_folders) {
		return true;
	}
	$hidden_Entries = $GLOBALS['hidden_Entries'];//['$RECYCLE.BIN','System Volume Information'];

	if(in_array($entry, $hidden_Entries))
	{
		return true;
	}

	$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
	if (in_array($ext, $hidden_extensions)) {
		return true;
	}

	return false;
}
function startsWith ($string, $startString) 
{ 
    $len = strlen($startString); 
    return (substr($string, 0, $len) === $startString); 
} 
// from: http://php.net/manual/en/function.realpath.php#84012
function get_absolute_path($path) {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }

function err($code,$msg) {
	http_response_code($code);
	echo json_encode(['error' => ['code'=>intval($code), 'msg' => $msg]]);
	exit;
}

function get_directory_action($new_entry,$allow_show_folders,$hidden_extensions){
	$files = array_diff(scandir($new_entry), ['.','..']);
	$hadDir = false;
	$hadImage = false;
	$hadVideo = false;
	$hadAudio = false;
	foreach ($files as $entry){
		$i = $new_entry . '/' . $entry;
		if (!is_entry_ignored($entry, $allow_show_folders, $hidden_extensions)) {
			if(is_dir($i)){
				$hadDir = true;
			} else{
				$file_type = get_file_type($entry);
				if($file_type == "image")
				{
					$hadImage = true;
				}
				if($file_type == "video")
				{
					$hadVideo = true;
				}
				if($file_type == "audio")
				{
					$hadAudio = true;
				}
			}
		}
	}
	if($hadDir){
		return 'content:http://' . $_SERVER['SERVER_ADDR'] . '/content.php?path=' . preg_replace('@^\./@', '', $new_entry);
	}elseif($hadImage){
		return 'slideshow:http://' . $_SERVER['SERVER_ADDR'] . '/content.php?path=' . preg_replace('@^\./@', '', $new_entry);
	}elseif($hadAudio || $hadVideo){
		return 'playlist:http://' . $_SERVER['SERVER_ADDR'] . '/content.php?path=' . preg_replace('@^\./@', '', $new_entry);
	}else{
		return 'content:http://' . $_SERVER['SERVER_ADDR'] . '/content.php?path=' . preg_replace('@^\./@', '', $new_entry);
	}
		
}

function get_action($directory,$entry){
	$file_type = get_file_type($entry);
	$icon_Mapping = array(
		"video"=>'video',
		"audio"=>'audio',
		"image"=>'image',
		"other"=>'link'
	);
	
	return $icon_Mapping[$file_type] . ":" . 'http://' . $_SERVER['SERVER_ADDR'] . '/' . $directory . '/' . $entry;
}

function get_icon($directory,$entry){
	$file_type = get_file_type($entry);
	$icon_Mapping = array(
		"video"=>'local-movies',
		"audio"=>'audiotrack',
		"image"=>'image',
		"other"=>'link'
	);
	return $icon_Mapping[$file_type];
	
}
function get_file_type($entry){
	$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
	if(in_array($ext,$GLOBALS['vedio_ext'])){
		return "video";
	}
	if(in_array($ext,$GLOBALS['audio_ext'])){
		return "audio";
	}
	if(in_array($ext,$GLOBALS['image_ext'])){
		return "image";
	}
	return "other";
}
?>
