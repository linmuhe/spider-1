<?php
/**
 * spider
 * @copyright 2014 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace ddliu\spider;
use ddliu\spider\Pipe\PipeInterface;
use ddliu\spider\Pipe\FunctionPipe;
use ddliu\spider\Pipe\CombinedPipe;
use Monolog\Logger;


class Spider {
    protected $pipes = array();
    protected $tasks = array();
    protected $counter = array();
    protected $limitCount = 0;
    protected $startTime;
    protected $stopped = false;
    public $logger;

    /**
     * Optins
     *  - limit: Maxmum tasks to run
     *  - depth: Task fork depth
     *  - timeout: Maxmum time to run
     * @var array
     */
    protected $options = array();

    public function __construct($options = array()) {
        $this->startTime = microtime(true);
        $this->pipe = new CombinedPipe();
        $this->options = $options;
        $this->logger = new Logger(isset($options['name'])?$options['name']:'din.spider');
    }

    public function setLogger($logger) {
        $this->logger = $logger;
        return $this;
    } 
    public function clearTask(){
    	$this->tasks=[];
    }
    public function addTask($data) {
        if (!$data instanceof Task) {
            $task = new Task($data);
        } else {
            $task = $data;
        }

        $task->spider = $this;
        $this->tasks[] = $task;

        return $this;
    }

    public function run() {
        $this->logger->addInfo('spider started');
        // TODO: scheduller
        while(!$this->stopped && $task = array_pop($this->tasks)) {
            $this->process($task);
        }

        return $this;
    }

    public function stop($message = null) {
        if (!$message) {
            $message = '';
        }
        $this->logger->addInfo('Spider stopped: '.$message);
        $this->stopped = true;
    }

    public function pipe($pipe) {
        $this->pipe->pipe($pipe);
        return $this;
    }

    protected function process($task) {
        // check for limit
        if (!empty($this->options['limit']) && $this->limitCount >= $this->options['limit']) {
            $this->logger->addWarning('Stopped after processing '.$this->options['limit'].' tasks');
            $this->stop();
            return;
        }

        $task->start();
        try {
            $this->pipe->run($this, $task);
        } catch (\Exception $e) {
		$task->fail($e);
        }
        if ($task->getStatus() === Task::STATUS_WORKING) {
            $task->done();
        }

        $status = $task->getStatus();

        // limit counter
        if ($status !== Task::STATUS_IGNORED) {
            $this->limitCount++;
        }

        if (!isset($this->counter[$status])) {
            $this->counter[$status] = 1;
        } else {
            $this->counter[$status]++;
        }
    }

    public function getTaskCount(){
    	return count($this->tasks);
    }
    public function report() {
        $counter = $this->counter;
        $counter[Task::STATUS_PENDING] = isset($counter[Task::STATUS_PENDING])?$counter[Task::STATUS_PENDING]:0 + count($this->tasks);
        static $names = [
            Task::STATUS_PENDING => 'Pending',
            Task::STATUS_WORKING => 'Working',
            Task::STATUS_PAUSE => 'Paused',
            Task::STATUS_DONE => 'Done',
            Task::STATUS_RETRY => 'Retry',
            Task::STATUS_FAILED => 'Failed',
            Task::STATUS_IGNORED => 'Ignored',
        ];

        $message = '';
        $mem = memory_get_usage(true) / (1024 * 1024);
        $memPeak = memory_get_peak_usage(true) / (1024 * 1024);
        $message .= sprintf("SPIDER REPORT - Time: %.2fs; Mem: %.2fM; Peak: %.2fM", microtime(true) - $this->startTime, $mem, $memPeak);
        foreach ($counter as $status => $count) {
            if ($count) {
                $message .= '; '.$names[$status].': '.$count;
            }
        }

        $this->logger->addInfo($message);

        return $this;
    }
}
