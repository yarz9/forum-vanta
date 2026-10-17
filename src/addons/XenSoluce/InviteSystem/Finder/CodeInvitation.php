<?php

namespace XenSoluce\InviteSystem\Finder;

use XF\Mvc\Entity\Finder;

class CodeInvitation extends Finder
{
     public function dateBetweenInvitation($start, $end)
     {
          $this->where('invitation_date', 'BETWEEN', [$start, $end]);
          return $this;
     }
}
