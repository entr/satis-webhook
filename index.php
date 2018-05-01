<?php

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/vendor/autoload.php';

if (!file_exists(__DIR__.'/config.yml')) {
  echo "Please, define your satis configuration in a config.yml file.\nYou can use the config.yml.dist as a template.";
  exit(-1);
}

$defaults = array(
  'bin' => 'bin/satis',
  'json' => 'satis.json',
  'webroot' => 'web/',
  'user' => null,
  'var_path' => '/var',
);

try {
  $config = array_merge($defaults, Yaml::parse(__DIR__.'/config.yml'));
} catch(Exception $e) {
  echo $e->getMessage();
  exit(-1);
}

define('VAR_PATH', $config['var_path']);

if ( function_exists('fastcgi_finish_request') ) {
  fastcgi_finish_request();
} else {
  flush();
}

set_time_limit(120);

require_once __DIR__ . '/lock_functions.php';

$repo = isset($_GET['package']) ? $_GET['package'] : "ALL PACKAGES";

logEntry("Started", $repo);

$lockHandle = acquireLock($repo);

register_shutdown_function("releaseLock", $lockHandle, $repo);

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

$payload = json_decode( file_get_contents('php://input') );

if ( NULL === $payload ) {
    $errors[] = 'Can\'t parse json payload.';
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        logEntry($error, $repo);
    }
    exit(-1);
}

$repo_ssh = sprintf( 'git@bitbucket.org:%s.git', $payload->repository->full_name );

$command = sprintf('%s build --repository-url=%s %s %s', $config['bin'], $repo_ssh, $config['json'], $config['webroot']);

if (null !== $config['user']) {
    $command = sprintf('sudo -u %s -i %s', $config['user'], $command);
}

$errorBuffer = "";
logEntry( sprintf('Executing: %s', $command), $repo );
$process = new Process($command);
$exitCode = $process->run(function ($type, $buffer) use (&$errorBuffer) {
    if ('err' === $type) {
        $errorBuffer .= $buffer;
    }
});

if(!empty($errorBuffer)) {
    logEntry("Error", $repo);
    logMessage($errorBuffer);
}

$message = $exitCode === 0 ? 'Successful rebuild! Done.' : 'An error occurred! Done.';
logEntry($message, $repo);
