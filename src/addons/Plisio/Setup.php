<?php

namespace Plisio;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;
    
    /**
     * Performs an installation procedure (creating a payment provider).
     *
     * @throws \XF\PrintableException
     */
    public function installStep1()
    {
        /** @var \XF\Entity\PaymentProvider $provider */
        $provider = $this->em()->create('XF:PaymentProvider');
        $provider->bulkSet([
            'provider_id'       => 'Plisio',
            'provider_class'    => 'Plisio:PlisioGateway',
            'addon_id'          => $this->addOn->getAddOnId()
        ]);
        $provider->save();
    }
    
    /**
     * Performs an uninstallation procedure (removing a payment provider).
     *
     * @throws \XF\PrintableException
     */
    public function uninstallStep1()
    {
        /** @var \XF\Entity\PaymentProvider $provider */
        $provider = $this->em()->find('XF:PaymentProvider', 'Plisio');
        if ($provider)
        {
            $provider->delete();
        }
    }
    
    /**
     * @return \XF\Mvc\Entity\Manager
     */
    protected function em()
    {
        return $this->app->em();
    }
}