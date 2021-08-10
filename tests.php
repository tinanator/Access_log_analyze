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

    public function testCycleBuffer() {
        $test_array = [];
        $index = 0;
        $max_size = 50;
        $buffer = new CycleBuffer($max_size);
        $test_refuses_count = 0;
        for ($i = 0; $i < 1000; $i++) {
            $log_data = new LogData('1', rand(0, 1) == 0);
            $test_array[] = $log_data;
            $buffer->write_log($log_data);
            if (count($buffer->get_buffer()) < $max_size) {
                $this->assertCount($i + 1, $buffer->get_buffer());
            }
            else {
                $this->assertCount($max_size, $buffer->get_buffer());
            }
            if ($index - $max_size >= 0 && $test_array[$index - $max_size]->refused) {
                $test_refuses_count--;
            }
            if ($test_array[$index]->refused) {
                $test_refuses_count++;
            }
            $test_percent = (float)$test_refuses_count / $max_size * 100;
            $this->assertSame($test_percent, $buffer->get_refuses_percent());
            $index++;
        }
    }

    private function write_logs($count, $is_refused, $cur_time, &$bufferChecker) : int{
        for ($i = 0; $i < $count; $i++) {
            $cur_time++;
            $bufferChecker->write_log_and_check(new LogData((string)$cur_time, $is_refused));
        }

        return $cur_time;
    }

    public function testCheckBuffer() {
        $this->expectOutputString("2 10 22.222222222222\n16 21 33.333333333333\n");

        $min_access = 50;
        $bufferChecker = new BufferChecker(5, $min_access);
        $cur_time = 0;
        $cur_time = $this->write_logs(3, false, $cur_time, $bufferChecker);
        $cur_time = $this->write_logs(2, true, $cur_time, $bufferChecker);
        $cur_time = $this->write_logs(6, true, $cur_time, $bufferChecker);
        $cur_time = $this->write_logs(6, false, $cur_time, $bufferChecker);
        $cur_time = $this->write_logs(5, true, $cur_time, $bufferChecker);
        $this->write_logs(5, false, $cur_time, $bufferChecker);
    }
}