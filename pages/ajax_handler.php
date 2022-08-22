<?php

namespace Stanford\WhatsAppAlerts;

/** @var WhatsAppAlerts $module */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $module->getMessagesByPhoneNumber(filter_var($_POST['phoneNumber'], FILTER_SANITIZE_STRING));
    print($result);
    exit();
}
