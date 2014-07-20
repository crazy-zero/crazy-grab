<?php
/**
 * this is a demo for php fork and pipe usage. fork use
 * to create child process and pipe is used to sychoroize
 * the child process and its main process.
 * @author bourneli
 * @date: 2012-7-6
 */

define("PC",10); // 进程个数
define("TO",4); // 超时
define("TS",4); // 事件跨度，用于模拟任务延时

if (!function_exists('pcntl_fork')) {
    die("pcntl_fork not existing");
}

// 创建管道
$sPipePath = "my_pipe.".posix_getpid();
if (!posix_mkfifo($sPipePath, 0666)) {
    die("create pipe {$sPipePath} error");
}
 
// 模拟任务并发
for ($i = 0; $i < PC; ++$i ) {
    $nPID = pcntl_fork(); // 创建子进程
    if ($nPID == 0) {
        // 子进程过程
        sleep(rand(1,TS)); // 模拟延时
        $oW = fopen($sPipePath, 'w');
        fwrite($oW, $i."\n"); // 当前任务处理完比，在管道中写入数据
        fclose($oW);
        exit(0); // 执行完后退出
    }
}
 
// 父进程
$oR = fopen($sPipePath, 'r');
stream_set_blocking($oR, FALSE); // 将管道设置为非堵塞，用于适应超时机制
$sData = ''; // 存放管道中的数据
$nLine = 0;
$nStart = time();
while ($nLine < PC && (time() - $nStart) < TO) {
    $sLine = fread($oR, 1024);
    if (empty($sLine)) {
        continue;   
    }   
     
    echo "current line: {$sLine}\n";
    // 用于分析多少任务处理完毕，通过‘\n’标识
    foreach(str_split($sLine) as $c) {
        if ("\n" == $c) {
            ++$nLine;
        }
    }
    $sData .= $sLine;
}
echo "Final line count:$nLine\n";
fclose($oR);
unlink($sPipePath); // 删除管道，已经没有作用了
 
// 等待子进程执行完毕，避免僵尸进程
$n = 0;
while ($n < PC) {
    $nStatus = -1;
    $nPID = pcntl_wait($nStatus, WNOHANG);
    if ($nPID > 0) {
        echo "{$nPID} exit\n";
        ++$n;
    }
}
 
// 验证结果，主要查看结果中是否每个任务都完成了
$arr2 = array();
foreach(explode("\n", $sData) as $i) {// trim all
    if (is_numeric(trim($i))) {
        array_push($arr2, $i);  
    }
}
$arr2 = array_unique($arr2);
if ( count($arr2) == PC) {  
    echo 'ok'; 
} else {
    echo  "error count " . count($arr2) . "\n";
    var_dump($arr2);
}