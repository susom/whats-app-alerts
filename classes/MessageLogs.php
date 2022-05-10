<?php

namespace Stanford\WhatsAppAlerts;

use REDCapEntity\Entity;
use REDCapEntity\EntityFactory;
use \Exception;

class MessageLogs extends Entity
{

    /**
     * @throws Exception
     */
    function __construct(\REDCapEntity\EntityFactory $factory, $entity_type, $id = null)
    {
        parent::__construct($factory, $entity_type, $id);
    }

    // TODO: Consider adding many of the logging/entity functions to this class


    /**
     * Get just a specific value from the entity
     * @throws Exception
     */
    function getDataValue($key) {
        if (isset($this->data[$key])) {
            $value = $this->data[$key];
            if ($this->entityTypeInfo['properties'][$key]['type'] == 'json' && is_string($value)) {
                $value = json_decode($value);
            }
            return $value;
        } else {
            throw new Exception("The property $key is not defined.");
        }
    }



    // function validateProperty($key, $value)
   //  {
   //      // if ($key != 'pid' || PAGE != 'ProjectGeneral/create_project.php') {
   //          return parent::validateProperty($key, $value);
   //      // }
   //
   //      // Overriding validation of project ID property on project creation.
   //      // return !empty($value) && intval($value) == $value;
   //  }
}
