<?php

class StatusEnumTypes {
    const START = 0;
    const SEARCHING_FOR_INTERVAL = 1;
    const FOUND_START_INTERVAL = 2;
}

class BufferChecker {

    private string $time;

    private int $min_access;

    private String $start_interval= '';

    private String $store_end_interval = '';

    private String $found_interval_end = '';

    private int $refuses_in_interval = 0;

    private int $logs_in_interval;

    private float $access_percent = 0;

    private int $status = StatusEnumTypes::START;

    public function __construct($min_access) {
        $this->min_access = $min_access;
    }

    public function get_status(): int {

        return $this->status;
    }

    private function reset_values($log) {
        $this->time = $log->time;
        $this->start_interval = $log->time;
        $this->store_end_interval = $log->time;
        $this->refuses_in_interval = 0;
        $this->logs_in_interval = 1;
        if ($log->refused) {
            $this->refuses_in_interval = 1;
        }
    }

    private function increase_values($log) {
        $this->store_end_interval = $log->time;
        $this->logs_in_interval++;
        if ($log->refused) {
            $this->refuses_in_interval++;
        }
    }

    public function check_interval(LogData $log) {
        if ($this->status == StatusEnumTypes::START) { //start of a program
            $this->status = StatusEnumTypes::SEARCHING_FOR_INTERVAL;
            $this->reset_values($log);
        }
        else if ($this->status == StatusEnumTypes::SEARCHING_FOR_INTERVAL) { //if the same second
            if ($this->time == $log->time) {
                $this->increase_values($log);
            }
            else {
                $access = 100 - $this->refuses_in_interval / $this->logs_in_interval * 100;
                $this->time = $log->time;
                if ($access < $this->min_access) {
                    $this->status = StatusEnumTypes::FOUND_START_INTERVAL;
                    $this->found_interval_end = $this->store_end_interval;
                    $this->increase_values($log);
                    $this->access_percent = $access;
                }
                else {
                    $this->reset_values($log);
                }
            }
        }
        else if ($this->status == StatusEnumTypes::FOUND_START_INTERVAL) {
            if ($this->time == $log->time) {
                $this->increase_values($log);
            }
            else {
                $access = 100 - $this->refuses_in_interval / $this->logs_in_interval * 100;
                $this->time = $log->time;
                if ($access < $this->min_access) {
                    $this->found_interval_end = $this->store_end_interval;
                    $this->increase_values($log);
                    $this->access_percent = $access;
                }
                else {
                    $this->print_interval();
                    $this->status = StatusEnumTypes::SEARCHING_FOR_INTERVAL;
                    $this->reset_values($log);
                }
            }
        }
    }

    public function print_interval() {
        echo $this->start_interval . ' ' . $this->found_interval_end . ' ' . $this->access_percent . "\n";
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
    $bufferChecker = new BufferChecker($options['u']);
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
        $bufferChecker->check_interval($log_data);
    }
}



