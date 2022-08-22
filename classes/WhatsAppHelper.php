<?php
namespace Stanford\WhatsAppAlerts;

use \REDCapEntity\Entity;
use \REDCapEntity\EntityFactory;
use \Exception;

/**
 * Class WhatsAppHelper
 *
 * A class that helps log and look up message histories.  We could merge this with the actual EM class as well... Not sure
 *
 * @property WhatsAppAlerts $module
 * @property Entity $entity
 */
class WhatsAppHelper
{
    private $module;        // The parent EM

    public function __construct($module) {
        $this->module    = $module;
    }


    /**
     * Create a new MESSAGE DEFINITION from a config object
     * @param $config
     * @return WhatsAppMessageDefinition
     */
    public function getMessageDefinitionFromConfig($config) {
        $wamd = new WhatsAppMessageDefinition($this->module);
        $wamd->parseDefinition($config);
        return $wamd;
    }

    /**
     * @param $message
     * @return false|WhatsAppMessageDefinition
     */
    public function parseEmailForWhatsAppMessageDefinition($message) {
        $wamd = new WhatsAppMessageDefinition($this->module);
        if ($wamd->parseEmailForWhatsAppMessageDefinition($message)) {
            return $wamd;
        } else {
            return false;
        }
    }


    /**
     * Log a NEW message
     * @param array $payload
     * @return mixed
     */
    public function logNewMessage($payload) {
        $factory = new EntityFactory();
        // $payload = $this->getPayload();
        // xdebug_break();
        $entity = $factory->create('whats_app_message', $payload);
        if (!$entity) {
            // There was an error creating the entity:
            $this->module->emError("Error creating entity with payload", $payload, $factory->errors);
            return false;
        } else {
            $id = $entity->getId();
            // $this->module->emDebug("New message #$id - logged");
            return $id;
        }
    }

    /**
     * @param $message_sid
     * @return mixed
     * @throws Exception
     */
    public function getLogByMessageId($message_sid) {
        // See if there is an existing SID for this message - if so get the latest one
        $factory = new EntityFactory();
        $results = $factory->query('whats_app_message')
            ->condition('message_sid', $message_sid)
            ->orderBy('id', true)
            ->execute();
        if (empty($results)) {
            // There isn't an existing message SID - probably a newly received message
            return false;
        } else {
            // The factory query returns an array - we try to keep one log entry for messageSID so we
            // will just take the first...
            /** @var Entity $entity */
            $entity = array_shift($results);
            return $entity;
        }
    }


    /**
     * @param $number
     * @return array
     */
    public function getRecordsByToNumber($number) {
        $q = $this->module->query('select distinct record_id from redcap_entity_whats_app_message where ' .
            'record_id is not null and to_number = ?',
            [ $number ]
        );
        $records = [];
        while ($row = db_fetch_assoc($q)) $records[] = $row['record_id'];
        return $records;
    }



    /**
     * When a recipient responds to a SMS, it may open up a window to send any queued messages that errored
     * out because they were outside of the window.
     * @param $record_id
     * @param $project_id
     * @return void
     */
    public function getUndeliveredMessages($record_id, $project_id) {
        // Step 1 - get all messages that are undelivered
        xdebug_break();
        $factory = new EntityFactory();
        $entities = $factory->query('whats_app_message')
            ->condition('record_id', $record_id)
            ->condition('status', "undelivered")
            ->condition('source', "Undelivered Message", "!=")
//            ->condition('template_id', NULL)
            ->condition('error', 63016)
            ->condition('project_id', intval($project_id))
            ->orderBy('created')
            ->execute();
        return empty($entities) ? false : $entities;
    }


    /**
     * @param array $new_entry
     * @param array $existing_raw
     * @return array
     */
    public function appendRaw($new_entry, $existing_raw = []) {
        if (is_object($existing_raw)) {
            $existing_raw = json_decode(json_encode($existing_raw),true);
        }
        $i = strval(microtime(true));
        $existing_raw[$i] = $new_entry;
        return $existing_raw;
    }


    /**
     * Makes an array of differences from the last entry...
     * @param $old_raw array
     * @param $new_raw array
     * @return array
     */
    public function diffLastRaw($old_raw, $new_raw) {
        if (empty($old_raw)) {
            return $new_raw;
        } else {
            $last_raw_entry = end($old_raw);
            $this->module->emDebug("Last Raw: " . json_encode($last_raw_entry));
            $diff = [];
            foreach ($new_raw as $k => $v) {
                if (!isset($last_raw_entry[$k]) || $last_raw_entry[$k] !== $v) {
                    $diff[$k] = $v;
                }
            }
            $this->module->emDebug("Diff: " . json_encode($diff));
            return $diff;

        }
    }

}

