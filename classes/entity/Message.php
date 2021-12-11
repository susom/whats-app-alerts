<?php

namespace Stanford\WhatsAppAlerts\Entity;

use REDCapEntity\Entity;
use REDCapEntity\EntityFactory;

class Message extends Entity
{

    function __construct(\REDCapEntity\EntityFactory $factory, $entity_type, $id = null)
    {
        parent::__construct($factory, $entity_type, $id);
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
