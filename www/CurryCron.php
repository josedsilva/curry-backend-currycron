<?php
/**
 * Execute cron jobs scheduled with Common_Backend_CronJobScheduler.
 *
 * @package Curry
 * @author Jose F. D'Silva
 *
 */

require_once 'init.php';
use Cron\CronExpression;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\HtmlFormatter;

class CurryCron
{
    /** @var CurryCron */
    private static $instance = null;
    
    /** @var DateTime */
    protected $now;
    
    /** @var boolean */
    protected $isConsole;
    
    protected $logger;
    protected $logFile;
    
    protected function __construct()
    {
        $this->now = new \DateTime('now');
        $this->isConsole = $this->getIsConsole();
        $this->validateToken();
        $this->initLogger();
    }
    
    private function __clone()
    {
        
    }
    
    private function __wakeup()
    {
        
    }
    
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }
    
    /**
     * determine whether we are executing in a console or browser.
     * @return boolean
     */
    protected function getIsConsole()
    {
        if (function_exists('php_sapi_name')) {
            return (php_sapi_name() === 'cli');
        }
        
        return !((boolean) $_SERVER['REQUEST_METHOD']);
    }
    
    protected function initLogger()
    {
        $this->logger = new Logger(__CLASS__);
        $logPath = realpath(dirname(Curry_Core::$config->curry->configPath) . '/../data/log');
        $this->logFile = $logPath.'/currycron-'.date('Y-m-d-H-i-s').'.log';
        $handler = new StreamHandler($this->logFile, Logger::DEBUG);
        if (!$this->isConsole) {
            $handler->setFormatter(new HtmlFormatter());
        }
        
        $this->logger->pushHandler($handler);
    }
    
    protected function validateToken()
    {
        if ($this->isConsole) {
            return;
        }
        
        if (!isset($_GET['hash'])) {
            throw new Exception('Missing cron execution hash parameter.');
        }
        
        if (!Common_Backend_CronJobScheduler::isValidCronExecutionHash($_GET['hash'])) {
            throw new Exception('Invalid cron execution hash.');
        }
    }
    
    protected function getQueuedJobs()
    {
        $jobs = CronJobQuery::create()
            ->joinWithCronJobSchedule()
            ->filterByActive(true)
            ->find();
        
        $ret = clone $jobs;
        foreach ($jobs as $key => $job) {
            $cron = CronExpression::factory($this->getCronExpression($job));
            if (!$cron->isDue($this->now)) {
                $ret->remove($key);
            }
            unset($cron);
        }
        
        unset($jobs);
        return $ret;
    }
    
    protected function getCronExpression(CronJob $job)
    {
        return sprintf('%s %s %s %s %s %s',
            $job->getCronJobSchedule()->getMinute(),
            $job->getCronJobSchedule()->getHour(),
            $job->getCronJobSchedule()->getDay(),
            $job->getCronJobSchedule()->getMonth(),
            $job->getCronJobSchedule()->getWeekDay(),
            $job->getCronJobSchedule()->getYear());
    }
    
    public function run()
    {
        $queuedJobs = $this->getQueuedJobs();
        $this->log($queuedJobs->count().' queued job(s) found.');
        foreach ($queuedJobs as $job) {
            $this->log('Executing job: '.$job->getFQJobHandler().' ...');
            $jobClass = $job->getJobClass();
            $jobHandler = $job->getJobHandler();
            $task = new $jobClass;
            try {
                $task->{$jobHandler}($this->logger, $job->getData());
            } catch (Exception $e) {
                $this->log($e->getErrorMessage(), Logger::ERROR);
            }
            $this->log("\nFinished task.");
        }
    }
    
    public function log($msg, $level = Logger::INFO, array $context = array())
    {
        return $this->logger->log($level, $msg, $context);
    }
    
    public function terminate()
    {
        if (!$this->isConsole) {
            print file_get_contents($this->logFile);
        }
    }
    
}

set_time_limit(0);
$cron = CurryCron::getInstance();
$cron->log('CurryCron started executing at '.date('Y-m-d H:i:s'));
$cron->run();
$cron->log('CurryCron finished executing at '.date('Y-m-d H:i:s'));
$cron->terminate();
