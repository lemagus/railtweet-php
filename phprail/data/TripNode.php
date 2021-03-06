<?php

class TripNode {
    
    private $station;
    private $platform;
    private $vehicle;
    private $time;
    private $delay;

    function __construct(Station $station, $platform, Vehicle $vehicle = NULL, DateTime $time, $delay)
    {
        $this->station = $station;
        $this->platform = $platform;
        $this->vehicle = $vehicle;
        $this->time = $time;
        $this->delay = $delay;
    }

    public function getDelay()
    {
        return $this->delay;
    }

    public function getPlatform()
    {
        return $this->platform; 
    }

    public function getStation()
    {
        return $this->station;
    }

    public function getTime()
    {
        return $this->time; 
    }

    public function getVehicle()
    {
        return $this->vehicle;
    }



}
?>
