<?php
$worker_num = 8;
$redirect_stdout = false;
for($i = 0; $i < $worker_num; $i++)
{
    $process = new swoole_process('callback_function', $redirect_stdout);
    $pid = $process->start();
    $workers[$pid] = $process;

}

function callback_function(swoole_process $worker)
{
    echo "Worker: start. PID=".$worker->pid."\n";
    //recv data from master
    $recv = $worker->read();
    echo "From Master: $recv\n";
    //send data to master
    $worker->write("hello master\n");
    sleep(2);
    $worker->exit(0);
}
