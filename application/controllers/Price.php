<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Price extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('price_coin_model');
        }
        
        public function update()
        {
            $coins_arr = $this->price_coin_model->active_coins();
            $coin_names = array();
            foreach($coins_arr as $coin){
                $coin_names[$coin['id']]    =   $coin['name'];
            }
            foreach($coin_names as $id => $name){
                $res = commit_curl("https://www.okcoin.com/api/v1/ticker.do?symbol=".$name."_usd");
                $res_arr = json_decode($res,true);
                if(isset($res_arr['ticker']['last'])){
                    $price = $res_arr['ticker']['last'];
                    $this->price_coin_model->update_price($price,$id);
                } else {
                    echo $name. " has no price \r\n";
                }
            }

            echo "update all the coins' price at :".date('Y-m-d H:i:s')."\r\n";
            
        }
}


