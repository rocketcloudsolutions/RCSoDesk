<?php

namespace RCSoDesk\Implementations;

class Jobs {
    protected $api;

    public function __construct(){
        $this->api = new \RCSoDesk\API\oDesk();
    }

    /**
     * @desc API ref: http://developers.odesk.com/w/page/12364012/search%20jobs
     * @return string
     */
    public function dispenseWeb(){
        $ammo = '';

        $ammo = $this->api->call('https://www.odesk.com/api/profiles/v1/search/jobs.json', array(
            'q' => 'web',
            'min' => '1000',
            't' => 'Fixed',
            'dp' => 0
        ));


        return $ammo;
    }
}