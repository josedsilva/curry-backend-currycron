# curry-backend-currycron
A unified cron handler for CurryCMS.

Setup:

1. Install the following dependencies:
   `composer require mtdowling/cron-expression monolog/monolog`
1. Merge the folder structure with your project.
1. Migrate or rebuild your database.
1. For web/browser execution hash, update cms/config/config.php and add a new config param:
   `'modules' => array('contrib' => array('CurryCron' => array('token' => 'some-random-string')))`


