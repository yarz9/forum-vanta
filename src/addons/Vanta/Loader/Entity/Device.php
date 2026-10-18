<?php

namespace Vanta\Loader\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Device extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->tableName = 'xf_vanta_device';
        $structure->shortName = 'Device';
        $structure->namespace = 'Vanta\Loader\Entity';

        $structure->columns = [
            'device_id' => ['type' => 'uint', 'autoIncrement' => true, 'required' => true],
            'user_id' => ['type' => 'uint', 'required' => true],
            'hwid_hash' => ['type' => 'binary', 'typeParams' => ['length' => 64], 'required' => true],
            'device_name' => ['type' => 'string', 'maxLength' => 255, 'required' => true],
            'created' => ['type' => 'uint', 'required' => true],
            'last_seen' => ['type' => 'uint', 'required' => true],
            'create_ip' => ['type' => 'string', 'maxLength' => 45, 'nullable' => true],
        ];

        $structure->primaryKey = 'device_id';
        $structure->unique = ['user_id', 'hwid_hash'];
    }
}
