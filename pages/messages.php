<?php
namespace Stanford\WhatsAppAlerts;

/** @var WhatsAppAlerts $module */

use REDCapEntity\EntityList;

$list = new EntityList('whats_app_message', $module);
$list->setOperations(['create', 'update', 'delete']) // Enabling all operations.
->render('project'); // Context: Control Center.
