#!/bin/bash

ulimit -n 10240
ulimit -u 10240

#echo "$1 stratum started" | mail -s "$1 stratum started" your@email.com # change email address to valid one

cd /var/stratum
while [ -e config/${1}.conf ]; do
	gzip -f config/${1}.log
        ./stratum config/$1
	#echo "$1 stratum crashed/restarted" | mail -s "$1 stratum crashed/restarted" your@email.com # change email address to valid one
	sleep 1
done
exec bash

