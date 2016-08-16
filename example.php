<?php
require './CrontabRule.php';

echo date('Y-m-d H:i:s') . "\n";
var_dump(CrontabRule::check_time_rule('*/2 * 3,6,9 * 1'));
echo date('Y-m-d H:i:s', CrontabRule::get_next_time('*/5 * */4 * 1,5')) . "\n";
echo date('Y-m-d H:i:s', CrontabRule::get_next_time('*/5 * */4 * 1,5', strtotime('2016-08-17 00:05:00'))) . "\n";