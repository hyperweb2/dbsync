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

x-www-browser "http://"$DS_URL"method=export&-dbid=0-code="$DS_CODE"-filename="$FILENAME

pause 'continue when ready';

ftp -nv <<EOF
open $FTP_HOST
user $FTP_USER $FTP_PASSWD
bin
get $REMOTEFILE $LOCALFILE
del $REMOTEFILE
quit
EOF

gunzip < $LOCALFILE | mysql -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB;

pause 'completed'
