<?php

class Btc_coin_model extends Coin_Model {
    
    
    public function __construct()
    {
        parent::__construct();
        $this->coin_id=$this->config->item('btc_coin_id');
        $this->cold_wallet = "fw_cold_wallet_btc";
        
    }
}
