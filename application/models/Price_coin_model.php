<?php

class Price_coin_model extends Coin_Model {
    
    
    public function __construct()
    {
        parent::__construct();
        
        $this->coins_table = $this->db->dbprefix('coins');
        
    }

    
    public function update_price($price, $id)
    {
        $this->db->update($this->coins_table,['price'=>$price],['id'=>intval($id)]);
    }
    
    public function active_coins()
    {
        $this->db->select('*');
        $this->db->from($this->coins_table);
        $this->db->where(['is_active'=>1]);
        $query = $this->db->get();
        return $query->result_array();
    }
}

