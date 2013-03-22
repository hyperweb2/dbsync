<?php defined("DBSYNC_EXEC") or die("dbsync not loaded correctly");

include 'functions.php';

// Mmake sure we can write the dump file
$time		= date('Ymd_His');
$dumpfile	= $dir . $dbname . '_' . $time . '_old.sql';

backup_tables($dumpfile,$dbhost, $dbuser, $dbpass, $dbname);

uncompress($dir.$filename.'.gz',$dir.$filename);

parse_mysql_dump($dir.$filename,$dbhost, $dbuser, $dbpass, $dbname);

unlink($dir.$filename);
unlink($dir.$filename.'.gz');

echo 'completed';

?>
