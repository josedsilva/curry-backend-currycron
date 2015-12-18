<?php

use Cron\CronExpression;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\HtmlFormatter;

class CronJob_Cron extends CronJob_BaseAbstract
{
    protected function getQueuedJobs()
    {
        $jobs = CronJobQuery::create()
            ->joinWithCronJobSchedule()
            ->filterByActive(true)
            ->filterByType('cron')
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
        $this->log($queuedJobs->count().' queued cron job(s) found.');
        foreach ($queuedJobs as $job) {
            $this->log('Executing cron job: '.$job->getFQJobHandler().' ...');
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
