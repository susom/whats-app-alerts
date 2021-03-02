<?php
namespace Stanford\ClassNameHere;
/** @var ClassNameHere $this */

trait emLoggerTrait
{
    private $emLoggerEnabled = null;    // Cache logger enabled
    private $emLoggerDebug   = null;    // Cache debug mode

    /**
     * Obtain an instance of emLogger or false if not installed / active
     * @return bool|mixed
     */
    function emLoggerInstance() {

        // This is the first time used, see if it is enabled on server
        if (is_null($this->emLoggerEnabled)) {
            $versions = \ExternalModules\ExternalModules::getEnabledModules();
            $this->emLoggerEnabled = isset($versions['em_logger']);
        }

        // Return instance if enabled
        if ($this->emLoggerEnabled) {
            // Try to return the instance of emLogger (which is cached by the EM framework)
            try {
                return \ExternalModules\ExternalModules::getModuleInstance('em_logger');
            } catch (\Exception $e) {
                // Unable to initialize em_logger
                error_log("Exception caught - unable to initialize emLogger in " . __NAMESPACE__ . "/" . __FILE__ . "!");
                $this->emLoggerEnabled = false;
            }
        }
        return false;
    }


    /**
     * Determine if we are in debug mode either on system or project level and cache
     * @return bool
     */
    function emLoggerDebugMode() {
        // Set if debug mode once on the first log call
        if (is_null($this->emLoggerDebug)) {
            $systemDebug         = $this->getSystemSetting('enable-system-debug-logging');
            $projectDebug        = !empty($_GET['pid']) && $this->getProjectSetting('enable-project-debug-logging');
            $this->emLoggerDebug = $systemDebug || $projectDebug;
        }
        return $this->emLoggerDebug;
    }


    /**
     * Do the logging
     * The reason we broke it into three functions was to reduce complexity with backtrace and the calling function
     */
    function emLog() {
        if ($emLogger = $this->emLoggerInstance()) $emLogger->emLog($this->PREFIX, func_get_args(), "INFO");
    }


    /**
     * Wrapper for logging an error
     */
    function emError() {
        if ($emLogger = $this->emLoggerInstance()) $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }


    /**
     * Wrapper for logging debug statements
     */
    function emDebug() {
        if ( $this->emLoggerDebugMode() && ($emLogger = $this->emLoggerInstance()) ) $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
    }

}