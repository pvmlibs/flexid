#!/usr/bin/env sh

set -e

for (( i=81; i <= 85; ++i ))
do
  echo "------ BENCHMARK FOR PHP$i ------"
  docker exec flexid_php$i php bench.php
done
