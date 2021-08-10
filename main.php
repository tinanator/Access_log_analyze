<?php

class StatusEnumTypes {
    const SEARCHING_FOR_INTERVAL = 1;
    const FOUND_START_INTERVAL = 2;
}

class BufferChecker {

    private CycleBuffer $buffer;

    private int $min_access;

    private String $start_interval= '';

    private String $end_interval = '';

    private int $refuses_in_interval = 0;

    private int $logs_in_interval;

    private int $status = StatusEnumTypes::SEARCHING_FOR_INTERVAL;

    public function __construct($buffer_max_size, $min_access) {
        $this->buffer = new CycleBuffer($buffer_max_size);
        $this->min_access = $min_access;
    }

    public function get_status(): int {

        return $this->status;
    }

    public function write_log_and_check(LogData $log) {
        $this->buffer->write_log($log);
        $this->check_buffer();
    }

    private function check_buffer() {
        if (100 - $this->buffer->get_refuses_percent() < $this->min_access) {
            if ($this->status === StatusEnumTypes::SEARCHING_FOR_INTERVAL) {
                $this->start_interval = $this->buffer->get_time();
                $this->status = StatusEnumTypes::FOUND_START_INTERVAL;
                $this->end_interval = $this->start_interval;
                $this->refuses_in_interval = 0;
                $this->logs_in_interval = 1;
                if ($this->buffer->is_refused()) {
                    $this->refuses_in_interval++;
                }
            }
            else if ($this->status === StatusEnumTypes::FOUND_START_INTERVAL) {
                $this->end_interval = $this->buffer->get_time();
                if ($this->buffer->is_refused()) {
                    $this->refuses_in_interval++;
                }
                $this->logs_in_interval++;
            }
        }
        else if ($this->status === StatusEnumTypes::FOUND_START_INTERVAL) {
            $this->status = StatusEnumTypes::SEARCHING_FOR_INTERVAL;
            if ($this->buffer->is_refused()) {
                $this->refuses_in_interval++;
            }
            $this->logs_in_interval++;
            $this->print_interval();
        }
    }

    public function print_interval() {
        $access_percent = 100 - $this->refuses_in_interval / $this->logs_in_interval * 100;
        $this->end_interval = $this->buffer->get_time();
        echo $this->start_interval . ' ' . $this->end_interval . ' ' . $access_percent . "\n";
    }

}
class CycleBuffer {
    public function __construct($size) {
        $this->max_size = $size;
    }

    private array $buffer = [];

    private int $max_size;

    private int $index = 0;

    private int $pointer = 0;

    private float $refuses_percent = 0;

    private int $refuses = 0;

    public function write_log(LogData $log_data) {
        if (count($this->buffer) < $this->max_size) {
            $this->buffer[] = $log_data;
            $this->index++;
        }
        else {
            if ($this->buffer[$this->index % $this->max_size]->refused) {
                $this->refuses--;
            }
            $this->buffer[$this->index % $this->max_size] = $log_data;
            $this->index = ($this->index + 1) % $this->max_size;
            $this->pointer = ($this->pointer + 1) % $this->max_size;
        }
        if ($log_data->refused) {
            $this->refuses++;
        }
        $this->refuses_percent = $this->refuses / $this->max_size * 100;
    }

    public function is_refused() : bool {

        return $this->buffer[$this->pointer]->refused;
    }

    public function get_time() : string {

        return $this->buffer[$this->pointer]->time;
    }

    public function get_buffer(): array {

        return $this->buffer;
    }

    public function get_refuses_percent() : float {

        return $this->refuses_percent;
    }
}

class LogData {
    public function __construct($time, $refused) {
        $this->time = $time;
        $this->refused = $refused;
    }

    public string $time;

    public bool $refused;
}

function parse(string $log, float $response_time) : LogData {
    $refused = false;
    preg_match_all('/".*?"\s(?<code>.*?)\s.*?\s(?<response_time>.*?)\s/s', $log, $matches, PREG_SET_ORDER);
    if ($matches[0]['code'][0] === '5' || (float)$matches[0]['response_time'] > $response_time) {
        $refused = true;
    }
    preg_match_all('/:(?<time>.*?)\s/',$log, $matches, PREG_SET_ORDER);

    return new LogData($matches[0]['time'], $refused);
}

function main($stream, $options) {
    $bufferChecker = new BufferChecker(5, $options['u']);
    $log_file = fopen($stream, 'r', 'r');
    while(true) {
        $log = fgets($log_file);
        if (!$log) {
            if ($bufferChecker->get_status() === StatusEnumTypes::FOUND_START_INTERVAL) {
                $bufferChecker->print_interval();
            }
            break;
        }
        $log_data = parse($log, $options['t']);
        $bufferChecker->write_log_and_check($log_data);
    }
}



