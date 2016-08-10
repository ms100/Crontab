<?php
require './crontab.php';
//print_r(get_mday_by_wday(array(1,2),8,2016));
//exit;
echo date('Y-m-d H:i:s') . "\n";
echo date('Y-m-d H:i:s', get_next_timestamp('* * */4 * 1,5')) . "\n";