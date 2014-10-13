<?php

include_once "libs/fight.php";

$p = array(
    1 => array(
        'id' => '1061',
        'lv' => 100

    ),
    /*
    2 => array(
        'id' => '1002',
        'lv' => 10

    ),
    3 => array(
        'id' => '1003',
        'lv' => 1

    ),
    4 => array(
        'id' => '1004',
        'lv' => 1

    ),
    5 => array(
        'id' => '1005',
        'lv' => 1

    ),
    6 => array(
        'id' => '1006',
        'lv' => 1

    ),
    */
);
$e = array(
    /*
    1 => array(
        'id' => '1007',
        'lv' => 1

    ),
    2 => array(
        'id' => '1008',
        'lv' => 1

    ),
    */
    3 => array(
        'id' => '1009',
        'lv' => 1
    ),
    4 => array(
        'id' => '1021',
        'lv' => 1

    ),
    /*
    5 => array(
        'id' => '1022',
        'lv' => 1

    ),
    */
    6 => array(
        'id' => '1023',
        'lv' => 1

    ),
);

$e_buf = $p_buf = array(
    array(1 => 100, 2 => 2),
    array(1 => 100),
    array(2 => 1),
    array(3 => 2),
    array(4 => 3),
    array(5 => 4),
    array(6 => 6),
    array(7 => 6),
    array(8 => 7),
    array(9 => 8),
    array(10 => 10),
    array(11 => 10),
    array(12 => 10)
    );

$ret = Fight::getInstance()->start($p, $e, $p_buf, $e_buf);
print_r($ret);exit;
//echo json_encode($ret);