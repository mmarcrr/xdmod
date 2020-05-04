<?php

namespace DataWarehouse\Export;

use DataWarehouse\Access\BatchExport;
use DataWarehouse\Data\RawStatisticsConfiguration;
use Exception;
use Models\Services\Realms;
use XDUser;

/**
 * Manage data regarding realms available for batch export.
 */
class RealmManager
{
    /**
     * Raw statistics configuration.
     * @var \DataWarehouse\Data\RawStatisticsConfiguration
     */
    private $config;

    /**
     * Prepare database connection and load configuration.
     */
    public function __construct()
    {
        $this->config = RawStatisticsConfiguration::factory();
    }

    /**
     * Get an array of all the batch exportable realms.
     *
     * @return \Models\Realm[]
     */
    public function getRealms()
    {
        // The "name" values from rawstatistics match those in
        // the moddb.realms.display column.
        $exportable = array_map(
            function ($realm) {
                return $realm['name'];
            },
            $this->config->getBatchExportRealms()
        );

        return array_filter(
            Realms::getRealms(),
            function ($realm) use ($exportable) {
                return in_array($realm->getDisplay(), $exportable);
            }
        );
    }

    /**
     * Get an array of all the batch exportable realms for a user.
     *
     * @param \XDUser $user
     * @return \Models\Realm[]
     */
    public function getRealmsForUser(XDUser $user)
    {
        return array_filter(
            $this->getRealms(),
            function ($realm) use ($user) {
                return BatchExport::realmExists($user, $realm->getDisplay());
            }
        );
    }

    /**
     * Get the raw data query class for the given realm.
     *
     * @param string $realmName The realm name used in moddb.realms.name.
     * @return string The fully qualified name of the query class.
     */
    public function getRawDataQueryClass($realmName)
    {
        // The query classes use the "name" from the rawstatistics
        // configuration, but the realm name is taken from moddb.realms.name.
        // These use the same "display" name so that is used to find the
        // correct class name.

        // Realm model.
        $realmObj = null;

        foreach ($this->getRealms() as $realm) {
            if ($realm->getName() == $realmName) {
                $realmObj = $realm;
                break;
            }
        }

        if ($realmObj === null) {
            throw new Exception(
                sprintf('Failed to find model for realm "%s"', $realmName)
            );
        }

        // Realm rawstatistics configuration.
        $realmConfig = null;

        foreach ($this->config->getBatchExportRealms() as $realm) {
            if ($realm['display'] == $realmObj->getDisplay()) {
                $realmConfig = $realm;
                break;
            }
        }

        if ($realmConfig === null) {
            throw new Exception(
                sprintf(
                    'Failed to find rawstatistics configuration for realm "%s"',
                    $realmName
                )
            );
        }

        return sprintf('\DataWarehouse\Query\%s\JobDataset', $realmConfig['name']);
    }
}
