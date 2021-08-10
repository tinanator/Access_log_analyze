<?php

include "main.php";

$options = getopt('u:t:');
main('php://stdin', $options);