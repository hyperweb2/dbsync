<?php 
defined("DBSYNC_EXEC") or define("DBSYNC_EXEC",1);
defined("DBSYNC_PATH") or define("DBSYNC_PATH",  dirname(__FILE__).DIRECTORY_SEPARATOR);

class dbSync {
    /**
     * call init to run dbsync
     */
    public static function init($dbpath) {
        global $hw2ds_dbpath;
        $hw2ds_dbpath=$dbpath;
        if ($_GET["method"]=="import") {
            echo "importing..";
            require "import.php";
        } else {
            echo "exporting..";
            require "export.php";
        }
    }
}

?>
