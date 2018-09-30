<?php
/* =====================================================================
This file is part of "phpmaildirclass"
https://github.com/AnanasPfirsichSaft/phpmaildirclass

MIT License

Copyright (c) 2018 AnanasPfirsichSaft

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE. */
class Mail_Parser_l10n {
static public $version = 1;
static public $lang = 'de';
static protected $data = array(
'MIME_TEXT_PLAIN'=>array('de'=>'Einfacher Text','en'=>'Simple Text'),
'MIME_TEXT_HTML'=>array('de'=>'HTML-Dokument','en'=>'HTML Document'),
'MIME_IMAGE_GIF'=>array('de'=>'GIF-Bild','en'=>'GIF Image'),
'MIME_IMAGE_JPEG'=>array('de'=>'JPEG-Bild','en'=>'JPEG Image'),
'MIME_IMAGE_PNG'=>array('de'=>'PNG-Bild','en'=>'PNG Image'),
);
	static function translate($key){
		if ( isset(self::$data[$key]) )
		return self::$data[$key][self::$lang];
		else
		return '((l10n:'.$key.' MISSING))';
	}
}

class Mail_Parser {
const PARSE_HEADER_ONLY = 0x01;
public $charset_output = 'utf-8';
protected $debug_trace = array();
protected $mail_file = '';
protected $max_size = 33554432;
protected $max_parts = 64;
protected $chunk_size = 65535;
protected $boundary_stack = array();
protected $boundary_closed = array();
protected $parsed_envelope_header = array();
protected $parsed_body = array();

	function __construct(){
	$this->mail_file = '';
	$this->debug_trace = array(microtime(true),memory_get_usage(),'');
	$this->boundary_stack = array();
	$this->boundary_closed = array();
	$this->parsed_envelope_header = array();
	$this->parsed_body = array();
	}

	function fetch($key,$part=false){
		if ( $key === 'header' )
		return $this->parsed_envelope_header;
		elseif ( $key === 'body' ){
			if ( $part === false )
			return $this->parsed_body;
			else{
				if ( isset($this->parsed_body[$part]) )
				return trim(file_get_contents($this->mail_file,null,null,$this->parsed_body[$part]['offset'],$this->parsed_body[$part]['length']));
				else
				return false;
			}
		}
		elseif ( $key === 'debug' ){
		echo '<pre>'.chr(10);
			for ( $k = 3 ; $k < sizeof($this->debug_trace) ; ){
			echo ' %%% <strong><big>time='.round($this->debug_trace[$k]-$this->debug_trace[0],6).' mem='.$this->debug_trace[$k+1].'</big></strong>'.chr(10);
			echo $this->debug_trace[$k+2].chr(10);
			$k += 3;
			}
		echo '</pre>'.chr(10);
		}
		else
		return false;
	}

	function process($file,$flags=0x00){
		if ( !file_exists($file) || !is_readable($file) ){
		array_push($this->debug_trace,microtime(true),memory_get_usage(),'FILE CANNOT BE READ');
		return false;
		}
	$abs_pointer = 1;
	$abs_start = 0;
	$abs_end = $this->chunk_size;
	$this->mail_file = $file;
	$file_size = filesize($file);
	$mail_eol = '';
		if ( $file_size > $this->max_size )
		return false;
	array_push($this->debug_trace,microtime(true),memory_get_usage(),'PARSER STARTED');
	array_push($this->debug_trace,microtime(true),memory_get_usage(),'file has '.$file_size.' bytes in size, chunks are '.$this->chunk_size.' bytes');

		while ( true ){
		array_push($this->debug_trace,microtime(true),memory_get_usage(),'START OF CHUNK #'.$abs_pointer.' @'.sprintf('%08d',$abs_start).'-'.sprintf('%08d',$abs_end));
		$main_buffer = file_get_contents($file,null,null,$abs_start,$this->chunk_size);
		$rel_start = 0;

			if ( sizeof($this->parsed_envelope_header) === 0 && strlen($main_buffer) > 0 ){
				if ( strpos($main_buffer,"\n\n") > 0 )
				$mail_eol = "\n";
				elseif ( strpos($main_buffer,"\r\r") > 0 )
				$mail_eol = "\r";
				else
				$mail_eol = "\r\n";
			array_push($this->debug_trace,microtime(true),memory_get_usage(),'end of line is '.str_replace(array("\r","\n"),array('%CR','%LF'),$mail_eol));
			$splitter = strpos($main_buffer,$mail_eol.$mail_eol);
				if ( $splitter > 0 ){
				$local_header = substr($main_buffer,0,$splitter);
				$this->parsed_envelope_header = $this->parse_header_lines($local_header);
				$rel_start = $splitter;
				}
				if ( $flags&Mail_Parser::PARSE_HEADER_ONLY )
				return true;
			$multipart = $this->look_for_multipart();
			array_push($this->debug_trace,microtime(true),memory_get_usage(),'multipart scan result is '.$multipart);
				if ( sizeof($this->boundary_stack) === 0 ){
				$part_data = array(
				'offset'=>$splitter,
				'length'=>$file_size-$splitter
				);
				array_push($this->parsed_body,$part_data);
				}
			unset($splitter,$local_header,$multipart);
			}

			while ( sizeof($this->boundary_stack) > 0 && strlen($main_buffer) > 0 ){
			$boundary = array_shift($this->boundary_stack);
			$found_start = strpos($main_buffer,'--'.$boundary,$rel_start);

				if ( is_integer($found_start) && strlen($boundary) > 2 ){
				$found_end = strpos($main_buffer,$mail_eol.$mail_eol,$found_start);
					if ( !is_integer($found_end) )
					$found_end = $file_size;
				$found_len = $found_end-$found_start;
				array_push($this->debug_trace,microtime(true),memory_get_usage(),'FOUND BOUNDARY '.$boundary.' #='.$abs_pointer.' start='.$found_start.' len='.$found_len.' end='.$found_end);

				$local_header_raw = substr($main_buffer,$found_start,$found_len);
				array_push($this->debug_trace,microtime(true),memory_get_usage(),'FOUND HEADER '.print_r($local_header_raw,true));
				$local_header = $this->parse_header_lines($local_header_raw);
				$local_type = $this->parse_multivar_line($this->get_header_line($local_header,'content-type'));
				array_push($this->debug_trace,microtime(true),memory_get_usage(),'PARSED HEADER '.print_r($local_header,true));
				array_push($this->debug_trace,microtime(true),memory_get_usage(),'PARSED TYPE '.print_r($local_type,true));

					if ( is_string($local_type['#']) && substr($local_type['#'],0,10) === 'multipart/' ){
					$this->remove_quotes($local_type['boundary']);
					array_unshift($this->boundary_stack,$boundary);
					array_unshift($this->boundary_stack,$local_type['boundary']);
					array_push($this->debug_trace,microtime(true),memory_get_usage(),'PUSHING STACK '.$local_type['boundary']);
					array_push($this->debug_trace,microtime(true),memory_get_usage(),'PUSHING STACK '.$boundary);
					}
					else{
						if ( strpos($local_header_raw,$boundary.'--') === false ){
						$last_part = array_keys($this->parsed_body);
						$last_part = array_pop($last_part);
							if ( is_integer($last_part) && $this->parsed_body[$last_part]['length'] === -1 )
							$this->parsed_body[$last_part]['length'] = ($abs_start+$found_start)-$this->parsed_body[$last_part]['offset'];
						$part_data = array_merge($local_header,
						array(
						'offset'=>$abs_start+$found_end,
						'length'=>-1
						));
							if ( sizeof($this->parsed_body) < $this->max_parts )
							array_push($this->parsed_body,$part_data);
						array_unshift($this->boundary_stack,$boundary);
						array_push($this->debug_trace,microtime(true),memory_get_usage(),'STORE MAIL PART '.print_r($part_data,true));
						array_push($this->debug_trace,microtime(true),memory_get_usage(),'PUSHING STACK '.print_r($boundary,true));
						}
						else{
						$last_part = array_keys($this->parsed_body);
						$last_part = array_pop($last_part);
							if ( is_integer($last_part) && $this->parsed_body[$last_part]['length'] === -1 )
							$this->parsed_body[$last_part]['length'] = ($abs_start+$found_start)-$this->parsed_body[$last_part]['offset'];
						array_push($this->boundary_closed,$boundary);
						array_push($this->debug_trace,microtime(true),memory_get_usage(),'CLOSING TAG FOUND '.$boundary);
						}
					}

				array_push($this->debug_trace,microtime(true),memory_get_usage(),'BOUNDARY STACK '.print_r($this->boundary_stack,true));
				array_push($this->debug_trace,microtime(true),memory_get_usage(),'PUSHING POINTER TO #='.$abs_pointer.' rel='.$found_end.' abs='.($abs_start+$found_end));
				$rel_start = $found_end;
				}
				else{
					if ( !isset($this->boundary_closed[$boundary]) ){
					array_push($this->boundary_stack,$boundary);
					array_push($this->debug_trace,microtime(true),memory_get_usage(),'CLOSING TAG PENDING '.$boundary);
					}
				$rel_start = $this->chunk_size;
				array_push($this->debug_trace,microtime(true),memory_get_usage(),'END OF CHUNK #'.$abs_pointer);
				}

				if ( $rel_start === $this->chunk_size )
				break;
			unset($found_start,$found_len,$found_end,$boundary,$local_header,$local_header_raw,$local_type,$part_data,$last_part);
			}

		unset($main_buffer);
			if ( $abs_end > $file_size ){
			array_push($this->debug_trace,microtime(true),memory_get_usage(),'PARSER COMPLETED');
			break;
			}
		$abs_pointer++;
		$abs_start = $abs_end+1;
		$abs_end = $abs_pointer*$this->chunk_size;
		}

	return true;
	}

	function parse_header_lines($buffer){
	$matches = array();
	$buffer = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]+/','?',$buffer);
	$buffer = preg_replace('/\x0d{0,1}\x0a{1}([\x09|\x20]+)/',chr(0x09),$buffer);
	$buffer = explode(chr(10),$buffer);
		foreach ( $buffer as $value ){
		$split = strpos($value,':');
		$key = strtolower(substr($value,0,$split));
		$value = trim(str_replace(chr(0x09),chr(0x0a),substr($value,$split+1)));
			if ( strlen($key) < 2 )
			continue;
			if ( in_array($key,array('offset','length'),true) )
			continue;
			if ( !isset($matches[$key]) )
			$matches[$key] = array();
		array_push($matches[$key],$value);
		}
	ksort($matches);
	return $matches;
	}

	protected function look_for_multipart(){
	$type = $this->get_header_line($this->parsed_envelope_header,'content-type');
		if ( !$type )
		return 0;
	$type = $this->parse_multivar_line($type);
		if ( substr($type['#'],0,10) === 'multipart/' && isset($type['boundary']) ){
		$this->remove_quotes($type['boundary']);
		array_push($this->boundary_stack,$type['boundary']);
		return $type['boundary'];
		}
	return -1;
	}

	function get_header_line(&$headers,$key){
		if ( isset($headers[$key]) ){
			if ( sizeof($headers[$key]) === 1 )
			return $headers[$key][0];
			else
			return $headers[$key];
		}
		else
		return false;
	}

	function parse_multivar_line($buffer){
	$matches = array();
	$splitter = strpos($buffer,';');
		if ( $splitter > 0 ){
		$matches['#'] = substr($buffer,0,$splitter);
		$stack = explode(',',substr($buffer,$splitter+1));
			foreach ( $stack as $value ){
			$value = $this->make_key_value_pair(trim($value));
				if ( is_string($value[0]) )
				$matches[$value[0]] = $value[1];
			}
		return $matches;
		}
		else{
			if ( sizeof($matches) === 0 )
			$matches = '';
		return array('#'=>$matches);
		}
	}

	protected function make_key_value_pair($buffer){
	$pair = array(false,false);
	$splitter = strpos($buffer,'=');
		if ( $splitter > 0 ){
		$key = substr($buffer,0,$splitter);
		$value = substr($buffer,$splitter+1);
		$pair[0] = $key;
		$pair[1] = $value;
		}
		else
		$pair[1] = $buffer;
	return $pair;
	}

	protected function remove_quotes(&$buffer){
		if ( in_array(substr($buffer,0,1),array("'",'"'),true) )
		$buffer = substr($buffer,1);
		if ( in_array(substr($buffer,-1),array("'",'"'),true) )
		$buffer = substr($buffer,0,-1);
	}

	protected function decode_mime_string(&$buffer){
	$buffer = preg_replace_callback('/=\?([a-z0-9\-]+)\?(b|q)\?(.+?)\?=/im',array('Mail_Parser','decode_mime_string_callback'),$buffer);
	}

	protected function decode_mime_string_callback(&$matches){
	// full-string,charset,type,string
		if ( is_array($matches) && sizeof($matches) === 4 ){
			if ( strtolower($matches[2]) === 'b' )
			$matches[3] = base64_decode($matches[3]);
			elseif ( strtolower($matches[2]) === 'q' )
			$matches[3] = quoted_printable_decode($matches[3]);
			if ( strtolower($matches[1]) !== $this->charset_output )
			$matches[3] = iconv($matches[1],$this->charset_output,$matches[3]);
		return $matches[3];
		}
		else
		return $matches[0];
	}

	function decode_address_line($buffer){
	$parsed = array('','');
		if ( is_array($buffer) && sizeof($buffer) === 1 && isset($buffer[0]) )
		$buffer = $buffer[0];
	preg_match('/[^<@"\' ]+@[a-z0-9:_\-\.]+/i',$buffer,$matches);
		if ( !isset($matches[0]) )
		$matches[0] = '';
		if ( is_array($matches) && strlen($matches[0]) > 6 ){
		$parsed[1] = $matches[0];
		$buffer = str_replace($matches[0],'',$buffer);
		}
		else
		$parsed[1] = 'no.local.part@invalid';
	$buffer = str_replace(array('"',"'",'<>'),array('','',''),$buffer);
	$parsed[0] = trim($buffer);
	$this->decode_mime_string($parsed[0]);
		if ( strlen($parsed[0]) === 0 )
		$parsed[0] = substr($parsed[1],0,strpos($parsed[1],'@'));
	return $parsed;
	}

	function determine_meta_of_part(&$header,&$body_part){
	$main_encoding = $this->get_header_line($header,'content-transfer-encoding');
	$main_content = $this->parse_multivar_line($this->get_header_line($header,'content-type'));
	$main_type = 'text/plain';
	$main_charset = 'us-ascii';
		if ( isset($main_content['#']) && strlen($main_content['#']) > 4 ){
		$main_type = $main_content['#'];
			if ( isset($main_content['charset']) && strlen($main_content['charset']) > 4 )
			$main_charset = $main_content['charset'];
		}
		if ( !is_string($main_encoding) )
		$main_encoding = '8bit';
	$part_encoding = $this->get_header_line($body_part,'content-transfer-encoding');
	$part_content = $this->parse_multivar_line($this->get_header_line($body_part,'content-type'));
		if ( isset($part_content['#']) && strlen($part_content['#']) > 4 )
		$part_type = $part_content['#'];
		else
		$part_type = $main_type;
		if ( isset($part_content['charset']) && strlen($part_content['charset']) > 4 )
		$part_charset = $part_content['charset'];
		else
		$part_charset = $main_charset;
		if ( !is_string($part_encoding) )
		$part_encoding = $main_encoding;
	return array('type'=>$part_type,'charset'=>$part_charset,'encoding'=>$part_encoding);
	}

	function decode_string(&$buffer,&$meta){
		if ( sizeof($meta) === 3 ){
			if ( isset($meta['encoding']) && strtolower($meta['encoding']) === 'base64' )
			$buffer = base64_decode($buffer);
			if ( isset($meta['encoding']) && strtolower($meta['encoding']) === 'quoted-printable' )
			$buffer = quoted_printable_decode($buffer);
			if ( isset($meta['charset']) && substr($meta['type'],0,5) === 'text/'
						&& strtolower($meta['charset']) !== $this->charset_output )
			$buffer = iconv($meta['charset'],$this->charset_output,$buffer);
		}
		else
		$this->decode_mime_string($buffer);
	}

	function human_readable_byte_size($buffer){
		if ( $buffer > pow(2,30) )
		return round($buffer/pow(2,30),1).' GB';
		elseif ( $buffer > pow(2,20) )
		return round($buffer/pow(2,20),1).' MB';
		elseif ( $buffer > pow(2,10) )
		return round($buffer/pow(2,10),1).' KB';
		else
		return $buffer.' Bytes';
	}

	function human_readable_content_type($buffer){
		if ( $buffer === 'text/plain' )
		return Mail_Parser_l10n::translate('MIME_TEXT_PLAIN');
		elseif ( $buffer === 'text/html' )
		return Mail_Parser_l10n::translate('MIME_TEXT_HTML');
		elseif ( $buffer === 'image/gif' )
		return Mail_Parser_l10n::translate('MIME_IMAGE_GIF');
		elseif ( $buffer === 'image/jpg' )
		return Mail_Parser_l10n::translate('MIME_IMAGE_JPEG');
		elseif ( $buffer === 'image/jpeg' )
		return Mail_Parser_l10n::translate('MIME_IMAGE_JPEG');
		elseif ( $buffer === 'image/png' )
		return Mail_Parser_l10n::translate('MIME_IMAGE_PNG');
		else
		return $buffer;
	}

}
?>