<?php

/**
 * @package CLI API
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2018, Iurii Makukh <gplcart.software@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GPL-3.0+
 */

namespace gplcart\modules\cli_api;

use Exception;
use gplcart\core\Config;
use gplcart\core\Container;
use RuntimeException;
use UnexpectedValueException;

/**
 * Main class for CLI API module
 */
class Main
{

    /**
     * Config class instance
     * @var \gplcart\core\Config $config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Implements hook "module.install.before"
     * @param null|string $result
     */
    public function hookModuleInstallBefore(&$result)
    {
        $exec_enabled = function_exists('exec')
            && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))
            && exec('echo EXEC') === 'EXEC';

        if (!$exec_enabled) {
            $result = gplcart_text('exec() function is disabled');
        }
    }

    /**
     * Implements hook "module.uninstall.before"
     */
    public function hookModuleUninstallAfter()
    {
        $this->config->reset('module_cli_api_phpexe_file');
    }

    /**
     * Implements hook "module.api.process"
     * @param array $params
     * @param array $user
     * @param mixed $response
     */
    public function hookModuleApiProcess(array $params, array $user, &$response)
    {
        if (!isset($response)) {

            $result = $this->exec($params, $user);
            $response = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $response = $result;
            }
        }
    }

    /**
     * Executes a command using an array of API request parameters
     * @param array $params
     * @param array $user
     * @param bool $return_string
     * @return string|array
     */
    public function exec(array $params, array $user, $return_string = true)
    {
        try {
            $php = $this->getPhpExe();
        } catch (Exception $ex) {
            return 'Error defining PHP executable: ' . $ex->getMessage();
        }

        try {
            $cmd = $this->getCommand($params, $user);
        } catch (Exception $ex) {
            return 'Error constructing CLI command: ' . $ex->getMessage();
        }

        $exe = GC_FILE_CLI;
        $output = $return = null;

        exec(escapeshellcmd("$php $exe $cmd"), $output, $return);

        if (!empty($return) && empty($output)) {
            $output = $return;
        }

        return $return_string ? trim(implode('', $output)) : $output;
    }

    /**
     * Returns a saved path to PHP executable file
     * @return string
     */
    protected function getPhpExe()
    {
        $file = (string) $this->config->get('module_cli_api_phpexe_file', '');

        if (empty($file)) {
            $file = $this->findPhpExe();
            $this->setPhpExe($file);
        }

        return $file;
    }

    /**
     * Sets the path to PHP executable file
     * @param string $file
     * @return bool
     */
    public function setPhpExe($file)
    {
        return $this->config->set('module_cli_api_phpexe_file', $file);
    }

    /**
     * Returns the path to PHP executable file
     * @return string
     * @throws RuntimeException
     */
    public function findPhpExe()
    {
        $php = getenv('PHP_BINARY');

        if (!empty($php)) {

            if (is_executable($php)) {
                return $php;
            }

            throw new RuntimeException('PHP_BINARY is not executable');
        }

        $php = getenv('PHP_PATH');

        if (!empty($php)) {

            if (is_executable($php)) {
                return $php;
            }

            throw new RuntimeException('PHP_PATH is not executable');
        }

        $php = getenv('PHP_PEAR_PHP_BIN');

        if (!empty($php) && is_executable($php)) {
            return $php;
        }

        $php = PHP_BINDIR . (DIRECTORY_SEPARATOR === '\\' ? '\\php.exe' : '/php');

        if (is_executable($php)) {
            return $php;
        }

        if (ini_get('open_basedir')) {

            $dirs = array();
            foreach (explode(PATH_SEPARATOR, ini_get('open_basedir')) as $path) {

                // Silencing against https://bugs.php.net/69240
                if (@is_dir($path)) {
                    $dirs[] = $path;
                    continue;
                }

                if (basename($path) === 'php' && @is_executable($path)) {
                    return $path;
                }
            }

        } else {

            $dirs = array(PHP_BINDIR);

            if (DIRECTORY_SEPARATOR === '\\') {
                $dirs[] = 'C:\xampp\php\\';
            }

            $dirs = array_merge(explode(PATH_SEPARATOR, getenv('PATH') ?: getenv('Path')), $dirs);
        }

        $suffixes = array('');

        if (DIRECTORY_SEPARATOR === '\\') {
            $path_ext = getenv('PATHEXT');
            if (!empty($path_ext)) {
                $suffixes = array_merge($suffixes, explode(PATH_SEPARATOR, $path_ext));
            }
        }

        foreach ($suffixes as $suffix) {
            foreach ($dirs as $dir) {
                $file = $dir . DIRECTORY_SEPARATOR . "php$suffix";
                if (@is_file($file) && (DIRECTORY_SEPARATOR === '\\' || is_executable($file))) {
                    return $file;
                }
            }
        }

        throw new RuntimeException('Cannot find PHP executable file');
    }

    /**
     * Convert an array of params into a CLI command
     * @param array $params
     * @param array $user
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @return string
     */
    public function getCommand(array $params, array $user)
    {
        $params += array(
            'get' => array(),
            'post' => array(),
            'arguments' => array()
        );

        if (count($params['arguments']) != 1) {
            throw new UnexpectedValueException('"arguments" key must contain exactly one array element');
        }

        if (empty($user['user_id'])) {
            throw new UnexpectedValueException('Second argument must contain a valid user ID under "user_id" key');
        }

        $command = array(reset($params['arguments']));

        $route = $this->getCliRouteInstance()->get($command[0]);

        if (empty($route['access'])) {
            throw new RuntimeException('Undefined user access');
        }

        foreach (array_merge($params['get'], $params['post']) as $key => $value) {
            if (is_string($value) && strlen($key) > 1) {
                $command[] = rtrim("--$key=" . escapeshellarg($value), '=');
            }
        }

        $command[] = " -u=" . escapeshellarg($user['user_id']);
        $command[] = " -f=json";

        return implode(' ', $command);
    }

    /**
     * Returns CLI router instance
     * @return \gplcart\core\CliRoute
     */
    protected function getCliRouteInstance()
    {
        /** @var \gplcart\core\CliRoute $instance */
        $instance = Container::get('gplcart\\core\\CliRoute');
        return $instance;
    }
}
