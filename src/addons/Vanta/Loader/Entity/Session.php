<?php

namespace Vanta\Loader\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Session extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->tableName = 'xf_vanta_session';
        $structure->shortName = 'Session';
        $structure->namespace = 'Vanta\Loader\Entity';

        $structure->columns = [
            'session_token' => ['type' => 'binary', 'typeParams' => ['length' => 32], 'required' => true],
            'user_id' => ['type' => 'uint', 'required' => true],
            'token_hash' => ['type' => 'binary', 'typeParams' => ['length' => 64], 'required' => true],
            'created' => ['type' => 'uint', 'required' => true],
            'expires' => ['type' => 'uint', 'required' => true],
            'device_token' => ['type' => 'binary', 'typeParams' => ['length' => 32], 'nullable' => true],
            'login_ip' => ['type' => 'uint', 'nullable' => true],
            'login_user_agent' => ['type' => 'string', 'maxLength' => 255, 'nullable' => true],
            'last_activity' => ['type' => 'uint', 'required' => true],
        ];

        $structure->primaryKey = 'session_token';
    }
}
