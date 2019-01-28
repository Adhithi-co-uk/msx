<?php
/********************************
Simple PHP File Browser
Copyright John Campbell (jcampbell1)

Liscense: MIT
********************************/

//Disable error report for undefined superglobals
error_reporting( error_reporting() & ~E_NOTICE );

//Security options
$allow_delete = true; // Set to false to disable delete button and delete POST request.
$allow_upload = true; // Set to true to allow upload files
$allow_create_folder = true; // Set to false to disable folder creation
$allow_direct_link = true; // Set to false to only allow downloads and not direct link
$allow_show_folders = true; // Set to false to hide all subdirectories

$hidden_Entries = ['$RECYCLE.BIN','System Volume Information'];

$disallowed_extensions = ['php'];  // must be an array. Extensions disallowed to be uploaded
$hidden_extensions = ['php']; // must be an array of lowercase file extensions. Extensions hidden in directory index

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
$tmp = get_absolute_path($tmp_dir . '/' .$_REQUEST['file']);

if($tmp === false)
	err(404,'File or Directory Not Found');
if(substr($tmp, 0,strlen($tmp_dir)) !== $tmp_dir)
	err(403,"Forbidden");
if(strpos($_REQUEST['file'], DIRECTORY_SEPARATOR) === 0)
	err(403,"Forbidden");

if(!$_COOKIE['_sfm_xsrf'])
	setcookie('_sfm_xsrf',bin2hex(openssl_random_pseudo_bytes(16)));
$file = $_REQUEST['file'] ?: '.';
$headline = $_REQUEST['file'] ?: 'Home Server';

	if (is_dir($file)) {
		$responseObj = new stdClass();
		$responseObj->headline =  $headline;
		$directory = $file;
		$result = [];
		$files = array_diff(scandir($directory), ['.','..']);
		foreach ($files as $entry){
			$i = $directory . '/' . $entry;
			if (!is_entry_ignored($entry, $allow_show_folders, $hidden_extensions) && is_dir($i)) {
				$stat = stat($i);
				$result[] = [
					'mtime' => $stat['mtime'],
					'size' => $stat['size'],
					'label' => basename($i),
					'data' =>  'http://' . $_SERVER['SERVER_ADDR'] . '/content.php?path=' . preg_replace('@^\./@', '', $i)
				];
			}
		}
	} else {
		err(412,"$file is not a Directory");
	}
	$responseObj->menu = $result;
	echo json_encode($responseObj);
	exit;
	
	
function is_entry_ignored($entry, $allow_show_folders, $hidden_extensions) {
	if ($entry === basename(__FILE__)) {
		return true;
	}
	$hidden_Entries = $GLOBALS['hidden_Entries'];//['$RECYCLE.BIN','System Volume Information'];

if(in_array(basename($entry), $hidden_Entries))
	{
		return true;
	}
	if (is_dir($entry) && !$allow_show_folders) {
		return true;
	}

	$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
	if (in_array($ext, $hidden_extensions)) {
		return true;
	}

	return false;
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
?>
