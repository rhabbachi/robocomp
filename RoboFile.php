<?php

require_once __DIR__ . '/vendor/autoload.php';

use Robo\Robo;
use Symfony\Component\Yaml\Yaml;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{

    public static $APP_CONFIG_PATH = "./app.yml";
    public static $APP_CONFIG_TPL_PATH = "./templates/app.yml.tpl";
    public static $APP_ENV_DIR_PATH = "./config/env_files/";
    public static $APP_CRON_DIR_PATH = "./config/crontab/";
    public static $APP_LOCAL_FILE_PATH = "./_local.compose";
    public static $APP_VOLUMES_PATH = "./volumes";

    /**
     *
     */
    private function _getConfiguration()
    {
        return Yaml::parseFile(self::$APP_CONFIG_PATH);
    }

    /**
     * $additional_agregation:additional aggregations.
     */
    private function _compileListCompose($additional_agregation = array()) {
        $composefiles = array();

        $supported_os = array(
            // Default OS.
            // 'linux',
            'darwin',
            'window',
        );

        $agregation_order = array(
            '_base',
            '_main',

            // This is optional and not tracked in version control.
            '_local',

            // This is available by default but only when needed.
            // '_cmds',
        );

        $agregation_order = array_merge($agregation_order, $additional_agregation);
        $glob_order = implode('|', $agregation_order);

        $composefiles = array();

        $config = $this->_getConfiguration();
        $modes = $config['modes'];

        if (!empty($modes)) {
            $glob_modes = implode('|', $modes);
        } else {
            $glob_modes = $modes;
        }

        $glob_os = strtolower(PHP_OS);

        $composefiles = glob("*.compose");
        $composefiles = preg_grep("/({$glob_order})(\.\w+)?\.?({$glob_modes})?\.?({$glob_os})?.compose/", $composefiles);

        usort($composefiles, function($a, $b) use ($agregation_order) {
            $a = str_replace('.compose', '', $a);
            $b = str_replace('.compose', '', $b);

            $a = explode('.', $a);
            $b = explode('.', $b);

            $a_root = $a[0];
            $b_root = $b[0];

            if (strpos($a_root, $b_root) === 0) {
                // a and b have the same roots.
                if (count($a) == count($b)) {
                    // a and b have the same override level. One of them must
                    // be OS specific.
                    return end($a) == PHP_OS ? 1 : -1;
                }
                // a and b are on a differrent override level. One of them must
                return count($a) > count($b) ? 1 : -1;
            }
            elseif (in_array($a_root, $agregation_order) === in_array($b_root, $agregation_order)) {
                return array_search($a_root, $agregation_order) -  array_search($b_root, $agregation_order);
            }
            else {
                return array_search($a_root, $agregation_order) === FALSE ? 1 : -1;
            }

        });

        $dockerCompose = array_map(
            function ($file) {
                return "-f " . $file;
            },
            $composefiles
        );

        array_unshift($dockerCompose, 'docker-compose');
        return $dockerCompose;
    }

    /**
     * Prepare the host machine.
     */
    public function provision()
    {
        $this->io()->section("provision host");

        // Generate env files.
        $this->io()->section("Provision volumes folder");

        $config = $this->_getConfiguration();

        $this->_mkdir('volumes');
        $configVolumes = $config['volumes'];

        // Make sure the volume local directories are available.
        foreach ($configVolumes as $volume_name => $volume_config) {
            $volume_path = self::$APP_VOLUMES_PATH . "/" . $volume_name;

            $stack = $this->taskFilesystemStack()
                          ->mkdir($volume_path);

            if (isset($volume_config['user'])) {
                $stack->chown($volume_path, $volume_config['user']);
            }

            if (isset($volume_config['group'])) {
                $stack->chgrp($volume_path, $volume_config['group']);
            }

            $stack->run();
        }
    }

    /**
     * Setup setps for Dev mode.
     */
    public function provisionDev()
    {
        // Add bindfs driver install.
        $this->io()->section("Make sure the lebokus/bindfs docker plugin is installed.");
        $this->_exec("docker plugin install lebokus/bindfs");

        $this->io()->section("Update /etc/hosts to make sure the 'dkan.docker' entry is included.");
        $this->_exec("sudo sed -i '/dkan\.docker/d' /etc/hosts");
        $this->_exec('echo "127.0.0.1 dkan.docker" | sudo tee -a /etc/hosts');
    }


    /**
     * Setup setps for Prod mode.
     */
    public function provisionProd()
    {
        // Setup vm.max_map_count on the host system.
        // sudo sysctl -w vm.max_map_count=262144
        $this->_exec("sudo sysctl -w vm.max_map_count=262144");
    }

    /**
     *
     */
    public function configInit() {
        $this->_copy(self::$APP_CONFIG_TPL_PATH, self::$APP_CONFIG_PATH);
    }


    /**
     *
     */
    public function configGenerate() {
        $config = $this->_getConfiguration();

        // Make sure the env directory is available.
        $this->_mkdir(self::$APP_ENV_DIR_PATH);

        // Generate env files.
        $this->io()->section("Generate env files.");
        foreach ($config['env_files'] as $env_file => $env_content) {
            file_put_contents(self::$APP_ENV_DIR_PATH . "/" . $env_file . ".env", implode(PHP_EOL, $env_content));
        }

        // Generate crontab file.
        $this->io()->section("Generate crontab file.");

        // Generate _local.cpmopose file
        $this->io()->section("Generate _local.compose file.");
        $localYaml = $config['_local'];
        $localYaml = array_merge(array("version" => "3.4"), $localYaml);
        $localYaml = Yaml::dump($localYaml, 10, 2);
        file_put_contents(self::$APP_LOCAL_FILE_PATH, $localYaml);
    }

    /**
     *
     */
    public function appStatus() {
        $this->servicePs(array());
    }

    /**
     *
     */
    public function appBuild() {
        $config = $this->_getConfiguration();
        $build_cmds = $config['build'];

        foreach ($build_cmds as $cmd) {
            $this->cmd(array("run", "--rm", $cmd));
        }
    }

    /**
     *
     */
    public function appStart() {
        $this->service(array('up', '-d', '--build'));
    }

    /**
     *
     */
    public function appSetup() {

        $config = $this->_getConfiguration();
        $setup_cmds = $config['setup'];

        foreach ($setup_cmds as $cmd) {
            $this->cmd(array("run", "--rm", $cmd));
        }
    }

    /**
     * Stop docker services.
     */
    public function appStop()
    {
        $this->service(array("stop"));
    }

    /**
     * Destroy all the services and volumes.
     */
    public function appDestroy()
    {
        $this->serviceDown(array('--remove-orphans', '-v'));
    }

    // Docker Operations

    /**
     * Run any docker-compose commands.
     */
    public function service(array $args)
    {
        $dockerCompose = $this->_compileListCompose();

        $this->taskExec(implode(' ', $dockerCompose))
             ->rawArg(implode(' ', $args))
             ->run();
    }

    /**
     *
     */
    public function serviceUp(array $args)
    {
        $this->service(array('up', '-d', implode(' ', $args)));
    }

    /**
     *
     */
    public function servicePs(array $args)
    {
        $this->service(array("ps", implode(' ', $args)));
    }

    /**
     *
     */
    public function serviceRestart(array $args)
    {
        $this->service(array("restart", implode(' ', $args)));
    }

    /**
     *
     */
    public function serviceStop(array $args)
    {
        $this->service(array("stop", implode(' ', $args)));
    }

    /**
     *
     */
    public function serviceLogs(array $args)
    {
        $this->service(array('logs', '-f', implode(' ', $args)));
    }

    /**
     *
     */
    public function serviceDown(array $args)
    {
        $this->service(array('down', implode(' ', $args)));
    }

    /**
     *
     */
    public function volumeLs()
    {
        $this->_exec("docker volume ls -f name=\"opendatastack\"");
    }

    /**
     *
     */
    public function volumeRemove(array $args)
    {
        $this->_exec("docker volume rm -f name=\"opendatastack\"");
    }

    // PHPUnit Tests

    /**
     *
     */
    public function test()
    {
      $this->taskPHPUnit()
        ->dir("volumes/elasticsearch-import-api-client")
        ->files('./src/tests/*')
          ->group('Integration')
        ->run();
    }

    /**
     * Generic method to run cmd type docker compose services.
     */
    public function cmd(array $args) {
        // make sure to load the cmd docker compose file.
        $dockerCompose = $this->_compileListCompose(array('_cmds'));

        $this->taskExec(implode(' ', $dockerCompose))
             ->rawArg(implode(' ', $args))
             ->run();
    }

    /**
     * Upload database snapshot to S3 from the local backup volume.
     */
    public function cmdDkanAssetDbsnapshotUpload() {
        $this->cmd(array("run", "dkan-asset-dbsnapshot-upload"));
    }

    /**
     * Backup database to the backup volume.
     */
    public function dkanAssetsDbsnapshotBackup()
    {
        // make sure to load the assets compose file.
        $dockerCompose = $this->_compileListCompose();

        // Make sure the backups folder exists.
        $this->_mkdir("backups");

        $this->taskExecStack()
             ->exec(implode(' ', $dockerCompose) . ' exec -T dkan-mariadb /bin/bash -c "/usr/bin/mysqldump -uroot --password=\$MYSQL_ROOT_PASSWORD \$MYSQL_DATABASE" | gzip -c > ./backups/dkan-`date --iso-8601="seconds"`.sql.gz')
             ->run();
    }

    /**
     * Import dkan database from local snapshot.
     */
    public function dkanAssetsDbImport()
    {
        $this->cmd(array("run", "dkan-asset-dbsnapshot-import"));
    }

    /**
     * Download files archive from S3.
     */
    public function dkanAssetsFilesDownload()
    {
        $this->cmd(array("run", "dkan-asset-files-download"));
    }

    /**
     * Save an archive from current Dkan files.
     */
    public function dkanAssetsFilesArchive()
    {
        $this->cmd(array("run", "dkan-asset-files-archive"));
    }

    /**
     * Extrat an archive from current Dkan files.
     */
    public function dkanAssetsFilesUnpack()
    {
        $this->cmd(array("run", "dkan-asset-files-unpack"));
    }

    /**
     * Extrat an archive from current Dkan files.
     */
    public function dkanBuild()
    {
        $this->cmd(array("run", "dkan-build-custom"));
        $this->cmd(array("run", "dkan-build-custom-libs"));
        $this->cmd(array("run", "dkan-build-overrides"));
        $this->cmd(array("run", "dkan-build-config-htaccess"));
        $this->cmd(array("run", "dkan-build-config-circleci"));
        $this->cmd(array("run", "dkan-build-config-config"));
        $this->cmd(array("run", "dkan-build-config-transpose"));
        $this->cmd(array("run", "dkan-build-post-build"));
        $this->cmd(array("run", "dkan-build-post-build-custom"));
    }
}
