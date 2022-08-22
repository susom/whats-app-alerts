<?php
namespace Stanford\WhatsAppAlerts;

/** @var WhatsAppAlerts $module */

use REDCapEntity\EntityList;
use REDCapEntity\EntityFactory;

$list = new EntityList('whats_app_message', $module);
$factory = new EntityFactory();
$results = $factory->query('whats_app_message')
    ->condition('project_id', PROJECT_ID)
    ->execute();
$list->setOperations(['delete'])
    ->setCols(['id','message_sid', 'body', 'record_id','message', 'to_number', 'from_number', 'status', 'project_id'])
    ->render('project'); // Context: Control Center.
