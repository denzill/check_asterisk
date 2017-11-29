#!/usr/bin/env php
<?php

/**
 * Show usage help
 */
function usage() {
    $usage = "Usage: check_asterisk.php -H <hostname> -P <port> -u <user> -p <password> -t <seconds> " . PHP_EOL
            . "       [-w unconnected WARNING] [-c unconnected CRITICAL] [-W long call WARNING] [-C long call CRITICAL] " . PHP_EOL
            . "       [-v] [-l logfile] " . PHP_EOL
            . "       -H <hostname>" . PHP_EOL
            . "       -P <port>" . PHP_EOL
            . "       -u <username>" . PHP_EOL
            . "       -p <password>" . PHP_EOL
            . "       -t <read timeout>" . PHP_EOL
            . "       -v verbose output" . PHP_EOL
            . "       [-w #] unconnected peers WARNING threshold" . PHP_EOL
            . "       [-c #] unconnected peers CRITICAL threshold" . PHP_EOL
            . "       [-W #] long call WARNING threshold (in seconds) (NOT IMPLEMENTED!)" . PHP_EOL
            . "       [-C #] long call CRITICAL threshold (in seconds) (NOT IMPLEMENTED!)" . PHP_EOL
            . "       [-l logfile] log output to file (relative to /var/log/)" . PHP_EOL
            . PHP_EOL . "You need add new user in manager.conf:" . PHP_EOL
            . "[<USER>] ; username (-u option)" . PHP_EOL
            . "displayconnects = no" . PHP_EOL
            . "secret = <PASSWORD> ; Password (-p option)" . PHP_EOL
            . "deny=0.0.0.0/0.0.0.0 ; Deny from all" . PHP_EOL
            . "permit=123.456.789.012/255.255.255.255 ; Change to icinga/nagios IP/MASK" . PHP_EOL
            . "read = system,call,log,verbose,command,agent,user,config,all ; Read privileges" . PHP_EOL
            . "write = command ; Write privileges" . PHP_EOL;
    echo ($usage . PHP_EOL);
    exit(0);
}

/**
 * Check commandline options
 * @return array $options - Parsed commandline options
 */
function checkOptions() {
    $requiredOpts = 'H:P:u:p:t:';
    $additionslOpts = 'W:w:C:c:vl:';
    $options = getopt($requiredOpts . $additionslOpts);
    foreach (explode(':', $requiredOpts)as $option) {
        if ($option) {
            if (!isset($options[$option]) || (empty($options[$option]))) {
                echo ("ASTERISK UNKNOWN - Required option missed: '-" . $option . "' | " . PHP_EOL);
                usage();
            }
        }
    }
    return $options;
}

/**
 * Connect to Asterisk AMI
 * @global socket $connection - socket for connection
 * @global int $status        - service status
 * @param string $address     - Astrisk AMI hostname/address (-H option)
 * @param string $port        - Asterisk AMI port (-P option)
 * @return string             - Error explanation or Asterisk Manager version
 */
function connect($address, $port) {
    global $connection, $status;

    $errno = $errstr = '';
    $connection = fsockopen($address, $port, $errno, $errstr, 3);
    if ($connection === FALSE) {
        $status = CRITICAL;
        $return = $errstr . "(" . $errno . ")";
    } else {
        $return = trim(fgets($connection, 1000));
    }
    return $return;
}

/**
 * Login to AMI
 * @param string $user - Username (-u option)
 * @param string $pass - Password (-p option)
 * @return array       - Action result
 */
function login($user, $pass) {
    $result = action("Login", array("Username" => $user, "Secret" => $pass));
    return $result;
}

/**
 * Execute action
 * @global socket $connection - socket for connection
 * @global array $options     - Parsed commandline options
 * @param string $action      - Action to execute
 * @param array $parameters   - Action parameters
 * @param string $events      - On/Off enable or disable event listening
 * @return array              - Action result
 */
function action($action, $parameters = array(), $events = 'Off') {
    global $connection, $options;

    if (!is_array($parameters)) {
        $parameters[] = $parameters;
    }
    $actionArray = array('Action: ' . $action, 'Events: ' . $events);
    foreach ($parameters as $act => $val) {
        $actionArray[] = $act . ": " . $val;
    }
    $actionStr = implode("\r\n", $actionArray) . "\r\n\r\n";
    $event = 0;
    $wrets = array('action' => $action);
    $list_complete = false;
    stream_set_timeout($connection, $options['t']);
    fputs($connection, $actionStr);
    do {
        $line = trim(fgets($connection, 4096), "\r\n");
        if ($line != '') {
            if ($line == 'EventList: Complete' ||
                    strpos($line, 'Response: ') !== FALSE ||
                    $line == 'Event: StatusComplete' ||
                    $line == '--END COMMAND--') {
                $list_complete = true;
            }
            $respArray = explode(': ', $line);
            if (count($respArray) == 2) {
                $wrets[$event][$respArray[0]] = $respArray[1];
            } else {
                $wrets[$event]['output'][] = $respArray[0];
            }
        } else {
            $event++;
            if ($list_complete == true) {
                break;
            }
        }
        $info = stream_get_meta_data($connection);
    } while ($info['timed_out'] == false);
    $info['response'] = $wrets;
    return $info;
}

/**
 * Simple logoff
 */
function logoff() {
    action("Logoff");
}

/**
 * Set new status
 * @global int $status       - Current status
 * @global array $statusText - Array with string representation of statuses
 * @param int $newStatus     - New status (must be greater then current status)
 * @return boolean           - true if new status set
 */
function setStatus($newStatus) {
    global $status, $statusText;

    $result = false;
    if ((isset($statusText[$newStatus])) && ($newStatus > $status)) {
        $status = $newStatus;
        $result = true;
    }
    return $result;
}

/**
 * Convert perfomance data array into the string
 * @param array|string $perfData - Perfomance data
 * @return string                - Concatenated string with perfomance data
 */
function processPerfData($perfData) {
    $string = '';
    if (!is_array($perfData)) {
        $perfData['perf'] = array($perfData);
    }
    foreach ($perfData as $label => $value) {
        $string .= ' ' . $label . '=' . str_replace(' ', '', $value);
    }
    return trim($string);
}

/**
 * Output service status and exit from script
 * @global array $statusText - Array with string representation of statuses
 * @param type $status       - One of defined status
 * @param type $statusString - Extended status string
 * @param type $perfData     - Perfomance data
 * @param type $additions    - Additional data to display
 */
function showResult($status, $statusString, $perfData = array(), $additions = '') {
    global $statusText;

    echo ('ASTERISK ' . $statusText[$status] . ' - ' . $statusString . ' | ' . processPerfData($perfData) . PHP_EOL . $additions);
    exit($status);
}

/**
 * Concatenate array into string
 * @param array|string $array - Array with key=>value pairs
 * @return string             - Imploded string
 */
function implodeArray($array) {
    $return = $array;
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            if ($return != '') {
                $return .= PHP_EOL;
            }
            $return .= $key . " = " . $value;
        }
    }
    return $return;
}

/**
 * Strange magic...
 * Split table header string into regexp 
 * for splitting table rows to cells
 * @param string $tableHeaders - First line of table
 * @return string              - Returning regexp
 */
function getPattern($tableHeaders) {
    $pattern = '';
    $columns = array();
    $prev = 0;
    foreach (preg_split('|[\s]+|', $tableHeaders, -1, PREG_SPLIT_OFFSET_CAPTURE) as $key => $column) {
        $columns[] = str_replace('/', '', $column[0]);
        if (($key != 0) && ($key < count($columns))) {
            $pattern .= "(?<" . $columns[count($columns) - 2] . ">.{" . ($column[1] - $prev) . "})";
        }
        $prev = $column[1];
    }
    return $pattern;
}

/**
 * Get asterisk uptime
 * @global array $options - Parsed commandline options
 * @return array          - Uptime data
 */
function getUptime() {
    global $options;

    $response = action('Command', array('Command' => "Core Show uptime seconds"));
    if ($response['response'][0]['Response'] == 'Error') {
        showResult(WARNING, 'Get uptime failed: ' . $response['response'][0]['Message'] . ', may be need "command" write privilege for user ' . $options['u']
                . ' in file manager.conf (write=command)');
    }
    return $response['response'][0];
}

/**
 * Get active and processed calls counter
 * @global array $options - Parsed commandline options
 * @return array          - Calls data
 */
function calls() {
    global $options;

    $response = action('Command', array('Command' => "Core Show calls"));
    if ($response['response'][0]['Response'] == 'Error') {
        showResult(WARNING, 'Get calls counter failed: ' . $response['response'][0]['Message'] . ', may be need "command" write privilege for user ' . $options['u']
                . ' in file manager.conf (write=command)');
    }
    foreach ($response['response'][0]['output'] as $key) {
        $resp = explode(' ', $key);
        if (count($resp) == 3) {
            $response[$resp[1]] = $resp[0];
        }
    }
    return $response;
}

/**
 * Get connected and disconected sip users
 * @global array $options - Parsed commandline options
 * @return array          - Users data
 */
function users() {
    global $options;

    $response = action('Command', array('Command' => "sip Show peers"));
    if ($response['response'][0]['Response'] == 'Error') {
        showResult(WARNING, 'Get calls counter failed: ' . $response['response'][0]['Message'] . ', may be need "command" write privilege for user ' . $options['u']
                . ' in file manager.conf (write=command)');
    }
    $connected = 0;
    $disconnected = 0;
    $response['sip_disconected'] = array();
    $pattern = getPattern(array_shift($response['response'][0]['output']));
    foreach ($response['response'][0]['output'] as $val) {
        $cells = array();
        preg_match_all('~^' . $pattern . '(.*)$~', $val, $cells, PREG_SET_ORDER);
        if (count($cells) > 0) {
            if (strpos(trim($cells[0]['Status']), 'OK') === 0) {
                $connected++;
            } elseif (trim($cells[0]['Status']) == 'Unmonitored') { // Unmonitored SIP Users ()
                if (preg_match('/([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})/', $cells[0]['Host'])) {
                    $connected++;
                } else {
                    $response['sip_disconected'][trim($cells[0]['Nameusername'])] = trim($cells[0]['Host']);
                    $disconnected++;
                }
            } else { // Monitored, but disconected
                $response['sip_disconected'][trim($cells[0]['Nameusername'])] = trim($cells[0]['Status']);
                $disconnected++;
            }
        }
    }
    $response['connected'] = $connected;
    $response['disconnected'] = $disconnected;
    $response['sip_peers'] = count($response['response'][0]['output']);
    return $response;
}

/**
 * Check value for warning and critical threshold
 * @global array $options - Parsed commandline options
 * @global type $info     - Service status explanation text
 * @param type $opt_warn  - Warning commandline parameter
 * @param type $opt_crit  - Critical commandline parameter
 * @param type $value     - Value for checking
 * @param type $infoText  - Text for add to $info if status is changed
 */
function checkStatus($opt_warn, $opt_crit, $value, $infoText) {
    global $options, $info;

    if (isset($options[$opt_crit]) && $options[$opt_crit] <= $value) {
        if (setStatus(CRITICAL)) {
            $info .= ', ' . $infoText;
        }
    } elseif (isset($options[$opt_warn]) && $options[$opt_warn] <= $value) {
        if (setStatus(WARNING)) {
            $info .= ', ' . $infoText;
        }
    }
}

/**
 * Search long calls (only for SIP users)
 * @global array $options - Parsed commandline options
 * @param type $users     - List SIP users
 * @return array          - Long calls list
 */
function longCalls($users) {
    global $options;

    if (isset($options['W']) || isset($options['C'])) {
        $response = action('Command', array('Command' => "core show channels"));
        print_r($response);
    }
    return $response;
}

/**
 * Concatenate verbose text ad write log if nessecary
 * @global string|null $verbose - Concatenated verbose text or null when verbose is disabled
 * @global string|null $logfile - Logfile to write log
 * @param mixed $text           - Verbose text for add to output
 * @return string|null          - Concatenated verbose text or null when verbose is disabled
 */
function verbose($text = '') {
    global $verbose, $logfile;

    // Add text to verbose output
    if (!is_null($verbose)) {
        if (!is_string($text)) {
            $text = print_r($text, 1);
        }
        $verbose .= PHP_EOL . $text;
    }
    // Add text to log output
    if (!is_null($logfile) && $logfile !== false) {
        if (!is_string($text)) {
            $text = print_r($text, 1);
        }
        file_put_contents($logfile, $text . PHP_EOL, FILE_APPEND);
    }
    return $verbose;
}

define('OK', 0);
define('WARNING', 1);
define('CRITICAL', 2);
define('UNKNOWN', 3);
$statusText = array(
    OK       => 'OK',
    WARNING  => 'WARNING',
    CRITICAL => 'CRITICAL',
    UNKNOWN  => 'UNKNOWN',
);

$verbose = '';
$error = '';
$connection = false;
$status = OK;
$startTime = microtime();
$perfData = array();

/* Main part */
$options = checkOptions();
if (!isset($options['v'])) {
    $verbose = null;
    error_reporting(0);
}
if (isset($options['l'])) {
    $logfile = '/var/log/' . $options['l'];
    if (file_exists(dirname($logfile)) && !is_writable(dirname($logfile))) {
        $error .= 'LOGFILE: directory ' . dirname($logfile) . ' not writable.' . PHP_EOL;
        $logfile = false;
    } else {
        if (!file_exists(dirname($logfile)) && !mkdir(dirname($logfile), 0777, true)) {
            $error .= 'LOGFILE: Cannot create directory: ' . dirname($logfile) . '.';
            $logfile = false;
        }
        if (!touch($logfile)) {
            $error .= 'LOGFILE: Directory ' . dirname($logfile) . ' not writable.' . PHP_EOL;
            $logfile = false;
        }
    }
} else {
    $logfile = null;
}
verbose($options);

$connect = connect($options['H'], $options['P']);
if ($status !== OK) {
    showResult($status, $connect, '');
}

$login = login($options['u'], $options['p']);
verbose($login);
$perfData['time'] = (microtime() - $startTime);
if ($login['timed_out'] == true) {
    showResult(WARNING, 'Login command timed out (may be you need incresase read timeout?)', $perfData);
}
if ($login['response'][0]['Response'] == 'Error') {
    showResult(WARNING, 'Login command failed: ' . $login['response'][0]['Message'] . '. Do you add user in manager.conf and execute "asterisk -rx "manager reload"?', $perfData);
}
$info = $connect;
// Uptime
$uptime = getUptime();
verbose($uptime);
$perfData['system_uptime'] = $uptime['System uptime'] . 's';
$perfData['last_reload'] = $uptime['Last reload'] . 's';
// Calls
$calls = calls();
verbose($calls);
$perfData['active_calls'] = $calls['active'];
$perfData['processed_calls'] = $calls['calls'] . 'c';
// Connected and unconnected users
$users = users();
verbose($users);
$perfData['connected'] = $users['connected'];
$perfData['disconnected'] = $users['disconnected'];
$perfData['sip_peers'] = $users['sip_peers'];
checkStatus('w', 'c', $perfData['disconnected'], 'Disconnected users: ' . $perfData['disconnected']);
if (count($users['sip_disconected']) == 0) {
    $users['sip_disconected'] = 'NONE';
}
// Long calls
//$longCalls = longCalls($users['response'][0]['output']);
// Logoff and close
logoff();
fclose($connection);

showResult($status, $info, $perfData, "Disconected peers:\n"
        . implodeArray($users['sip_disconected']) . PHP_EOL
        . (string)$error
        . (string)$verbose);
