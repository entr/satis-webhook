<?php
fastcgi_finish_request();
set_time_limit(120);

require_once __DIR__ . '/lock_functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

function shutdown($lockHandle, $repo)
{
    releaseLock($lockHandle, $repo);
}

$repo = isset($_GET['package']) ? $_GET['package'] : "ALL PACKAGES";

logEntry("Started", $repo);

$lockHandle = acquireLock($repo);

register_shutdown_function("shutdown", $lockHandle, $repo);

if (!file_exists(__DIR__.'/config.yml')) {
    logEntry(
        "Please, define your satis configuration in a config.yml file.\nYou can use the config.yml.dist as a template.",
        $repo
    );
    exit(-1);
}

$defaults = array(
    'bin' => 'bin/satis',
    'json' => 'satis.json',
    'webroot' => 'web/',
    'user' => null,
);
$config = Yaml::parse(__DIR__.'/config.yml');
$config = array_merge($defaults, $config);

$errors = array();
if (!file_exists($config['bin'])) {
    $errors[] = 'The Satis bin could not be found.';
}

if (!file_exists($config['json'])) {
    $errors[] = 'The satis.json file could not be found.';
}

if (!file_exists($config['webroot'])) {
    $errors[] = 'The webroot directory could not be found.';
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        logEntry($error, $repo);
    }
    exit(-1);
}

$command = sprintf('%s build %s %s', $config['bin'], $config['json'], $config['webroot']);
if (isset($_GET['package'])) {
    $command .= ' ' . $_GET['package'];
    chdir($config['repositories'] .  '/' . $_GET['package']);
    exec('git fetch origin && git remote update --prune origin && git branch -D `git branch -l | grep -v \* | xargs` ; for remote in `git branch -r | grep -v HEAD `; do git checkout --track $remote ; done');
    chdir(__DIR__);
}
if (null !== $config['user']) {
    $command = sprintf('sudo -u %s -i %s', $config['user'], $command);
}

$process = new Process($command);
$exitCode = $process->run(function ($type, $buffer) use ($repo) {
    if ('err' === $type) {
        logMessage($repo . ": " . $buffer);
    }
});

$message = $exitCode === 0 ? 'Successful rebuild! Done.' : 'An error occurred! Done.';
logEntry($message, $repo);
