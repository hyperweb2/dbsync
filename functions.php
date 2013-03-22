 <?php defined("DBSYNC_EXEC") or die("dbsync not loaded correctly");

require_once DBSYNC_PATH.'configuration.php';
// define end of line character
$os = strtoupper(substr(php_uname(), 0, 3));
if ($os == 'WIN') {
	define(EOL, "\r\n");
} elseif ($os == 'MAC') {
	define(EOL, "\r");
} else {
	define(EOL, "\n");
}

// manually decode query string...we use - instead of & because Cpanel cron page doesn't like & in commands
$dbid		= 0; // a large number which will cause an error if query string argument is missing
$code		= '';
$bundle		= '';
$filename	= '';
$dir        = $hw2ds_dbpath;


$query = explode('-',$_SERVER['QUERY_STRING']);
for($x = 0; $x < count($query); $x++) {
	$strings =  explode('=', $query[$x]);
	if ($strings[0] == 'dbid') {
		$dbid = $strings[1];
	} elseif ($strings[0] == 'code') {
		$code = $strings[1];
	} elseif ($strings[0] == 'bundle') {
		$bundle = $strings[1];
	} elseif ($strings[0] == 'filename') {
		$filename = $strings[1];
	/*} else {
		$msg = "Invalid argument '$strings[0]' in the query string: ?{$_SERVER['QUERY_STRING']}";
		if ($debug) print "$msg<br />";
		sendLog(1, $msg  . EOL);
    */
	}
}

// shorter variables names means less typing :-)
$dbhost 	= $cfg[$dbid]['dbhost'];
$dbuser 	= $cfg[$dbid]['dbuser'];
$dbpass 	= $cfg[$dbid]['dbpass'];
$dbname 	= $cfg[$dbid]['dbname'];
$dbprefix	= $cfg[$dbid]['dbprefix'];
$optimize 	= $cfg[$dbid]['optimize'];
$inserts 	= $cfg[$dbid]['extended_inserts'];
$options 	= $cfg[$dbid]['options'];

/* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
	Functions
	++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
*/

function fixMysqlString($string, $dblink) {
    if (version_compare(phpversion(), "4.3.0", ">=")):
        return mysql_real_escape_string($string, $dblink);
    else:
        return mysql_real_escape($string);
    endif;
}


function parse_mysql_dump($path,$dbhost, $dbuser, $dbpass, $dbname) {
/*        $file_content = file($path);
        if (!$file_content)
            die ("file doesn't exist");

        $query = "";
        foreach($file_content as $sql_line){
            if(trim($sql_line) != "" && strpos($sql_line, "--") === false && strpos($sql_line, "/*") === false){
            $query .= $sql_line;
                if (substr(rtrim($query), -1) == ';'){
                    $result = mysql_query($query)or die(mysql_error());
                    $query = "";
                }
            }
        }
*/

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
 
if (mysqli_connect_error()) {
    die('Connect Error (' . mysqli_connect_errno() . ') '
            . mysqli_connect_error());
}
 
echo 'Success... ' . $mysqli->host_info . "<br />";
echo 'Retrieving dumpfile' . "<br />";
 
$sql = file_get_contents($path);
if (!$sql){
	die ('Error opening file '.$path);
}
 
echo 'processing file <br />';
mysqli_multi_query($mysqli,$sql);
 
echo 'done.';
$mysqli->close();
}

/**
 * backup the db OR different tables 
 *
 * @param  string $dumpfile filename of file to dump
 * @param  string $dbhost
 * @param  string $dbuser
 * @param  string $dbpass
 * @param  string $dbname
 * @param  string $tables can be an array or a comma separated string list
 * @param  string $isFilter assume $tables as filter list if true
 * @return       2.5
 */
function backup_tables($dumpfile,$dbhost, $dbuser, $dbpass, $dbname,$tables = '*',$isFilter=false)
{

  $dblink = @mysql_connect($dbhost, $dbuser, $dbpass);
  $select = @mysql_select_db($dbname);

    if (!$dblink)
       die('Not connected : ' . mysql_error());

    if (!$select)
       die ('Can\'t use : '.$dbname .' '. mysql_error());
  
  //get all of the tables
  if($isFilter || $tables == '*')
  {
    $sqlTables = array();
    $result = mysql_query('SHOW TABLES');
    while($row = mysql_fetch_row($result))
    {
      $sqlTables[] = $row[0];
    }
  }
  
  if ($tables != '*')
  {
    $passed_tables = is_array($tables) ? $tables : explode(',',$tables);
  }
  
  if ($isFilter)
      $tables=array_intersect ($sqlTables, $passed_tables);
  else
      $tables=$tables != '*' ? $passed_tables : $sqlTables;
      
  //cycle through
  foreach($tables as $table)
  {
    $result = mysql_query('SELECT * FROM '.$table);
    $num_fields = mysql_num_fields($result);
    
    $return.= 'DROP TABLE IF EXISTS '.$table.';';
    $row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
    $return.= "\n\n".$row2[1].";\n\n";
    
    for ($i = 0; $i < $num_fields; $i++) 
    {
      while($row = mysql_fetch_row($result))
      {
        $return.= 'INSERT INTO '.$table.' VALUES(';
        for($j=0; $j<$num_fields; $j++) 
        {
          $string = fixMysqlString($row[$j],$dblink);
          if (isset($string)) { $return.= '"'.$string.'"' ; } else { $return.= '""'; }
          if ($j<($num_fields-1)) { $return.= ','; }
        }
        $return.= ");\n";
      }
    }
    $return.="\n\n\n";
  }

  mysql_close($dblink); 
  
  //save file
  $zhandler=gzopen($dumpfile.'.gz', 'wb'); 
  gzwrite($zhandler, $return);
  gzclose($zhandler);  
}


function uncompress($srcName, $dstName) {
    $sfp = gzopen($srcName, "rb");
    if (!$sfp)
        die($srcName." doesn't exist");

    $fp = fopen($dstName, "w");

    while ($string = gzread($sfp, 4096)) {
        fwrite($fp, $string, strlen($string));
    }
    gzclose($sfp);
    fclose($fp);
}

// download a dump file
function downloadFile($filename) {
	global $dir;
	
	$handle = @fopen($filename, 'r');
	if (!$handle) {
		sendLog(1, "Can't open file: $filename. Download cancelled."  . EOL);
	}
	
	ob_end_clean();
  
	header("Content-type: application/force-download");
	header("Content-Disposition: attachment; filename=\"$filename\"");
	
	while(!feof($handle) and (connection_status()==0)) {
		print(fread($handle, 1024*8));
		flush();
	}
	fclose($handle);		
	if ($delete_file == 2) unlink($file);
}

// download all backup files in one archive
function sendBundle() {
	global $dir, $bundle_name, $cfg;
	
	if (!$dh = opendir($dir)) exit("Attempt to open directory $dir failed");
	
	$time				= date('Ymd-Hi');
	$bundle_name	= $bundle_name . '_' . $time . '.zip';
	$archive			= new zipfile();  
	$archive -> add_dir("mybackup/");
	
	// for each set of configuration entries
	$files_message		= '';
	$fa = 0;
	for($x = 0; $x <= count($cfg); $x++) {
		rewinddir ($dh);
		$buffer = '';
		while (false !== ($file = readdir($dh))) {
			if (!is_dir($file) && strstr($file, $cfg[$x]['dbname'])) {
				$fh = fopen($dir . $file, 'rb');
				if (!$fh) exit("Attempt to read $file failed");
				$filesize   = sprintf("%u", filesize($dir . $file));
				$filedata   = fread($fh, $filesize);
				fclose($fh);
				$archive -> add_file($filedata, "mybackup/$file");
				$fa++;				
			}
		}
	}
	
	if (!$fa) exit('No files to download');

	ob_end_clean();
	
	header("Content-type: application/force-download");
	header("Content-Disposition: attachment; filename=\"$bundle_name\""); 
	print $archive -> file();  

}

// send email
function sendLog($die, $body) {
	global $from, $to, $dbname, $dbid;
	
	$headers = "From: $from" . EOL
	. "Content-Type: text/plain; charset=utf-8" . EOL 
	. "Content-Transfer-Encoding: 8bit" . EOL;
	
	ini_set('sendmail_from', $from);
	mail($to, "Message from {$_SERVER['PHP_SELF']}, dbname= '$dbname'", $body, $headers);
	if ($die) exit($body);
}

function deleteFiles() {
	global $dir, $dbname;
	
	if (!$handle = @opendir($dir)) {
		$msg = "Error, can't open the files directory: '$dir'" . EOL;
		sendLog(1, $msg  . EOL);
	} else {
		$msg = '';
		while (false !== ($del_file = readdir($handle))) {
            $match = $dbname.'_latest';
			if (!is_dir($del_file) && preg_match("#$match#", $del_file)) {
				$result = unlink($dir . $del_file);
				if (!$result) {
					$msg .= "Attempt to delete $dir$del_file was unsuccessful" . EOL;
					if ($debug) $msg .= "<br />";
				} else {
					if ($debug) print "File deleted: $dir$del_file<br />";
				}
			}
		}
		
	}
	if ($msg) {
		if ($debug) print "$msg<br />";
		sendLog(0, $msg  . EOL);
	}
}

/*
Zip file creation class
by Eric Mueller
http://www.themepark.com
initial version with:
  - class appearance
  - add_file() and file() methods
  - gzcompress() output hacking
by Denis O.Philippov, webmaster@atlant.ru, http://www.atlant.ru
official ZIP file format: http://www. // pkware.com/appnote.txt
*/

class zipfile  
{  

    var $datasec = array(); // array to store compressed data
    var $ctrl_dir = array(); // central directory   
    var $eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record
    var $old_offset = 0;

    function add_dir($name)   

    // adds "directory" to archive - do this before putting any files in directory!
    // $name - name of directory... like this: "path/"
    // ...then you can add files using add_file with names like "path/file.txt"
    {  
        $name = str_replace("\\", "/", $name);  

        $fr = "\x50\x4b\x03\x04";
        $fr .= "\x0a\x00";    // ver needed to extract
        $fr .= "\x00\x00";    // gen purpose bit flag
        $fr .= "\x00\x00";    // compression method
        $fr .= "\x00\x00\x00\x00"; // last mod time and date

        $fr .= pack("V",0); // crc32
        $fr .= pack("V",0); //compressed filesize
        $fr .= pack("V",0); //uncompressed filesize
        $fr .= pack("v", strlen($name) ); //length of pathname
        $fr .= pack("v", 0 ); //extra field length
        $fr .= $name;  
        // end of "local file header" segment

        // no "file data" segment for path

        // "data descriptor" segment (optional but necessary if archive is not served as file)
        $fr .= pack("V",$crc); //crc32
        $fr .= pack("V",$c_len); //compressed filesize
        $fr .= pack("V",$unc_len); //uncompressed filesize

        // add this entry to array
        $this -> datasec[] = $fr;

        $new_offset = strlen(implode("", $this->datasec));

        // ext. file attributes mirrors MS-DOS directory attr byte, detailed
        // at http://support.microsoft.com/support/kb/articles/Q125/0/19.asp

        // now add to central record
        $cdrec = "\x50\x4b\x01\x02";
        $cdrec .="\x00\x00";    // version made by
        $cdrec .="\x0a\x00";    // version needed to extract
        $cdrec .="\x00\x00";    // gen purpose bit flag
        $cdrec .="\x00\x00";    // compression method
        $cdrec .="\x00\x00\x00\x00"; // last mod time & date
        $cdrec .= pack("V",0); // crc32
        $cdrec .= pack("V",0); //compressed filesize
        $cdrec .= pack("V",0); //uncompressed filesize
        $cdrec .= pack("v", strlen($name) ); //length of filename
        $cdrec .= pack("v", 0 ); //extra field length   
        $cdrec .= pack("v", 0 ); //file comment length
        $cdrec .= pack("v", 0 ); //disk number start
        $cdrec .= pack("v", 0 ); //internal file attributes
        $ext = "\x00\x00\x10\x00";
        $ext = "\xff\xff\xff\xff";  
        $cdrec .= pack("V", 16 ); //external file attributes  - 'directory' bit set

        $cdrec .= pack("V", $this -> old_offset ); //relative offset of local header
        $this -> old_offset = $new_offset;

        $cdrec .= $name;  
        // optional extra field, file comment goes here
        // save to array
        $this -> ctrl_dir[] = $cdrec;  

         
    }

    function add_file($data, $name)   

    // adds "file" to archive   
    // $data - file contents
    // $name - name of file in archive. Add path if your want

    {  
        $name = str_replace("\\", "/", $name);  
        //$name = str_replace("\\", "\\\\", $name);

        $fr = "\x50\x4b\x03\x04";
        $fr .= "\x14\x00";    // ver needed to extract
        $fr .= "\x00\x00";    // gen purpose bit flag
        $fr .= "\x08\x00";    // compression method
        $fr .= "\x00\x00\x00\x00"; // last mod time and date

        $unc_len = strlen($data);  
        $crc = crc32($data);  
        // $zdata = gzcompress($data);  it is already compressed
        $zdata = substr( substr($zdata, 0, strlen($zdata) - 4), 2); // fix crc bug
        $c_len = strlen($zdata);  
        $fr .= pack("V",$crc); // crc32
        $fr .= pack("V",$c_len); //compressed filesize
        $fr .= pack("V",$unc_len); //uncompressed filesize
        $fr .= pack("v", strlen($name) ); //length of filename
        $fr .= pack("v", 0 ); //extra field length
        $fr .= $name;  
        // end of "local file header" segment
         
        // "file data" segment
        $fr .= $zdata;  

        // "data descriptor" segment (optional but necessary if archive is not served as file)
        $fr .= pack("V",$crc); //crc32
        $fr .= pack("V",$c_len); //compressed filesize
        $fr .= pack("V",$unc_len); //uncompressed filesize

        // add this entry to array
        $this -> datasec[] = $fr;

        $new_offset = strlen(implode("", $this->datasec));

        // now add to central directory record
        $cdrec = "\x50\x4b\x01\x02";
        $cdrec .="\x00\x00";    // version made by
        $cdrec .="\x14\x00";    // version needed to extract
        $cdrec .="\x00\x00";    // gen purpose bit flag
        $cdrec .="\x08\x00";    // compression method
        $cdrec .="\x00\x00\x00\x00"; // last mod time & date
        $cdrec .= pack("V",$crc); // crc32
        $cdrec .= pack("V",$c_len); //compressed filesize
        $cdrec .= pack("V",$unc_len); //uncompressed filesize
        $cdrec .= pack("v", strlen($name) ); //length of filename
        $cdrec .= pack("v", 0 ); //extra field length   
        $cdrec .= pack("v", 0 ); //file comment length
        $cdrec .= pack("v", 0 ); //disk number start
        $cdrec .= pack("v", 0 ); //internal file attributes
        $cdrec .= pack("V", 32 ); //external file attributes - 'archive' bit set

        $cdrec .= pack("V", $this -> old_offset ); //relative offset of local header
//      &n // bsp; echo "old offset is ".$this->old_offset.", new offset is $new_offset<br>";
        $this -> old_offset = $new_offset;

        $cdrec .= $name;  
        // optional extra field, file comment goes here
        // save to central directory
        $this -> ctrl_dir[] = $cdrec;  
    }

    function file() { // dump out file   
        $data = implode("", $this -> datasec);  
        $ctrldir = implode("", $this -> ctrl_dir);  

        return   
            $data.  
            $ctrldir.  
            $this -> eof_ctrl_dir.  
            pack("v", sizeof($this -> ctrl_dir)).     // total # of entries "on this disk"
            pack("v", sizeof($this -> ctrl_dir)).     // total # of entries overall
            pack("V", strlen($ctrldir)).             // size of central dir
            pack("V", strlen($data)).                 // offset to start of central dir
            "\x00\x00";                             // .zip file comment length
    }
}  

?>
