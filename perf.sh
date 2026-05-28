#!/usr/bin/env sh

echo "------ RUN PERF TEST------\n"
nice -20 docker exec flexid_php84 php src/Scripts/perf.php $@

