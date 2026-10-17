<?php
/*************************************************************************
 * Invite System - Xen-Soluce (c) 2019-2023
 * All Rights Reserved.
 * Created by SyTy and CRUEL-MODZ
 *************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at https://xen-soluce.com/help/license-agreement/.
 *************************************************************************/

namespace XenSoluce\InviteSystem\Install;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Exception;
use XF\Db\Schema\Alter;

abstract class AbstractInstall extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    abstract protected function getTables(): array;
    abstract protected function getAlterDefinitions(): array;

    abstract protected function getQuery(): string;
    abstract protected function getQueryUninstall(): string;

    /**
     *
     */
    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() AS $tableName => $closure)
        {
            $sm->createTable($tableName, $closure);
        }
    }


    /**
     * @param string $tableName
     * @return void
     */
    public function installByTable(string $tableName): void
    {
        $sm = $this->schemaManager();

        if(isset($this->getTables()[$tableName])) {
            $sm->createTable($tableName, $this->getTables()[$tableName]);
        }
    }

    /**
     * @param array $tablesName
     * @return void
     */
    public function installByTables(array $tablesName): void
    {
        foreach ($tablesName as $tableName)
        {
            $this->installByTable($tableName);
        }
    }

    /**
     *
     */
    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() AS $tableName => $closure)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $closure);
            }
        }
    }

    /**
     * @return array
     */
    protected function getAlterTables()
    {
        $tables = [];
        $alterDefinitions = $this->getAlterDefinitions();

        foreach ($alterDefinitions as $key => $definitions)
        {
            $tables[$key] = function(Alter $table) use ($definitions)
            {
                foreach ($definitions['columns'] as $name => $definition)
                {
                    $column = $table->addColumn(
                        $name,
                        $definition['type'],
                        $definition['length'] ?? null
                    );

                    if (isset($definition['default']))
                    {
                        $column->setDefault($definition['default']);
                    }

                    if (isset($definition['nullable']))
                    {
                        $column->nullable($definition['nullable']);
                    }

                    if (isset($definition['after']))
                    {
                        $column->after($definition['after']);
                    }
                }

                if (isset($definitions['keys']))
                {
                    foreach ($definitions['keys'] as $indexName => $column)
                    {
                        $table->addKey($column, $indexName);
                    }
                }
            };
        }

        return $tables;
    }

    /**
     * @throws Exception
     */
    protected function executeQuery()
    {
        $db = $this->db();
        $query = $this->getQuery();

        if($query !== '') {
            $db->query($query);
        }
    }

    /**
     * @param array $stateChanges
     * @return void
     * @throws Exception
     */
    public function postInstall(array &$stateChanges): void
    {
        $this->executeQuery();
    }

    /**
     *
     */
    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach (array_keys($this->getTables()) AS $tableName)
        {
            $sm->dropTable($tableName);
        }
    }

    /**
     *
     */
    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterDefinitions() AS $tableName => $definitions)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, function(Alter $table) use ($definitions)
                {
                    if (isset($definitions['columns']))
                    {
                        $table->dropColumns(array_keys($definitions['columns']));
                    }

                    if (isset($definitions['keys']))
                    {
                        $table->dropIndexes(array_keys($definitions['keys']));
                    }
                });
            }
        }
    }

    /**
     * @throws Exception
     */
    public function uninstallStep3()
    {
        $db = $this->db();
        $query = $this->getQueryUninstall();

        if($query !== '') {
            $db->query($query);
        }
    }
}