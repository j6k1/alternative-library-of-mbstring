<?php
/**
 * mbString
 *
 * @package mbString - multibyte-string-library
 * @version 0.0.1-alpha
 * @author jinpu <http://will-co21.net>
 * @lisence The LGPL License
 * @copyright Copyright 2012 jinpu. All rights reserved.
 */

/**
 * The mbString Class.
 */
class mbString
{
	private function __construct()
	{
	
	}

	public static function convertToUTF8FromSJIS($str)
	{
		return preg_replace_callback(
			'/(?:[\x00-\x7F\xA1-\xDF]|(?:[\x81-\x9F\xE0-\xFC][\x40-\x7E\x80-\xFC]))/',
			array("mbString", "convertToUTF8FromSJISInner"),
			$str);
	}
	
	private static function convertToUTF8FromSJISInner($match)
	{
		static $codetable;
		
		if(!isset($codetable))
		{
			$codetable = mbString_CodeTables::$SjisToUTF8;
		}
		
		if(isset($codetable[$match[0]]))
		{
			return $codetable[$match[0]];
		}
		else if($match[0] == "\x0A" || $match[0] == "\x0D")
		{
			return $match[0];
		}
		else
		{
			return '\\x' . bin2hex($match[0]);
		}
	}
	
	public static function convertToSJISFromUTF8($str)
	{
		return preg_replace_callback(
			'/([\x00-\x2E\x30-\x7F]|([\xC0-\xDF][\x80-\xBF])|([\xE0-\xEF][\x80-\xBF]{2})|([\xF0-\xF7][\x80-\xBF]{3}))/',
			array("mbString", "convertToSJISFromUTF8Inner"),
			$str);
	}
	
	private static function convertToSJISFromUTF8Inner($match)
	{
		static $codetable;
		
		if(!isset($codetable))
		{
			$codetable = array_flip(mbString_CodeTables::$SjisToUTF8);
		}
		
		if(isset($codetable[$match[0]]))
		{
			return $codetable[$match[0]];
		}
		else if($match[0] == "\x0A" || $match[0] == "\x0D")
		{
			return $match[0];
		}
		else
		{
			return '\\u' . bin2hex($match[0]);
		}
	}

	public static function init()
	{
		$codetable_dir_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR. "table";

		if(!file_exists($codetable_dir_path) || !is_dir($codetable_dir_path))
		{
			throw new Exception("コード変換テーブルファイル格納ディレクトリが見つかりませんでした。");
		}

		$codetable_path = $codetable_dir_path . DIRECTORY_SEPARATOR. "mbString_CodeTables.php";
	
		if(!file_exists($codetable_path))
		{
			$tables = array();
		
			$table = self::parseSJIS(
				dirname( __FILE__ ) . DIRECTORY_SEPARATOR. "SHIFTJIS.TXT");
			
			$tables["SjisToUTF8"] = self::serializeCodeTable($table);
			
			$table = self::parseCP932(
				dirname( __FILE__ ) . DIRECTORY_SEPARATOR. "CP932.TXT");

			$tables["SjisWinToUTF8"] = self::serializeCodeTable($table);
			
			self::saveCodeTables($tables);
		}
		
		require_once($codetable_path);
		
		return true;
	}
	
	private static function parseToUTF8Table($tablepath)
	{
		$table = array();
		
		$lines = file($tablepath);
		
		if($lines === false)
		{
			throw new Exception("{$cp932path}の読み込みに失敗しました。");
		}
		
		foreach($lines as $line)
		{
			if(strpos($line, "#") === 0)
			{
				continue;
			}
			
			list($sjiscode, $unicode) = explode("\t", $line);
			
			if(preg_match('/^0x([\dA-F][\dA-F]){2}$/i', $unicode) == 0)
			{
				continue;
			}
			else if(preg_match('/^0x(([\dA-F][\dA-F]){1,2})$/i', $sjiscode) == 0)
			{
				continue;
			}

			list(,$sjiscode) = explode("x", $sjiscode);
			list(,$unicode) = explode("x", $unicode);
			
			$sjiscode = hexdec($sjiscode);
			$unicode = hexdec($unicode);
			
			if($unicode <= 0x7F)
			{
				$table[$sjiscode] = pack('C1', $unicode);
			} 
			else if($unicode <= 0x7FF)
			{
				$table[$sjiscode] = pack('C2', (0xC0 | ($unicode >> 6)), 
					0x80 | ($unicode & 0x3F));
			}
			else
			{
				$table[$sjiscode] = pack('C3', (0xE0 | ($unicode >> 12)), 
					0x80 | (($unicode >> 6) & 0x3F), 
					0x80 | ($unicode & 0x3F));
			}
		}
		
		return $table;
	}
	
	private static function parseSJIS($sjispath)
	{
		return self::parseToUTF8Table($sjispath);
	}
	
	private static function parseCP932($cp932path)
	{
		return self::parseToUTF8Table($cp932path);
	}
	
	private static function  serializeToHexStringFromInt($val)
	{
		$val = sprintf("%X", $val);
		$str = '"';
		
		if((strlen($val) % 2) != 0)
		{
			$val = "0{$val}";
		}
		
		$len = strlen($val);
		
		for($i=0; $i < $len ; $i+=2)
		{
			$str .= '\\x' . substr($val, $i, 2);
		}
		
		$str .= '"';
		
		return $str;
	}
	
	private static function serializeBinString($val)
	{
		$hexstrs = unpack('C*', $val);
		$str = '"';
		
		foreach($hexstrs as $hex)
		{
			$str .= '\\x';
			$str .= sprintf("%02X", $hex);
		}
		$str .= '"';

		return $str;
	}
	
	private static function serializeCodeTable($table)
	{
		$lines = array();
		
		foreach($table as $key => $val)
		{
			
			$line = self::serializeToHexStringFromInt($key);
			$line .= " => ";
			$line .= self::serializeBinString($val);
		
			$lines[] = $line;
		}
		
		return $lines;
	}
	
	private static function saveCodeTables($serializedTables)
	{
		$code = <<<__EOM__
<?php
class mbString_CodeTables
{

__EOM__;
		foreach($serializedTables as $name => $lines)
		{
			$code .= "\tpublic static \${$name} = array(";
			$comma = "";
			
			foreach($lines as $line)
			{
				$code .= "{$comma}\n\t\t{$line}";
				$comma = ",";
			}
			
			$code .= "\n\t);\n\n";
		}
		
		$code .= <<<__EOM__
}
?>
__EOM__;

		$outpath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR. "table" . DIRECTORY_SEPARATOR. "mbString_CodeTables.php";
		$fp = @fopen($outpath, "w");
		
		if($fp == false)
		{
			throw new Exception("{$outpath}が書き込みモードで開けませんでした。");
		}
		
		if((@fwrite($fp, $code)) === false)
		{
			throw new Exception("{$outpath}への書き込みに失敗しました。");
		}
		
		return true;
	}
}
?>