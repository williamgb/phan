<?php
declare(strict_types=1);
namespace Phan;

use \Phan\Config;
use \Phan\Issue;

class Log {
	protected static $instance;
	protected $output_mode  = 'text';
	protected $output_filename = '';
	protected $output_mask = -1;

    /**
     * @var string[]
     */
    protected $msgs = [];

	public function __construct() {
		$this->msgs = [];
	}

	public static function getInstance():Log {
		if(empty(self::$instance)) {
			self::$instance = new Log();
		}
		return self::$instance;
	}

	public static function setOutputMode(string $mode) {
		$log = self::getInstance();
		$log->output_mode = $mode;
	}

	public static function setFilename(string $filename) {
		$log = self::getInstance();
		$log->output_filename = $filename;
	}

	public static function getOutputMask():int {
		$log = self::getInstance();
		return $log->output_mask;
	}

	public static function setOutputMask(int $mask) {
		$log = self::getInstance();
		$log->output_mask = $mask;
	}

    /**
     * @param int $category
     * The category of error such as Issue::CATEGORY_UNDEFINED
     *
     * @param string $type
     * The error type such as Issue::UndeclaredMethod
     *
     * @param string $message
     * The error message
     *
     * @param string $file
     * The name of the file with the issue
     *
     * @param int $lineno
     * The line number where the issue occurs
     */
    public static function err(
        int $category,
        string $type,
        string $message,
        string $file,
        int $lineno
    ) {
		$log = self::getInstance();

        // Don't report anything for excluded files
        if(Phan::isExcludedAnalysisFile($file)) {
            return;
        }

		if($category & $log->output_mask) {
            $ukey = md5(implode('|', [
                $file,
                $lineno,
                $category,
                $type,
                $message
            ]));
            $log->msgs[$ukey] = [
                'file' => $file,
                'lineno' => $lineno,
                'category' => $category,
                'type' => $type,
                'message' => $message
            ];
		}
	}

	public static function errorHandler($errno, $errstr, $errfile, $errline) {
		echo "$errfile:$errline $errstr\n";
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}

	public static function display(array $summary=[]) {
		$log = self::getInstance();
		$out = '';

        $print_closure = function(string $message) {
            print $message;
        };

        $fp = null;
        if(!empty($log->output_filename)) {
            $fp = fopen($log->output_filename, "w");
            $print_closure = function($message) use ($fp) {
                fputs($fp, $message);
            };
        } else {
            if(Config::get()->progress_bar) {
                fwrite(STDERR, "\n");
            }
        }

		switch($log->output_mode) {
			case 'text':
				foreach($log->msgs as $e) {
                    $print_closure(
                        "{$e['file']}:{$e['lineno']}"
                        . " {$e['type']}"
                        . " {$e['message']}\n"
                    );
				}
				break;
            case 'codeclimate':
                foreach($log->msgs as $e) {
                    $print_closure(
                        json_encode([
                            'type' => 'issue',
                            'check_name' => $e['type'],
                            'description' => Issue::CATEGORY_NAME[$e['category']] . ' ' . $e['type'] . ' ' . $e['message'],
                            'categories' => ['Bug Risk'],
                            'location' => [
                                'path' => preg_replace('/^\/code\//', '', $e['file']),
                                'lines' => [
                                    'begin' => $e['lineno'],
                                    'end' => $e['lineno'],
                                ],
                            ],
                        ], JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE) . chr(0)
                    );
                }
                break;
		}

        $log->msgs = [];

        if ($fp) {
            fclose($fp);
        }
	}
}

set_error_handler('\\phan\\Log::errorHandler', -1);
/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
