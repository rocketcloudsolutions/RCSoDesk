<?php
/**
 * @author Dustin Moorman <rocketcloudsolutions@gmail.com>
 * @date Jan 20, 2014
 * @desc oDesk API
 */

namespace RCSoDesk\API;

class oDesk {

    const ODESK_API_KEY     = '';
    const ODESK_API_SECRET  = '';
    const ODESK_USER        = '';
    const ODESK_PASS        = '';

    protected $library;

    public function call($url, $params = array()){
        $this->library = new \RCSoDesk\Library\API(self::ODESK_API_SECRET, self::ODESK_API_KEY);
        $this->library->auth(self::ODESK_USER, self::ODESK_PASS);
        $response = $this->library->get_request($url, $params);
        return json_decode($response);
    }

}