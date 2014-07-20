<?php
$redirect_stdout = true;
$process = new swoole_process('callback_function', $redirect_stdout);
$worker_pid = $process->start();
echo "new worker,PID=" . $worker_pid . PHP_EOL;


function callback_function($worker) {
    echo "workerstart PID=" . $worker->pid . "\n";
    sleep(2); 
    $worker->write("hello world!\n");

    $recv = $worker->read();
    echo "worker receive : $recv \n";
    
    $worker->exit(0);
}
$ret = swoole_process::wait();
var_dump($ret);
echo "Master Receive:\n" . $process->read() . PHP_EOL;

$process->write("master");

$ret = swoole_process::wait();

var_dump($ret);
sleep(2);
