<?php
namespace Stanford\WhatsAppAlerts;

/** @var WhatsAppAlerts $module */

use REDCapEntity\EntityList;

$list = new EntityList('whats_app_message', $module);

$list->setOperations(['delete'])
// ->setCols(['id','message_sid', 'record_id','message', 'status'])
->render('project'); // Context: Control Center.
