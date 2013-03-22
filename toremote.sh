  #!/bin/sh -vx

# Script to FTP data to server
# Paramters:    host            FTP Server
#                     user            FTP Username
#                     passwd      FTP Password
#                     file             File to send/put
############################################################################

SDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CONF=$1

if [ -e $SDIR"/configuration.sh" ]; then 
    source $SDIR"/configuration.sh"
fi;

if [ ! -z $CONF ]; then
    source $CONF # overwrite previous conf
fi;

mysqldump --user=$MYSQL_USER --password=$MYSQL_PASS --add-drop-table $MYSQL_DB $RDUMP_OPT | gzip > $LOCALFILE

# Connect to FTP HOST and Send File
# umask 002 (777-775) needs to set 775 to all transferred files
ftp -invd <<EOF
open $FTP_HOST
user $FTP_USER $FTP_PASSWD
umask 002
bin
put $LOCALFILE $REMOTEFILE
close
quit
EOF

x-www-browser "http://"$DS_URL"method=import&-filename="$FILENAME

pause 'completed'
