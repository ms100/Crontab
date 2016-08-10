<?php
/**
 * 检查crontab的时间规则是否正确
 *
 * @param string $time_rule crontab的时间计划字符串，如"15 3 * * *"
 *
 * @return array/string 正确返回数组，出错返回字符串（错误信息）
 */
function check_time_rule($time_rule){
    //格式检查
    $time_rule = trim($time_rule);
    $reg = '#^(\*(/\d+)?|((\d+(-\d+)?)(?2)?)(,(?3))*)( (?1)){4}$#';
    if(!preg_match($reg, $time_rule)){
        return false;
    }

    //分别解析分、时、日、月、周
    $parts = explode(' ', $time_rule);

    return parse_time_rule_part($parts[0], 0, 59)//分
    && parse_time_rule_part($parts[1], 0, 59)//时
    && parse_time_rule_part($parts[2], 1, 31)//日
    && parse_time_rule_part($parts[3], 1, 12)//月
    && parse_time_rule_part($parts[4], 0, 6);//周（0周日）
}


/**
 * 解析crontab时间计划里一个部分(分、时、日、月、周)的取值列表
 * @param string $part 时间计划里的一个部分，被空格分隔后的一个部分
 * @param int $f_min 此部分的最小取值
 * @param int $f_max 此部分的最大取值
 *
 * @return array/bool
 */
function parse_time_rule_part($part, $f_min, $f_max){
    $list = array();

    //处理"," -- 列表
    if(false !== strpos($part, ',')){
        $arr = explode(',', $part);
        foreach($arr as $v){
            $tmp = parse_time_rule_part($v, $f_min, $f_max);
            if(!$tmp) return false;
            $list = array_merge($list, $tmp);
        }

        return $list;
    }

    //处理"/" -- 间隔
    $tmp = explode('/', $part);
    $part = $tmp[0];
    $step = isset($tmp[1]) ? $tmp[1] : 1;

    //处理"-" -- 范围
    if(false !== strpos($part, '-')){
        list($min, $max) = explode('-', $part);
        if($min > $max){
            return false;
        }
    }elseif('*' == $part){
        $min = $f_min;
        $max = $f_max;
    }else{//数字
        $min = $max = $part;
    }


    //越界判断
    if($min < $f_min || $max > $f_max){
        return false;
    }

    if($min == $max) return array((int)$min);

    if($step > $max - $min) return false;

    return range($min, $max, $step);
}

/**
 * 根据星期几获取日期
 * @param $wday
 * @param $mon
 * @param $year
 * @return array
 */
function get_mday_by_wday($wday, $mon, $year){
    $res = array();
    if(is_array($wday)){
        if(count($wday) <= 4){
            foreach($wday as $w){
                $res = array_merge($res, get_mday_by_wday($w, $mon, $year));
            }
        }else{
            if(($index = array_search(7, $wday)) !== false){
                unset($wday[$index]);
                $wday[] = 0;
            }
            $wday = array_diff(range(0, 6, 1), $wday);
            $res = range(1, date('t', strtotime(sprintf('%d-%d-01', $year, $mon))), 1);
            foreach($wday as $w){
                $res = array_diff($res, get_mday_by_wday($w, $mon, $year));
            }
        }

        return $res;
    }

    $timestamp = strtotime(sprintf('%d-%d-01', $year, $mon));
    $tmp = date('N', $timestamp);
    $first = $wday - $tmp + (($wday - $tmp < 0) ? 8 : 1);

    return range($first, date('t', $timestamp), 7);
}

/**
 * 根据传入的时间戳和规则，计算下次脚本执行的时间
 * @param $time_rule
 * @param $timestamp
 * @return bool|int
 */
function get_next_timestamp($time_rule, $timestamp = 0){
    empty($timestamp) && $timestamp = time();

    $parts = explode(' ', $time_rule);
    $cron_range = array();
    $cron_range['minutes'] = parse_time_rule_part($parts[0], 0, 59);//分
    $cron_range['hours'] = parse_time_rule_part($parts[1], 0, 59);//时
    $cron_range['mday'] = parse_time_rule_part($parts[2], 1, 31);//日
    $cron_range['mon'] = parse_time_rule_part($parts[3], 1, 12);//月
    $cron_range['wday'] = parse_time_rule_part($parts[4], 0, 6);//周（0周日）

    if(!($cron_range['minutes'] && $cron_range['hours'] && $cron_range['mday'] && $cron_range['mon'] && $cron_range['wday'])){
        return false;
    }

    foreach($cron_range as $k => $v){
        sort($cron_range[$k]);
    }

    $date = getdate($timestamp);

    if(in_array($date['minutes'], $cron_range['minutes']) && in_array($date['hours'], $cron_range['hours']) && in_array($date['mon'], $cron_range['mon']) && ((($parts[2] == '*' || $parts[4] == '*') && in_array($date['mday'], $cron_range['mday']) && in_array($date['wday'], $cron_range['wday'])) || (in_array($date['mday'], $cron_range['mday']) || in_array($date['wday'], $cron_range['wday'])))){

        $next_date = array(
            'minutes' => $date['minutes'],
            'hours' => $date['hours'],
            'mday' => $date['mday'],
            'mon' => $date['mon'],
            'year' => $date['year']
        );

        $index = array_search($date['minutes'], $cron_range['minutes']);
        if($index + 1 < count($cron_range['minutes'])){
            $next_date['minutes'] = $cron_range['minutes'][$index + 1];
        }else{
            $next_date['minutes'] = $cron_range['minutes'][0];
            $index = array_search($date['hours'], $cron_range['hours']);
            if($index + 1 < count($cron_range['hours'])){
                $next_date['hours'] = $cron_range['hours'][$index + 1];
            }else{
                $next_date['hours'] = $cron_range['hours'][0];

                //$append_mday = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
                //$mday_range = array_intersect($cron_range['mday'], $append_mday);
                if($parts[4] == '*'){
                    $mday_range = $cron_range['mday'];
                }elseif($parts[2] == '*'){
                    $mday_range = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
                }else{
                    $append_mday = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
                    $mday_range = array_merge($cron_range['mday'], $append_mday);
                }

                sort($mday_range);

                $index = array_search($date['mday'], $mday_range);
                if($index + 1 < count($mday_range)){
                    $next_date['mday'] = $cron_range['mday'][$index + 1];
                }else{
                    while(1){
                        $index = array_search($next_date['mon'], $cron_range['mon']);
                        if($index + 1 < count($cron_range['mon'])){
                            $next_date['mon'] = $cron_range['mon'][$index + 1];
                        }else{
                            $next_date['mon'] = $cron_range['mon'][0];
                            $next_date['year']++;
                        }

                        //$append_mday = get_mday_by_wday($cron_range['wday'], $next_date['mon'], $next_date['year']);
                        //$mday_range = array_intersect($cron_range['mday'], $append_mday);
                        if($parts[4] == '*'){
                            $mday_range = $cron_range['mday'];
                        }elseif($parts[2] == '*'){
                            $mday_range = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
                        }else{
                            $append_mday = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
                            $mday_range = array_merge($cron_range['mday'], $append_mday);
                        }

                        if(!empty($mday_range)){
                            break;
                        }
                        if($next_date['year'] - $date['year'] > 1){
                            return false;
                        }
                    }
                    sort($mday_range);
                    $next_date['mday'] = $mday_range[0];
                }
            }
        }
    }else{
        $next_date = array();

        $mark = true;
        foreach($cron_range['mon'] as $v){
            $temp = $v - $date['mon'];
            if($temp >= 0){
                $next_date['mon'] = $v;
                $next_date['year'] = $date['year'];
                $temp || $mark = false;
                break;
            }
        }
        if(!isset($next_date['mon'])){
            $next_date['year'] = $date['year'] + 1;
            $next_date['mon'] = $cron_range['mon'][0];
        }

        while(1){
            //$append_mday = get_mday_by_wday($cron_range['wday'], $next_date['mon'], $next_date['year']);
            //$mday_range = array_intersect($cron_range['mday'], $append_mday);
            if($parts[4] == '*'){
                $mday_range = $cron_range['mday'];
            }elseif($parts[2] == '*'){
                $mday_range = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
            }else{
                $append_mday = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
                $mday_range = array_merge($cron_range['mday'], $append_mday);
            }

            if(!empty($mday_range)){
                break;
            }
            $mark = true;
            if($next_date['year'] - $date['year'] > 1){
                return false;
            }

            $index = array_search($next_date['mon'], $cron_range['mon']);
            if($index + 1 < count($cron_range['mon'])){
                $next_date['mon'] = $cron_range['mon'][$index + 1];
            }else{
                $next_date['mon'] = $cron_range['mon'][0];
                $next_date['year']++;
            }
        }
        sort($mday_range);

        if($mark){
            $next_date['mday'] = $mday_range[0];
            $next_date['hours'] = $cron_range['hours'][0];
            $next_date['minutes'] = $cron_range['minutes'][0];
        }else{
            $mark = true;

            foreach($mday_range as $v){
                $temp = $v - $date['mday'];
                if($temp >= 0){
                    $next_date['mday'] = $v;
                    $temp || $mark = false;
                    break;
                }
            }

            if(!isset($next_date['mday'])){
                mon_add:
                while(1){
                    $index = array_search($next_date['mon'], $cron_range['mon']);
                    if($index + 1 < count($cron_range['mon'])){
                        $next_date['mon'] = $cron_range['mon'][$index + 1];
                    }else{
                        $next_date['mon'] = $cron_range['mon'][0];
                        $next_date['year']++;
                    }

                    //$append_mday = get_mday_by_wday($cron_range['wday'], $next_date['mon'], $next_date['year']);
                    //$mday_range = array_intersect($cron_range['mday'], $append_mday);
                    if($parts[4] == '*'){
                        $mday_range = $cron_range['mday'];
                    }elseif($parts[2] == '*'){
                        $mday_range = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
                    }else{
                        $append_mday = get_mday_by_wday($cron_range['wday'], $date['mon'], $date['year']);
                        $mday_range = array_merge($cron_range['mday'], $append_mday);
                    }

                    if(!empty($mday_range)){
                        break;
                    }
                    if($next_date['year'] - $date['year'] > 1){
                        return false;
                    }
                }
                sort($mday_range);
                $next_date['mday'] = $mday_range[0];
            }

            if($mark){
                $next_date['hours'] = $cron_range['hours'][0];
                $next_date['minutes'] = $cron_range['minutes'][0];

                //return $next_date;
            }else{
                $mark = true;

                foreach($cron_range['hours'] as $v){
                    $temp = $v - $date['hours'];
                    if($temp >= 0){
                        $next_date['hours'] = $v;
                        $temp || $mark = false;
                        break;
                    }
                }

                if(!isset($next_date['hours'])){
                    mday_add:
                    $index = array_search($next_date['mday'], $mday_range);
                    if($index + 1 < count($mday_range)){
                        $next_date['mday'] = $mday_range[$index + 1];
                        $next_date['hours'] = $cron_range['hours'][0];
                    }else{
                        goto mon_add;
                    }
                }

                if($mark){
                    $next_date['minutes'] = $cron_range['minutes'][0];

                    //return $next_date;
                }else{
                    foreach($cron_range['minutes'] as $v){
                        $temp = $v - $date['minutes'];
                        if($temp > 0){
                            $next_date['minutes'] = $v;
                            break;
                        }
                    }
                    if(!isset($next_date['minutes'])){
                        $index = array_search($next_date['hours'], $cron_range['hours']);
                        if($index + 1 < count($cron_range['hours'])){
                            $next_date['hours'] = $cron_range['hours'][$index + 1];
                            $next_date['minutes'] = $cron_range['minutes'][0];
                        }else{
                            goto mday_add;
                        }
                    }
                }
            }
        }
    }

    return mktime($next_date['hours'], $next_date['minutes'], 0, $next_date['mon'], $next_date['mday'], $next_date['year']);
}
