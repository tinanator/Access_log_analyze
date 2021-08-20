<?php

use PHPUnit\Framework\TestCase;

include 'main.php';

class Test extends TestCase
{
    public function testParse()
    {
        $log = '192.168.32.181 - - [14/06/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=6076537c HTTP/1.1" 200 2 44.510983 "-" "@list-item-updater" prio:0';
        $log_data = parse($log, 50);
        preg_match_all('/".*?"\s(?<code>.*?)\s.*?\s(?<response_time>.*?)\s/s', $log, $matches, PREG_SET_ORDER);
        $this->assertSame('200', $matches[0]['code']);
        $this->assertSame('44.510983', $matches[0]['response_time']);

        $this->assertSame(false, $log_data->refused);
        $this->assertSame('16:47:02', $log_data->time);

        $log = '192.168.32.181 - - [14/06/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=6076537c HTTP/1.1" 200 2 60 "-" "@list-item-updater" prio:0';
        $log_data = parse($log, 50);
        $this->assertSame(true, $log_data->refused);

        $log = '192.168.32.181 - - [14/06/2017:16:47:02 +1000] "PUT /rest/v1.4/documents?zone=default&_rid=6076537c HTTP/1.1" 500 2 44.510983 "-" "@list-item-updater" prio:0';
        $log_data = parse($log, 50);
        $this->assertSame(true, $log_data->refused);
    }

    private function write_logs($count, $is_refused, $cur_time, &$bufferChecker) : void {
        for ($i = 0; $i < $count; $i++) {
            $bufferChecker->check_interval(new LogData((string)$cur_time, $is_refused));
        }
    }

    public function testCheckBuffer() {
        $this->expectOutputString("1 1 33.333333333333\n3 4 40\n");
        $min_access = 50;

        $bufferChecker = new BufferChecker($min_access);
        $cur_time = 1;
        $this->write_logs(2, true, $cur_time, $bufferChecker);
        $this->write_logs(1, false, $cur_time, $bufferChecker);
        $cur_time++;//2
        $this->write_logs(2, false, $cur_time, $bufferChecker);
        $cur_time++;//3
        $this->write_logs(1, false, $cur_time, $bufferChecker);
        $this->write_logs(2, true, $cur_time, $bufferChecker);
        $cur_time++;//4
        $this->write_logs(1, true, $cur_time, $bufferChecker);
        $this->write_logs(1, false, $cur_time, $bufferChecker);
        $cur_time++;//5
        $this->write_logs(5, false, $cur_time, $bufferChecker);
        $cur_time++;//6
        $this->write_logs(1, false, $cur_time, $bufferChecker);
    }
}