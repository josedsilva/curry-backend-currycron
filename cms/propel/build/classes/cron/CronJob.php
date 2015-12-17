<?php



/**
 * Skeleton subclass for representing a row from the 'cron_job' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.cron
 */
class CronJob extends BaseCronJob
{
    
    public function __toString()
    {
        return $this->getJobClass().'::'.$this->getJobHandler();
    }
    
    /**
     * Return the Fully Qualified job handler name.
     */
    public function getFQJobHandler()
    {
        return (string) $this;
    }
    
    /**
     * Similar to $this->getData()
     * but attempts to unserialize the stored value.
     * NOTE: we do not override the getData() method
     * because this has negative implications when backing up and restoring
     * the database with Curry's backup/restore feature.
     * @return  mixed
     */
    public function getParsedData()
    {
        $data = parent::getData();
        if (!is_null($data)) {
            $ret = @unserialize($data);
            if ($ret === false) {
                $ret = $data;
            }
            return $ret;
        }
        return;
    }
    
    /**
     * Serialize an object or array
     * before storing into the database.
     * @param mixed $v
     */
    public function setData($v)
    {
        try {
            $content = is_scalar($v) ? $v : serialize($v);
        } catch (Exception $e) {
            $content = $v;
        }
        return parent::setData($content);
    }
    
    /**
     * Helper method which can be used in your module to schedule a cron job.
     * This method shows a schedule form and saves the data to the database.
     * @param Curry_Backend $context
     * @param string $view  The view/method name from which this method is called.
     * @param string $jobHandler    The job handler method name.
     * @param null|Curry_From_SubForm $dataForm     Form to capture data that will be passed to the job handler at runtime.
     */
    public static function handleJobScheduler($context, $view = null, $jobHandler = null, $dataForm = null)
    {
        $ctxRC = new ReflectionClass(get_class($context));
        $jobClass = $ctxRC->getName();
        $cronJobTableMap = CronJobPeer::getTableMap();
        if (is_null($jobHandler)) {
            $jobHandler = $cronJobTableMap->getColumn('job_handler')->getDefaultValue();
        }
        
        if (is_null($view)) {
            $view = 'Main';
        }
        
        $form = new Curry_Form_ModelForm('CronJobSchedule', array(
            'elements' => array(
                'module_view' => array('hidden', array('value' => $view)),
                'job_class' => array('hidden', array('value' => $jobClass)),
                'job_handler' => array('text', array(
                    'label' => 'Job handler',
                    'value' => $jobHandler,
                    'order' => 0,
                    'readonly' => true,
                )),
            ),
        ));
        if ($dataForm instanceof Curry_Form_SubForm) {
            $form->addSubForm($dataForm, 'cronjob_data');
        }
        
        $form->addElement('checkbox', 'active', array(
            'label' => 'Active',
        ));
        $form->addElement('submit', 'schedule', array('label' => 'Schedule', 'class' => 'btn btn-primary'));
        
        if (isPost() && $form->isValid($_POST)) {
            static::scheduleJob($form);
            $context->addMessage('Job is scheduled.', $ctxRC->getConstant('MSG_SUCCESS'));
        } else {
            $job = CronJobQuery::create()
                ->joinWithCronJobSchedule()
                ->filterByJobClass($jobClass)
                ->filterByJobHandler($jobHandler)
                ->findOne();
            
            if ($job) {
                $form->fillForm($job->getCronJobSchedule());
                $form->getElement('active')->setValue($job->getActive());
                if ($dataForm instanceof Curry_Form_SubForm) {
                    self::fillDataForm($form->getSubForm('cronjob_data'), $job);
                }
            } else {
                $form->fillForm(new CronJobSchedule());
                $form->getElement('active')->setValue($cronJobTableMap->getColumn('active')->getDefaultValue());
            }
        }
        
        $context->addMainContent($form);
    }
    
    private static function fillDataForm(Curry_Form_SubForm $form, CronJob $job)
    {
        $data = $job->getParsedData();
        if (is_array($data)) {
            foreach ($data as $field => $value) {
                $form->getElement($field)->setValue($value);
            }
        }
    }
    
    private static function scheduleJob(Curry_Form_ModelForm $form)
    {
        $values = $form->getValues(true);
        $job = CronJobQuery::create()
            ->joinWithCronJobSchedule()
            ->filterByJobClass($values['job_class'])
            ->filterByJobHandler($values['job_handler'])
            ->findOne();
        
        if (!$job) {
            $job = new CronJob();
        }
        
        $con = Propel::getConnection(CronJobPeer::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $job->setJobClass($values['job_class'])
                ->setJobHandler($values['job_handler'])
                ->setModuleView($values['module_view'])
                ->setActive($values['active']);
            if (isset($values['cronjob_data'])) {
                $job->setData($values['cronjob_data']);
            }
            
            if (!$job->isNew()) {
                $job->getCronJobSchedule()->delete();
            }
            
            $job->save($con);
            
            $jobSchedule = new CronJobSchedule();
            $jobSchedule->setPrimaryKey($job->getPrimaryKey());
            $form->fillModel($jobSchedule);
            $jobSchedule->save($con);
            
            $con->commit();
        } catch (Exception $e) {
            $con->rollback();
            throw $e;
        }
    }
    
}
