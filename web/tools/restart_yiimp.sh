#!/bin/bash

echo "Kill Old Proccess"
kill $(ps -ax|grep SCREEN|grep -v run.sh|grep -v grep|awk '{print $1}')

echo "Start Proccess"
screen -dmS main /var/web/main.sh
screen -dmS loop2 /var/web/loop2.sh
screen -dmS blocks /var/web/blocks.sh

