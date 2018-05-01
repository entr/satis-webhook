<?php

use Symfony\Component\Yaml\Yaml;

const FILE_LOCK = VAR_PATH . "/run/satis-webhook.lock";
const FILE_LOG = VAR_PATH . "/log/satis-webhook.log";

const LOG_HEADER = "%s : %s\t\t";
const LOG_WAITING = "Waiting to acquire lock";
const LOG_NOTFOUND = "LOCK file %s cannot be found or accessed";
const LOG_ACQUIRED = "Acquired lock";
const LOG_RELEASED = "Released lock";
const LOG_FILE_NOTFOUND = "LOG file %s canot be found or accessed";

if ( ! file_exists(dirname( FILE_LOCK )) ) {
  mkdir( dirname( FILE_LOCK ), 0755, true );
}
if ( ! file_exists(dirname( FILE_LOG )) ) {
  mkdir( dirname( FILE_LOG ), 0755, true );
}

function logMessage($message)
{
    if(file_put_contents(FILE_LOG, $message, FILE_APPEND | LOCK_EX) === false) {
        error_log(sprintf(LOG_FILE_NOTFOUND, FILE_LOG));
    }
}

function logEntry($entry, $id)
{
    $timestamp = date("Y-m-d H:i:s");
    $entry = sprintf(LOG_HEADER, $timestamp, $id) . $entry . "\n";
    logMessage($entry);
}

function acquireLock($id)
{
    logEntry(LOG_WAITING, $id);
    $lockHandle = fopen(FILE_LOCK, "w+");
    if($lockHandle === false) {
        logEntry(sprintf(LOG_NOTFOUND, FILE_LOCK), $id);
        return false;
    }
    flock($lockHandle, LOCK_EX); // Busy waiting
    logEntry(LOG_ACQUIRED, $id);
    return $lockHandle;
}

function releaseLock($lockHandle, $id)
{
    if($lockHandle === false) {
        return;
    }
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    logEntry(LOG_RELEASED, $id);
}