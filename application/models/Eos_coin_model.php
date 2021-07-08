<?php

class Eos_coin_model extends Coin_Model {
    
    
    public function __construct()
    {
        parent::__construct();
        $this->coin_id=$this->config->item('eos_coin_id');
        $this->cold_wallet = "fm_cold_wallet_eos";
        
    }
}
