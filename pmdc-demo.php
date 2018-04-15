<?php
error_reporting(-1);
function D($a,$b=''){static $c = 0; if($b==='+'){$c++;$b=$c;} echo '<pre>'.gettype($a).' {'.@strlen($a).'} '.strtoupper($b).chr(10).str_repeat('=',12).chr(10).htmlspecialchars(print_r($a,true)).chr(10).str_repeat('=',12).'</pre>'.chr(10);}

// Use HTTP authentication based on a user database you specify a few lines below...
/*
	if ( !isset($_SERVER['PHP_AUTH_USER']) ){
	header('WWW-Authenticate: Basic realm="phpmaildirclass demo"');
	header($_SERVER['SERVER_PROTOCOL'].' 401 Unauthorized');
	die();
	}
	else{

$userdb = file_get_contents('/home/wwwroot/.htpasswd');
preg_match('/^'.$_SERVER['PHP_AUTH_USER'].':(.+?)$/m',$userdb,$matches);
	if ( is_array($matches) && sizeof($matches) === 2 ){
		if ( hash_equals($matches[1],crypt($_SERVER['PHP_AUTH_PW'],$matches[1])) ){
		// do nothing
		}
		else{
		header('WWW-Authenticate: Basic realm="phpmaildirclass demo"');
		header($_SERVER['SERVER_PROTOCOL'].' 401 Unauthorized');
		die();
		}
	}
	else{
	header('WWW-Authenticate: Basic realm="phpmaildirclass demo"');
	header($_SERVER['SERVER_PROTOCOL'].' 401 Unauthorized');
	die();
	}
*/
// If you want to use http authentication, also uncomment the last curly brace at the
// end of this script. Or you will run into a 'parse error'.
// Without http authentication you are likely have to set a static username.
$_static_user = 'demo';

require_once('./pmdc-class.php');
$_TIME = microtime(true);
$_action = ( isset($_GET['action']) ) ? $_GET['action'] : 'view_folder';
$_folder = ( isset($_GET['folder']) ) ? $_GET['folder'] : '.';
$_mid = ( isset($_GET['mid']) ) ? intval($_GET['mid']) : false;
$_part = ( isset($_GET['part']) ) ? intval($_GET['part']) : false;
$_user = ( isset($_SERVER['PHP_AUTH_USER']) ) ? strtolower($_SERVER['PHP_AUTH_USER']) : $_static_user;

// Some variables you can adapt to your needs...
$webmail_allowed_subfolders = array(
  '.',
  '.Sent',
  '.Spam',
  '.Trash'
);
$webmail_root_path = array(
  '/home/'.$_user.'/Maildir/'.$_folder.'/cur',
  '/home/'.$_user.'/Maildir/'.$_folder.'/new'
);
$webmail_folders = array( // 'path_relative_to_webmail_root' => array( 'displayed name', 'shown for these users' )
  '.'=>array('Posteingang','*'),
  '.Sent'=>array('Gesendet','*'),
  '.Spam'=>array('Spam','demo'),
  '.Trash'=>array('Papierkorb','foo,bar'),
);
Mail_Parser_l10n::$lang = 'de';
setlocale(LC_TIME,array('de_DE.utf-8'));
// ... now following the rest. Maybe you just want to translate the german strings ;)

$_folder = preg_replace('/[^a-z0-9\.\-]+/i','',$_folder);
$_folder = preg_replace('/[\.]{2,}/','',$_folder);
	if ( !in_array($_folder,$webmail_allowed_subfolders,true) )
	$_folder = '.';

$webmail_stack = array();
$webmail_stack_meta = array();
$webmail_stack_date = array();
	foreach ( $webmail_root_path as $path ){
		if ( !is_dir($path) || !is_readable($path) ){
		trigger_error('"'.$path.'" is not readable',E_USER_WARNING);
		continue;
		}
	$files = scandir($path);
		foreach ( $files as $file ){
			if ( $file === '.' || $file === '..' )
			continue;
		$full = $path.'/'.$file;
		$full_hash = md5($full);
		$webmail_stack_meta[$full_hash] = $full;
		$webmail_stack_date[$full_hash] = filemtime($full);
		}
	}
arsort($webmail_stack_date);
	foreach ( $webmail_stack_date as $key=>$value )
	array_push($webmail_stack,$webmail_stack_meta[$key]);
preg_match('/^(\d+)(k|m|g)$/im',ini_get('memory_limit'),$matches);
	if ( is_array($matches) && sizeof($matches) === 3 ){
		switch ( strtolower($matches[2]) ){
		case 'k': $value = $matches[1]*pow(10,3); break;
		case 'm': $value = $matches[1]*pow(10,6); break;
		case 'g': $value = $matches[1]*pow(10,9); break;
		default: break;
		}
	}
	else
	$value = ini_get('memory_limit');
define('MEMORY_LIMIT_AS_INT',$value);
unset($files,$userdb,$matches,$path,$file,$key,$value,$full,$full_hash,$webmail_stack_meta,$webmail_stack_date);

	if ( $_action === 'view_part' && isset($webmail_stack[$_mid]) ){
	$mail = new Mail_Parser;
		if ( !$mail->process($webmail_stack[$_mid]) )
		die('ERR');
		if ( $_part !== 255 ){
		$header = $mail->fetch('header');
		$body = $mail->fetch('body');
			if ( !isset($body[$_part]) )
			die('ERR');
		$body_part =& $body[$_part];
		$meta = $mail->determine_meta_of_part($header,$body_part);
		$buffer = $mail->fetch('body',$_part);
		$mail->decode_string($buffer,$meta);
		$charset_str = '';
			if ( substr($meta['type'],0,10) === 'multipart/' )
			$meta['type'] = 'text/plain';
			if ( substr($meta['type'],0,5) === 'text/' )
			$charset_str = '; charset='.$mail->charset_output;
			if ( $meta['type'] === 'text/html' )
			header('Content-Security-Policy: default-src \'self\'; style-src \'unsafe-inline\'');
		}
		else{
		$meta['type'] = 'text/plain; ';
		$charset_str = 'charset=utf-8';
			if ( filesize($webmail_stack[$_mid]) < MEMORY_LIMIT_AS_INT )
			$buffer = file_get_contents($webmail_stack[$_mid]);
			else
			die('ERR');
		}
	header('Cache-Control: no-cache,no-store,must-revalidate,maxage=0,s-maxage=0');
	header('Expires: '.gmdate('r',mktime(0,0,0,1,1,date('Y'))));
	header('Content-Length: '.strlen($buffer));
	header('Content-Type: '.$meta['type'].$charset_str);
	echo $buffer;
	die();
	}

?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv='content-type' value='text/html; charset=utf-8' />
	<title>phpmaildirclass demo for user <?php echo htmlspecialchars($_user,ENT_NOQUOTES|ENT_HTML5,'utf-8'); ?></title>
	<link rel='stylesheet' type='text/css' href='pmdc.css' />
</head>
<body>
<div class='main'>
<h1>phpmaildirclass demo</h1>
<div class='main_inner'>

<div class='folder_select'>
<form action='pmdc-demo.php' method='get'>
<input type='hidden' name='action' value='view_folder' />
<select name='folder'>
<?php
	foreach ( $webmail_folders as $folder=>$data ){
		if ( $data[1] !== '*' && !in_array($_user,explode(',',$data[1]),true) )
		continue;
	$is_current = ( $_folder === $folder ) ? ' selected="selected"' : '';
	echo '<option value=\''.urlencode($folder).'\''.$is_current.'>'.htmlspecialchars($data[0]).'</option>'.chr(10);
	}
unset($folder,$data,$is_current);
?>
</select>
<input type='submit' value='Open' />
</form>
</div>

<div class='username'>
<?php echo htmlspecialchars($_user,ENT_NOQUOTES|ENT_HTML5,'utf-8'); ?>
</div>

<br /><br /><hr />

<table>
	<tr>
		<th>Von</th>
		<th>Datum</th>
		<th>Betreff</th>
		<th>Größe</th>
	</tr>
<?php
	foreach ( $webmail_stack as $mail_key=>$mail_file ){
	$color = ( $mail_key%2 === 0 ) ? 'light' : 'dark';
	$hilight = ( $_mid === $mail_key ) ? 'current' : '';
	$mail = new Mail_Parser;
		if ( !$mail->process($mail_file,Mail_Parser::PARSE_HEADER_ONLY) )
		continue;
	$header = $mail->fetch('header');
	$body = array();
	$meta = array();
	$from = $mail->decode_address_line($mail->get_header_line($header,'from'));
	$date = $mail->get_header_line($header,'date');
	$subject = $mail->get_header_line($header,'subject');
		if ( strlen($subject) === 0 )
		$subject = '(keine Betreffszeile angegeben)';
	$mail->decode_string($subject,$meta);
	echo "\t<tr class='".$color."'>\n";
	echo "\t\t<td>".htmlspecialchars($from[0],ENT_NOQUOTES|ENT_HTML5,'utf-8')."</td>\n";
	echo "\t\t<td>".strftime('%a, %d %b %Y %H:%M',strtotime($date))."</td>\n";
	echo "\t\t<td class='".$hilight."'><a href='pmdc-demo.php?action=view_mail&amp;folder=".urlencode($_folder)."&amp;mid=".$mail_key."'>".htmlspecialchars($subject,ENT_NOQUOTES|ENT_HTML5,'utf-8')."</a></td>\n";
	echo "\t\t<td>".$mail->human_readable_byte_size(filesize($mail_file))."</td>\n";
	echo "\t</tr>\n";
	unset($mail,$header,$body,$meta,$from,$date,$subject);
	}
?>
</table>
<hr />
<?php
	if ( is_integer($_mid) && isset($webmail_stack[$_mid]) ){
	$mail = new Mail_Parser;
	$mail->process($webmail_stack[$_mid]);
	$header = $mail->fetch('header');
	$body = $mail->fetch('body');
	$meta = array();
	//$mail->fetch('debug');
	//D($body);
	$from = $mail->decode_address_line($mail->get_header_line($header,'from'));
	$to = $mail->decode_address_line($mail->get_header_line($header,'to'));
	$date =  $mail->get_header_line($header,'date');
	$subject =  $mail->get_header_line($header,'subject');
		if ( strlen($subject) === 0 )
		$subject = '(keine Betreffszeile angegeben)';
	$mail->decode_string($subject,$meta);
	echo "\t<div class='itembox'><strong>Von:</strong> ".htmlspecialchars($from[0].' <'.$from[1].'>',ENT_NOQUOTES|ENT_HTML5,'utf-8')."</div>\n";
	echo "\t<div class='itembox'><strong>An:</strong> ".htmlspecialchars($to[0].' <'.$to[1].'>',ENT_NOQUOTES|ENT_HTML5,'utf-8')."</div>\n";
	echo "\t<div class='itembox'><strong>Datum:</strong> ".strftime('%a, %d %b %Y %H:%M',strtotime($date))."</div>\n";
	echo "\t<div class='itembox'><strong>Betreff:</strong> ".htmlspecialchars($subject,ENT_NOQUOTES|ENT_HTML5,'utf-8')."</div>\n";
	echo "\t<div class='textbox'>\n";
	echo "\t\t<div class='textbox_head'>Quellcode-Ansicht</div>\n";
	echo "\t\t<a href='pmdc-demo.php?action=view_part&amp;folder=".urlencode($_folder)."&amp;mid=".$_mid."&amp;part=255' target='_blank'>In neuem Fenster öffnen</a>\n";
	echo "\t</div>\n";
		foreach ( $body as $body_id=>$body_part ){
		$meta = $mail->determine_meta_of_part($header,$body_part);
			if ( $meta['type'] === 'text/plain' ){
			$message = $mail->fetch('body',$body_id);
			$mail->decode_string($message,$meta);
			echo "\t<div class='textbox'>\n";
			echo "\t\t<div class='textbox_head'>".$mail->human_readable_content_type($meta['type'])." (".$mail->human_readable_byte_size($body_part['length']).")</div>\n";
			echo "\t\t<pre class='textbox_body'>".str_replace("\n"," &#x23ce;\n",htmlspecialchars($message,ENT_NOQUOTES|ENT_HTML5,'utf-8'))."</pre>\n";
			echo "\t</div>\n";
			}
			else{
			echo "\t<div class='textbox'>\n";
			echo "\t\t<div class='textbox_head'>".$mail->human_readable_content_type($meta['type'])." (".$mail->human_readable_byte_size($body_part['length']).")</div>\n";
			echo "\t\t<a href='pmdc-demo.php?action=view_part&amp;folder=".urlencode($_folder)."&amp;mid=".$_mid."&amp;part=".$body_id."' target='_blank'>In neuem Fenster öffnen</a>\n";
			echo "\t</div>\n";
			}
		}
	unset($mail,$header,$body,$body_id,$body_part,$from,$to,$date,$subject,$meta);
	}
?>
</div><!-- main_inner -->
<div class="footer">phpmaildirclass demo - <?php echo '('.round(microtime(true)-$_TIME,3).'s)'; ?></div>
</div>
</body>
</html>
<?php
//}
?>