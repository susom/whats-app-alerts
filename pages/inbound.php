<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

$module->emDebug($_POST);

$wam = new WhatsAppMessage($module);

$result = $wam->processInboundMessage();

if ($record_id = $wam->getIcebreakerNeeded()) {
    // We need to send an icebreaker
    try {
        $wam2 = new WhatsAppMessage($module);



        $t = new Template($module);
        $msg = $t->getIcebreakerMessage($record_id);



    } catch (\Exception $e) {
        $module->emError("Unable to load icebreaker template: " . $e->getMessage());
    }
}

