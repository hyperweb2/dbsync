<?php defined("DBSYNC_EXEC") or die("dbsync not loaded correctly");
/* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
	Backup mySql tables
	++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
	Copyright 2006 by Richard Williamson (aka OldGuy). 
	Website: http://www.scripts.oldguy.us - noldguy@gmail.com
	Support: http://www.scripts.oldguy.us/forums/
	Licensed under the terms of the GNU General Public License, June 1991.
	++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ 
*/
	
include 'functions.php';										

// ---- end of configuration settings array -----
// Don't change anything after this line unless you know what you are doing

/* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
	Dump a database to a file and send email containing download link
	++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
	mysqldump.php, version 1.3, Feb 2009
	By Richard Williamson (aka OldGuy). 
	Website: http://www.scripts.oldguy.us/mybackup
	Email: oldguy@oldguy.us

	Licensed under the terms of the GNU General Public License, June 1991. 

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, 
	WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN 
	CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
	++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ 
*/

// are we downloading a file?
/*if ($filename) {
	// secret code is not required, the file name is dynamically generated and thus relatively secure
	downloadFile($filename);
	exit();
}*/

if ($code != $secret) {
	$msg = "Invalid code ".$secret." in the query string: ?{$_SERVER['QUERY_STRING']}";
	if ($debug) print "$msg<br />";
	sendLog(1, $msg  . EOL);
    exit();
}

// are we downloading a bundle?
if ($bundle) {
	sendBundle(); 
	exit();
}

if (!is_numeric($dbid) || $dbid > count($cfg) || $dbid < 0) {
	$msg = "Invalid or missing dbid argument in the query string: ?{$_SERVER['QUERY_STRING']}";
	if ($debug) print "$msg<br />";
	sendLog(1, $msg . EOL);
}

// Mmake sure we can write the dump file
$time		= date('Ymd_His');

$dumpfile	= $dir . $filename /* $time . */;
if ($handle = @fopen($dumpfile, 'w')) {
	fclose($handle);
} else {
	$msg = "Unable to open '$dumpfile' for writing.". EOL;
	if ($debug) print "$msg<bmy_hw2_20111111_034837_old.sql.gzr />";
	sendLog(1, $msg  . EOL);
}


// delete old backup files for the database
deleteFiles();

// Open the database
$dblink = @mysql_connect($dbhost, $dbuser, $dbpass);
if (!$dblink) {
	$msg = "Unable to connect to mysql server using the host, user and password in \$cfg[$dbid]". EOL;
	if ($debug) print "$msg<br />";
	sendLog(1, $msg  . EOL);
}
$result = @mysql_select_db($dbname);
if (!$result) {
	$msg = "The database does not exist or the \$cfg[$dbid] user does not have permission to access it". EOL;
	if ($debug) print "$msg<br />";
	sendLog(1, $msg  . EOL);
}

// Get table names
$rows    = 0;
$tables  = array();
$result = mysql_query("SHOW TABLES FROM $dbname ", $dblink);
if (!$result) {
	$msg = "Error doing SHOW TABLES " . mysql_error() . EOL;
	if ($debug) print "$msg<br />";
	sendLog(1, $msg  . EOL);
}
while (list($table_name) = mysql_fetch_row($result)) {
	if ($dbprefix) {
		if (preg_match("/^$dbprefix/", $table_name)) {
			$tables[$rows] = $table_name;
			$rows++;
		}
	} else {
		$tables[$rows] = $table_name;
		$rows++;
	}
	
}

// Optimize tables, build dump command tables variable
$tables_list = '';
$msg = '';
for($i = 0; $i < $rows; $i++) {
	if ($dbprefix) $tables_list .= " {$tables[$i]}";
	if ($optimize) {
		$result	= mysql_query("OPTIMIZE TABLE {$tables[$i]}", $dblink);
		if (!$result) $msg .= "Optimize query failed. mysql error = " . mysql_error() . EOL . EOL;
	}
}

if ($msg) sendLog(0, $msg  . EOL);
if ($debug) print "$i tables were optimized<br />";

// dump the database
//$options	.= ($inserts) ? '' : ' --skip-extended-insert ';
//$cmd		= "$command $options --user=$dbuser --password=$dbpass $dbname $tables_list | gzip > $dumpfile";
//$last_line	= system($cmd, $result);

mysql_close($dblink);

backup_tables($dumpfile,$dbhost, $dbuser, $dbpass, $dbname);

if ($debug) print "<p>completed</p>";

if ($email) sendLog(1, "Download the dump file: http://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}?filename=$dumpfile" . EOL);

?>
