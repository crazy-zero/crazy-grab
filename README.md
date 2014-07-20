crazy-grab
==========

由php,gearman,swool组成的多进程，分布式，异步爬虫框架

启动gearman-job-server 注意：填写--listen的参数
sudo /usr/sbin/gearmand --pid-file=/var/run/gearmand.pid --user=gearman --daeman --log-file=/var/log/gearman-job-server/gearman.log --listen=192.168.1.235
