#!/bin/bash

PHP_CLI='php -d max_execution_time=120'

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd ${DIR}

date
echo started in ${DIR}

while true; do
        ${PHP_CLI} runconsole.php cronjob/runLoop2
        sleep 60
done
exec bash

