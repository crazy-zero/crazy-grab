<?php
$client = new GearmanClient();
$client->addServer('192.168.1.235', 4730);

echo $client->do('client_test', microtime()) . PHP_EOL;
