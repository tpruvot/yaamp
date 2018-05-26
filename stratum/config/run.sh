#!/bin/bash

ulimit -n 10240
ulimit -u 10240

cd /var/stratum
while [ -e config/${1}.conf ]; do
	gzip -f config/${1}.log
        ./stratum config/$1
	sleep 1
done
exec bash

