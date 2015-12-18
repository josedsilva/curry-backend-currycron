<?php
/**
 * Execute ordinary jobs queued with Common_Backend_CronJobScheduler.
 *
 * @package Curry
 * @author Jose F. D'Silva
 */

require_once 'init.php';

set_time_limit(0);
$job = new CronJob_Job();
$job->log('CurryJob started executing at '.date('Y-m-d H:i:s'));
$job->run();
$job->log('CurryJob finished executing at '.date('Y-m-d H:i:s'));
$job->terminate();
