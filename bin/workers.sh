#!/bin/bash

APP="lingua"
WORKER="translator"
COUNT=${1:-1}

if [ "$1" = "--stop" ]; then
    dokku ps:scale $WORKER=0
    echo "Stopped $WORKER workers"
else
    dokku ps:scale $WORKER=$COUNT
    echo "Scaled $WORKER to $COUNT"
fi

dokku ps:report
