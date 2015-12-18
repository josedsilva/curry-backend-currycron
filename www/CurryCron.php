<?php
/**
 * Execute cron jobs scheduled with Common_Backend_CronJobScheduler.
 *
 * @package Curry
 * @author Jose F. D'Silva
 */

require_once 'init.php';

set_time_limit(0);
$cron = new CronJob_Cron();
$cron->log('CurryCron started executing at '.date('Y-m-d H:i:s'));
$cron->run();
$cron->log('CurryCron finished executing at '.date('Y-m-d H:i:s'));
$cron->terminate();
