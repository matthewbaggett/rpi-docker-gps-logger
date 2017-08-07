#!/usr/bin/php
<?php
require_once("bootstrap.php");

class record{
    /** @var record_mode */
    public $mode;
    /** @var DateTime  */
    public $time;
    /** @var record_coord  */
    public $coord;
    /** @var record_movement  */
    public $movement;
    /** @var record_error  */
    public $error;

    public function __construct()
    {
        $this->mode = new record_mode();
        $this->time = new DateTime();
        $this->coord = new record_coord();
        $this->movement = new record_movement();
        $this->error = new record_error();
    }

    static public function Factory(){
        return new record();
    }

    /**
     * @return record_mode
     */
    public function getMode(): record_mode
    {
        return $this->mode;
    }

    /**
     * @return DateTime
     */
    public function getTime(): DateTime
    {
        return $this->time;
    }

    public function setTimeFromTimestamp(string $timestamp): self
    {
        if($timestamp != null) {
            $this->time->setTimestamp(strtotime($timestamp));
        }
        return $this;
    }

    /**
     * @return record_coord
     */
    public function getCoord(): record_coord
    {
        return $this->coord;
    }

    /**
     * @return record_movement
     */
    public function getMovement(): record_movement
    {
        return $this->movement;
    }

    /**
     * @return record_error
     */
    public function getError(): record_error
    {
        return $this->error;
    }

    public function __toArray(){
        $array = $this->getMode()->__toArray();
        $array = array_merge($array, ['time_u' => $this->getTime()->getTimestamp(), 'time' => $this->getTime()->format('Y-m-d H:i:s')]);
        $array = array_merge($array, $this->getCoord()->__toArray());
        $array = array_merge($array, $this->getMovement()->__toArray());
        $array = array_merge($array, $this->getError()->__toArray());
        ksort($array);
        return $array;
    }
}

class record_mode{
    static $modes = [
        1 => 'No Fix',
        2 => '2D',
        3 => '3D',
    ];

    public $mode;

    public function setByEnum($i){
        $this->mode = $i;
    }

    public function __toArray(){
        return [
            'mode' => self::$modes[$this->mode],
        ];
    }
}

class record_coord{
    public $longitude;
    public $latitude;
    public $altitude;

    public function __toArray(){
        return [
            'coord.longitude' => $this->longitude,
            'coord.latitude' => $this->latitude,
            'coord.altitude' => $this->altitude,
        ];
    }
}

class record_movement{
    public $direction;
    public $speed;
    public $climb;

    public function __toArray(){
        return [
            'coord.direction' => $this->direction,
            'coord.speed' => $this->speed,
            'coord.climb' => $this->climb,
            'state.moving' => $this->isMoving() ? 'Yes' : 'No',
        ];
    }

    public function isMoving(){
        return ($this->speed > 0.05 || $this->climb > 0.5);
    }
}

class record_error{
    public $longitude;
    public $latitude;
    public $altitude;
    public $direction;
    public $speed;
    public $climb;

    public function __toArray(){
        return [
            'error.longitude' => $this->longitude,
            'error.latitude' => $this->latitude,
            'error.altitude' => $this->altitude,
            'error.direction' => $this->direction,
            'error.speed' => $this->speed,
            'error.climb' => $this->climb,
        ];
    }
}

class gpsLogger{

    /** @var \Predis\Client  */
    private $redisClient;
    /** @var \Nykopol\GpsdClient\Client  */
    private $gpsClient;
    /** @var int */
    private $sleepInterval = 5;
    /** @var record|null */
    private $previousRecord = null;


    public function __construct(Predis\Client $redis, \Nykopol\GpsdClient\Client $gps)
    {
        $this->gpsClient = $gps;
        $this->redisClient = $redis;
    }

    public function setUp()
    {
        $this->gpsClient->connect();
        $this->gpsClient->watch();
    }

    public function run()
    {
        while(true) {
            $tpv = $this->gpsClient->getNext('TPV');
            $tpv = json_decode($tpv);

            $record = record::Factory();
            $record
                ->getMode()->setByEnum($tpv->mode);

            if(isset($tpv->time)) {
                $record
                    ->setTimeFromTimestamp($tpv->time);
            }

            $record->getCoord()->longitude = isset($tpv->lon) ? $tpv->lon : null;
            $record->getCoord()->latitude = isset($tpv->lat) ? $tpv->lat : null;
            $record->getCoord()->altitude = isset($tpv->alt) ? $tpv->alt : null;

            $record->getMovement()->speed = isset($tpv->speed) ? $tpv->speed : null;
            $record->getMovement()->climb = isset($tpv->climb) ? $tpv->climb : null;
            $record->getMovement()->direction = isset($tpv->track) ? $tpv->track : null;

            $record->getError()->longitude = isset($tpv->epx) ? $tpv->epx : null;
            $record->getError()->latitude = isset($tpv->epy) ? $tpv->epy : null;
            $record->getError()->altitude = isset($tpv->epv) ? $tpv->epv : null;
            $record->getError()->direction = isset($tpv->epd) ? $tpv->epd : null;
            $record->getError()->speed = isset($tpv->eps) ? $tpv->eps : null;
            $record->getError()->climb = isset($tpv->epc) ? $tpv->epc : null;

            $this->redisClient->hset('location', $record->getTime()->getTimestamp(), $record->__toArray());
            $this->redisClient->publish('location', json_encode($record->__toArray()));
            $this->calculateSleepInterval($record);
            sleep($this->sleepInterval);

            $this->handleEvents($record);

            $this->previousRecord = $record;
        }
    }

    private function handleEvents(record $record){
        if($this->previousRecord instanceof record){
            if(!$this->previousRecord->getMovement()->isMoving() && $record->getMovement()->isMoving()){
                echo "Movement Started\n";
                $this->redisClient->publish('location_events', 'movement_start');
            }
            if($this->previousRecord->getMovement()->isMoving() && !$record->getMovement()->isMoving()){
                echo "Movement Stopped\n";
                $this->redisClient->publish('location_events', 'movement_stop');
            }
        }
    }

    private function calculateSleepInterval(record $record)
    {
        if($record->getMovement()->isMoving()){
            return $this->changeSleepInterval(0);
        }else{
            return $this->changeSleepInterval(10);
        }
    }

    private function changeSleepInterval(int $newInterval)
    {
        if($newInterval != $this->sleepInterval){
            echo "Changing sleep interval to {$newInterval}s\n";
            $this->sleepInterval = $newInterval;
        }
        return $this;
    }
}

$gL = new gpsLogger($redisClient, $gpsClient);
$gL->setUp();
$gL->run();
