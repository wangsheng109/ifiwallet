<?php

class Ifi_coin_model extends Coin_Model {
    
    
    public function __construct()
    {
        parent::__construct();
        $this->coin_id=$this->config->item('ifi_coin_id');
        $this->cold_wallet = $this->db->dbprefix('cold_wallet_ifi');
        
    }

}

