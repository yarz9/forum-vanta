<?php

namespace Vanta\Loader\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Ban extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->tableName = 'xf_vanta_ban';
        $structure->shortName = 'Ban';
        $structure->namespace = 'Vanta\Loader\Entity';

        $structure->columns = [
            'ban_id' => ['type' => 'uint', 'autoIncrement' => true, 'required' => true],
            'user_id' => ['type' => 'uint', 'nullable' => true],
            'hwid_hash' => ['type' => 'binary', 'typeParams' => ['length' => 64], 'nullable' => true],
            'reason' => ['type' => 'string', 'maxLength' => 255, 'required' => true],
            'created_by' => ['type' => 'uint', 'required' => true],
            'created_by_username' => ['type' => 'string', 'maxLength' => 50, 'required' => true],
            'created' => ['type' => 'uint', 'required' => true],
            'expires' => ['type' => 'uint', 'nullable' => true],
            'type' => ['type' => 'string', 'maxLength' => 20, 'required' => true],
        ];

        $structure->primaryKey = 'ban_id';
    }

    public function isExpired(): bool
    {
        if ($this->expires === null) {
            return false;
        }
        return time() >= $this->expires;
    }
}
