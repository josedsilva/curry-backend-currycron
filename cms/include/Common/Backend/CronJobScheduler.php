<?php

/**
 * Manage cron jobs.
 *
 * @package     Curry
 * @author      Jose F. D'Silva
 *
 */
class Common_Backend_CronJobScheduler extends Curry_Backend
{
    
    public static function getGroup()
    {
        return 'Cron';
    }
    
    public function __construct()
    {
        parent::__construct();
        $this->renderMenu();
    }
    
    protected function renderMenu()
    {
        $this->addMenuItem('Job schedules', url('', array('module', 'view' => 'Main')));
        $this->addMenuItem('Settings', url('', array('module', 'view' => 'Settings')));
    }
    
    public function showMain()
    {
        // the Add/Edit form.
        $form = new Curry_Form_ModelForm('CronJobSchedule', array(
            'withRelations' => array('CronJob'),
            'columnElements' => array(
                'relation__cronjob' => array('select', array(
                    'multiOptions' => array(null => '[ Select a job ]') + CronJobQuery::create()
                        ->find()
                        ->toKeyValue('PrimaryKey', 'FQJobHandler'),
                    'order' => 0,
                )),
            ),
            'onFillForm' => function($cronJobSchedule, $form)
            {
            }
        ));
        
        $list = new Curry_ModelView_List('CronJobSchedule', array(
            'modelForm' => $form,
            'columns' => array(
                'fq_job_handler' => array(
                    'label' => 'Job handler',
                    'callback' => function($cronJobSchedule)
                    {
                        return $cronJobSchedule->getCronJob()->getFQJobHandler();
                    },
                    'order' => 0,
                    'action' => 'edit',
                ),
            ),
            'actions' => array(
                'action_edit_data' => array(
                    'label' => 'Edit job data',
                    'href' => (string) url('', array('module', 'view' => 'MainEditJobData')),
                    'single' => true,
                ),
            ),
        ));
        $list->show($this);
    }
    
    /**
     * Redirect the user to the appropriate Backend module (job).
     */
    public function showMainEditJobData()
    {
        $job = CronJobQuery::create()
            ->findPk($_GET['item']);
        $url = url('', array('module' => $job->getJobClass()));
        if ($job->getModuleView()) {
            $url->add(array('view' => $job->getModuleView()));
        }
        
        $url->redirect();
        exit();
    }
    
    public function showSettings()
    {
        $form = $this->getSettingsForm();
        if (isPost() && $form->isValid($_POST)) {
            $values = $form->getValues(true);
            $this->saveSettings($values);
            $this->addMessage('Settings saved', self::MSG_SUCCESS);
            return;
        }
        
        $this->addMainContent($form);
        $this->addMessage('You can also execute project cron tasks from this url: '.url(Curry_Core::$config->curry->baseUrl.'CurryCron.php/', array('hash' => self::getCronExecutionHash()))->getAbsolute());
    }
    
    protected function getSettingsForm()
    {
        return new Curry_Form(array(
            'url' => url('', $_GET),
            'method' => 'post',
            'elements' => array(
                'token' => array('text', array(
                    'label' => 'Token',
                    'value' => Curry_Core::$config->modules->contrib->CurryCron->token,
                    'required' => true,
                )),
                'save' => array('submit', array('label' => 'Save')),
            ),
        ));
    }
    
    protected function saveSettings($values)
    {
        $config = new Zend_Config(require(Curry_Core::$config->curry->configPath), true);
        $config->modules->contrib->CurryCron->token = $values['token'];
        $writer = new Zend_Config_Writer_Array();
        $writer->write(Curry_Core::$config->curry->configPath, $config);
    }
    
    public static function getCronExecutionHash()
    {
        return sha1(Curry_Core::$config->modules->contrib->CurryCron->token . Curry_Core::$config->curry->secret);
    }
    
    public static function isValidCronExecutionHash($hash)
    {
        return $hash == self::getCronExecutionHash();
    }
    
    
}