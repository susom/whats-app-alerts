<?php

namespace Stanford\WhatsAppAlerts;
/** @var \Stanford\WhatsAppAlerts\WhatsAppAlerts $module */

use Stanford\WhatsAppAlerts\Template;

$t = new Template($module);

// Pull the latest templates from Twilio's API
$r = $t->refreshTemplates(true);

?>

<h3>Twilio Templates formatted for What's App EM Storage</h3>

<pre>
    <?php echo print_r($r,true); ?>
</pre>
