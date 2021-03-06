<?php
/**
 * spider
 * @copyright 2014 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace ddliu\spider;

class Task implements \ArrayAccess {

    const STATUS_PENDING = 0;
    const STATUS_WORKING = 1;
    const STATUS_PAUSE = 2;
    const STATUS_DONE = 3;
    const STATUS_RETRY = -2;
    const STATUS_FAILED = -3;
    const STATUS_IGNORED = -1;

    protected $status = self::STATUS_PENDING;
    protected $data;
    public $parent;
    public $parent_title;
    public $spider;
    private $excei=0;
    protected $exces=[];
    //exception from pipe ;
    function putExce($e){
    	$this->exces[]=$e ;
    }

    function nextExce(){
        $re=false ;


        if ( isset($this->exces[$this->excei++])) {
           $re =   $this->exces[$this->excei-1];
            return $re;
       }else{
           $this->excei=0  ;
       }
        return $re ;
    }
    public function __construct($data) {
        if (is_string($data)) {
            $data = array(
                'url' => $data
            );
        } elseif (!is_array($data)) {
            throw new \Exception('Invalid task data');
        }

        $this->data = $data;
    }

    protected function getNameForDisplay() {
        return isset($this->data['url'])?$this->data['url']:'';
    }

    public function start() {
        $this->spider->logger->addDebug('Task started: '.$this->getNameForDisplay());
        $this->status = self::STATUS_WORKING;
    }

    public function done() {
        $this->spider->logger->addDebug('Task done: '.$this->getNameForDisplay());
        $this->status = self::STATUS_DONE;

        $this->end();
    }

    public function ignore($reason = null) {
        $reason = $reason?:'';

        $this->spider->logger->addDebug('Task ignored: '.$this->getNameForDisplay()."\t".$reason);
        $this->status = self::STATUS_IGNORED;

        $this->end();
    }

    public function isEnded() {
        return $this->status === self::STATUS_DONE || 
               $this->status === self::STATUS_FAILED || 
               $this->status === self::STATUS_IGNORED;
    }

    public function fail($reason = null) {
        $reason = $reason?:'';
        $this->spider->logger->addError('Task failed: '.$this->getNameForDisplay()."\t".$reason);
        $this->status = self::STATUS_FAILED;

        $this->end();
    }

    protected function end() {
        unset($this->parent, $this->spider);
    }

    public function getStatus() {
        return $this->status;
    }

    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset) {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }

    public function fork($data,$title='') {
        $task = new Task($data);
        $task->parent = $this;
        $task->parent_title=$title ;
        $this->spider->addTask($task);
    }
    public function getData(){
    	return $this->data;
    }
}
