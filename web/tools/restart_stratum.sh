#!/bin/bash
ALGO=$(ps -ax|grep stratum|grep SCREEN|grep -v grep|awk '{print $7}')
echo "Kill Old Proccess"
kill $(ps -ax|grep stratum|grep SCREEN|grep -v grep|awk '{print $1}')

echo "Start Proccess"
for algoname in $ALGO
do
	CMD="screen -dmS $algoname /var/stratum/run.sh $algoname"
	echo $CMD
	#echo $algoname
done

