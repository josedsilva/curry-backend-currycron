<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\HtmlFormatter;

class CronJob_Job extends CronJob_BaseAbstract
{
    protected function getQueuedJobs()
    {
        return CronJobQuery::create()
            ->filterByActive(true)
            ->filterByType('job')
            ->find();
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
                $task->{$jobHandler}($this->logger, $job->getParsedData());
            } catch (Exception $e) {
                $this->log($e->getErrorMessage(), Logger::ERROR);
            }
            $this->log("\nFinished task.");
        }
    }

}
