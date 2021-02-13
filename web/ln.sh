#!/bin/bash

PHP_CLI='php -d max_execution_time=60'

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd ${DIR}

date
echo started in ${DIR}

while true; do
        ${PHP_CLI} runconsole.php cronjob/runLightning
        sleep 20
done
exec bash
