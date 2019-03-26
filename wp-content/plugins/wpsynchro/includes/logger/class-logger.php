<?php
namespace WPSynchro\Logger;

/**
 * Class Logger - Used to log synchronization runs
 */
class FileLogger
{

    public $filename = "";
    public $filename_prefix = "";
    public $filepath = "";
    public $dateformat = 'Y-m-d H:i:s.u';
    public $log_levels = array(
        "EMERGENCY" => 0,
        "ALERT" => 1,
        "CRITICAL" => 2,
        "ERROR" => 3,
        "WARNING" => 4,
        "NOTICE" => 5,
        "INFO" => 6,
        "DEBUG" => 7
    );
    public $log_level_threshold = "DEBUG";
    public $write_queue = array();

    public function __construct()
    {
        
    }

    public function __destruct()
    {
        $this->flushlog();
    }

    public function setFilePath($path)
    {

        if ($path == $this->filepath) {
            return;
        }
        $this->flushlog();

        $this->filepath = trailingslashit($path);
        $this->createTempPath();
    }

    public function setFileName($filename)
    {

        if ($filename != $this->filename) {
            $this->flushlog();
            $this->filename = $filename;
        }
    }

    private function createTempPath()
    {
        if (!file_exists($this->filepath)) {
            mkdir($this->filepath, 0750, true);
        }
        $htaccess_file = trailingslashit($this->filepath) . ".htaccess";
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "order deny,allow" . PHP_EOL . "deny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        $indexphp_file = trailingslashit($this->filepath) . "index.php";
        if (!file_exists($indexphp_file)) {
            $indexphp_content = "<?php " . PHP_EOL . "// silence is golden";
            file_put_contents($indexphp_file, $indexphp_content);
        }
    }

    public function log($level, $message, $context = "")
    {
        if ($this->log_levels[$this->log_level_threshold] < $this->log_levels[$level]) {
            return;
        }

        // Format log msg
        $formatted_msg = "[{$this->getTimestamp()}] [{$level}] {$message}" . PHP_EOL;

        // If context, print that on newline
        if (is_array($context) || is_object($context)) {
            $formatted_msg .= PHP_EOL . print_r($context, true) . PHP_EOL;
        }

        $this->writelog($formatted_msg);
    }

    private function writelog($message)
    {
        $this->write_queue[] = $message;

        // If more than X messages, then write to file
        if (count($this->write_queue) > 20) {
            $this->flushlog();
        }
    }

    private function flushlog()
    {
        if (count($this->write_queue) > 0 && strlen($this->filepath) > 0 && strlen($this->filename) > 0) {
            file_put_contents($this->filepath . $this->filename_prefix . $this->filename, $this->write_queue, FILE_APPEND);
            $this->write_queue = array();
        }
    }

    private function getTimestamp()
    {
        $date = new \DateTime();
        return $date->format($this->dateformat);
    }
}
