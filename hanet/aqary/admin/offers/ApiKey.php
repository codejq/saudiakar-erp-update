<?php
$CONFIG_FILE = __DIR__ . '/../../../../app/officed.net.cfg';
$CONFIG_FILE_ALTERNATE = __DIR__ . '/../../officed.net.cfg';
// Read API key from config file
function getApiKey() {
    global $CONFIG_FILE, $CONFIG_FILE_ALTERNATE ;

    if (!file_exists($CONFIG_FILE)) {
        $CONFIG_FILE = $CONFIG_FILE_ALTERNATE;
    }

    if (!file_exists($CONFIG_FILE)) {
        logMessage("ERROR: Config file not found at {$CONFIG_FILE}");
        return null;
    }

    $config_content = file_get_contents($CONFIG_FILE);
    $parts = explode('#', $config_content);

    if (count($parts) < 1) {
        logMessage("ERROR: Invalid config file format");
        return null;
    }

    // Find the longest part in the array
    $longest = '';
    foreach ($parts as $part) {
        if (strlen($part) > strlen($longest)) {
            $longest = $part;
        }
    }

    return trim($longest);
}


