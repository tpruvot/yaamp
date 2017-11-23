#!/bin/bash

ps -aux|grep nginx|grep -v grep > /dev/null

if [ $? != 0 ]
then
        /etc/init.d/nginx restart > /dev/null
fi

