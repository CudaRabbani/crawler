<?php
require_once('./Crawler.php');

$url = 'https://agencyanalytics.com/';
$depth = 1;
$crawler = new Crawler($url, 5);
$crawler->run();
echo "<br>Finally Done";

$result = [
  'test1' => ['a', 'b', 'c'],
  'test2' => ['a', 'x', 'y'],
  'test3' => ['c', 'd', 'p']
];
$temp = [];

foreach ($result as $key=>$value) {
  array_push($temp, ...$value);
}

//print_r($temp);
//print_r(array_count_values($temp));

$result1 = [
  ['a', 'b', 'c'],
  ['a', 'x', 'y'],
  ['c', 'd', 'p']
];

$temp1 = [];
foreach ($result1 as $value) {
  array_push($temp1, ...$value);
}
//print_r(array_count_values($temp1));
