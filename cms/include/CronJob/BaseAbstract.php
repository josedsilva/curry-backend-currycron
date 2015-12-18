<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\HtmlFormatter;

abstract class CronJob_BaseAbstract
{
    /** @var DateTime */
    protected $now;
    
    /** @var boolean */
    protected $isConsole;
    
    protected $logger;
    protected $logFile;
    
    public function __construct()
    {
        $this->now = new \DateTime('now');
        $this->isConsole = $this->getIsConsole();
        $this->validateToken();
        $this->initLogger();
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
        $class = get_called_class();
        $this->logger = new Logger($class);
        $logPath = realpath(dirname(Curry_Core::$config->curry->configPath) . '/../data/log');
        $this->logFile = $logPath.'/'.str_replace('_', '-', strtolower($class)).' - '.date('Y-m-d-H-i-s').'.log';
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
    
    abstract public function run();

}
