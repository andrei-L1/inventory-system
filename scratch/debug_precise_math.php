<?php
$str = '67.063083345775';
$half = '0.000000005';
$result = bcadd($str, $half, 8);
echo "Result (scale 8): $result\n";

$resultHigh = bcadd($str, $half, 12);
echo "Result (scale 12): $resultHigh\n";
echo "Truncated High: " . bcadd($resultHigh, '0', 8) . "\n";
