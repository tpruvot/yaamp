#!/bin/bash

ps -aux|grep mysql|grep -v grep > /dev/null

if [ $? != 0 ]
then
        /etc/init.d/mysql restart > /dev/null
fi

