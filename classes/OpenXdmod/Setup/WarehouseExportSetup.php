<?php
/**
 * @author Jeffrey T. Palmer <jtpalmer@buffalo.edu>
 */

namespace OpenXdmod\Setup;

use Exception;

/**
 * Data warehouse batch export setup.
 */
class WarehouseExportSetup extends SetupItem
{
    /**
     * Configure data warehouse export.
     *
     * @see \OpenXdmod\Setup\SetupItem::handle()
     */
    public function handle()
    {
        $this->console->displaySectionHeader('Data Warehouse Batch Export');
        $newSettings = $this->promptForSettings($this->loadIniConfig('portal_settings'));
        $this->saveIniConfig($newSettings, 'portal_settings');
    }

    /**
     * Prompt user for settings.
     *
     * This function is public so that it may also be used during the upgrade
     * process.
     *
     * @param array $settings Current settings.
     * @return array New settings.
     */
    public function promptForSettings(array $settings)
    {
        $this->console->displayMessage(<<<'MSG'
The data warehouse batch export feature allows users to create requests to
export data which is then generated by a cron job and stored on the server.
The directory where this data is stored and the duration that the data will be
retained are configurable.
MSG
        );
        $this->console->displayBlankLine();
        $exportDir = $this->console->prompt(
            'Export Directory:',
            $settings['data_warehouse_export_export_directory']
        );

        try {
            $this->checkExportDirectory($exportDir);
        } catch (Exception $e) {
            $this->console->displayMessage('There was an error while updating the export directory: ' . $e->getMessage());
            $this->console->displayMessage('You must manually create the directory and set permissions');
        }
        $settings['data_warehouse_export_export_directory'] = $exportDir;

        $settings['data_warehouse_export_retention_duration_days'] = $this->promptForRetentionDuration($settings['data_warehouse_export_retention_duration_days']);

        if (empty($settings['data_warehouse_export_hash_salt'])) {
            $settings['data_warehouse_export_hash_salt'] = bin2hex(random_bytes(32));
        }

        return $settings;
    }

    /**
     * Prompt the user for the export file retention duration.
     *
     * @param int $defaultDuration The default retention duration.
     * @return int The user's response.
     */
    private function promptForRetentionDuration($defaultDuration)
    {
        $haveResponse = false;
        $retentionDuration = $defaultDuration;

        while (!$haveResponse) {
            $retentionDuration = $this->console->prompt(
                'Export File Retention Duration in Days:',
                $defaultDuration
            );

            if (filter_var($retentionDuration, FILTER_VALIDATE_INT) !== false
                && $retentionDuration > 0) {
                $haveResponse = true;
            } else {
                $this->console->displayMessage('The export file retention duration must be a positive integer.');
            }
        }

        return $retentionDuration;
    }

    /**
     * Check the export directory.
     *
     * Checks that the export directory exists, has the correct ownership and
     * permissions.  Prompts the user if the directory needs to be created or
     * changed.
     *
     * @param string $dir Path of the export directory.
     */
    private function checkExportDirectory($dir)
    {
        // Desired attributes.
        $desiredPerms = 0570;
        $desiredUser = 'apache';
        $desiredGroup = 'xdmod';

        $this->console->displayMessage(<<<"MSG"
If the export directory does not exist, it must be created and assigned the
correct permissions and ownership.  It must be readable by the web server and
both readable and writable by the user that is used to generate the export
files.  By default, the web server user is expected to be {$desiredUser} and
the group is expected to be {$desiredGroup}.  If your system uses a different
user and group then the automatic process will fail and you must set the
permissions manually.
MSG
        );
        $this->console->displayBlankLine();

        if (!is_dir($dir)) {
            $response = $this->console->prompt(
                'Export directory does not exist. Create and set permissions?',
                'yes',
                ['yes', 'no']
            );
            if ($response !== 'yes') {
                return;
            }
            if (!mkdir($dir, $desiredPerms, true)) {
                throw new Exception(sprintf(
                    'Failed to create directory "%s"',
                    $dir
                ));
            }
            if (!chmod($dir, $desiredPerms)) {
                throw new Exception(sprintf(
                    'Failed to change permissions of "%s"',
                    $dir
                ));
            }
            if (!chown($dir, $desiredUser)) {
                throw new Exception(sprintf(
                    'Failed to change owner of "%s"',
                    $dir
                ));
            }
            if (!chgrp($dir, $desiredGroup)) {
                throw new Exception(sprintf(
                    'Failed to change group of "%s"',
                    $dir
                ));
            }
        }

        $perms = fileperms($dir) & 0777;
        if ($perms != $desiredPerms) {
            $this->console->displayMessage(sprintf(
                'Directory permissions are "%o", expected "%o".',
                $perms,
                $desiredPerms
            ));
            $response = $this->console->prompt(
                'Update permissions?',
                'yes',
                ['yes', 'no']
            );
            if ($response != 'no') {
                if (!chmod($dir, $desiredPerms)) {
                    throw new Exception(sprintf(
                        'Failed to change permissions of "%s"',
                        $dir
                    ));
                }
            }
        }

        $user = posix_getpwuid(fileowner($dir))['name'];
        if ($user != $desiredUser) {
            $this->console->displayMessage(sprintf(
                'Directory owner is "%s", expected "%s".',
                $user,
                $desiredUser
            ));
            $response = $this->console->prompt(
                'Update owner?',
                'yes',
                ['yes', 'no']
            );
            if ($response != 'no') {
                if (!chown($dir, $desiredUser)) {
                    throw new Exception(sprintf(
                        'Failed to change owner of "%s"',
                        $dir
                    ));
                }
            }
        }

        $group = posix_getgrgid(filegroup($dir))['name'];
        if ($group != $desiredGroup) {
            $this->console->displayMessage(sprintf(
                'Directory group is "%s", expected "%s".',
                $group,
                $desiredGroup
            ));
            $response = $this->console->prompt(
                'Update group?',
                'yes',
                ['yes', 'no']
            );
            if ($response != 'no') {
                if (!chgrp($dir, $desiredGroup)) {
                    throw new Exception(sprintf(
                        'Failed to change group of "%s"',
                        $dir
                    ));
                }
            }
        }
    }
}
