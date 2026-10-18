<?php

namespace Vanta\Loader\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Reset extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->tableName = 'xf_vanta_reset';
        $structure->shortName = 'Reset';
        $structure->namespace = 'Vanta\Loader\Entity';

        $structure->columns = [
            'reset_id' => ['type' => 'uint', 'autoIncrement' => true, 'required' => true],
            'user_id' => ['type' => 'uint', 'required' => true],
            'hwid_hash' => ['type' => 'binary', 'typeParams' => ['length' => 64], 'required' => true],
            'reason' => ['type' => 'string', 'maxLength' => 255, 'required' => true],
            'requested_by' => ['type' => 'uint', 'required' => true],
            'requested_by_username' => ['type' => 'string', 'maxLength' => 50, 'required' => true],
            'approved_by' => ['type' => 'uint', 'nullable' => true],
            'approved_by_username' => ['type' => 'string', 'maxLength' => 50, 'nullable' => true],
            'created' => ['type' => 'uint', 'required' => true],
            'processed' => ['type' => 'uint', 'nullable' => true],
            'status' => ['type' => 'string', 'maxLength' => 20, 'default' => 'pending'],
        ];

        $structure->primaryKey = 'reset_id';
    }
}
