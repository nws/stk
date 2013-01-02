<?php

db_connect();

echo "deleted ", m('kv')->gc(), " kv entries\n";
