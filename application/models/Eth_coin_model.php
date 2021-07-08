<?php

class Eth_coin_model extends Coin_Model {
    
    
    public function __construct()
    {
        parent::__construct();
        $this->coin_id=$this->config->item('eth_coin_id');
        $this->cold_wallet = $this->db->dbprefix('cold_wallet_eth');
        
    }

    public function get_erc20_tokens($chain_id)
    {
        $this->db->select('*');
        $this->db->from($this->db->dbprefix('coins'));
        $this->db->where('chain_id',$chain_id);
        $this->db->where('id<>',$this->coin_id);
        $this->db->where('is_active',1);
        $query = $this->db->get();
        $temps = $query->result_array();
        $result = array();
        foreach($temps as $temp){
            $result[$temp['id']] = $temp;
        }
        return $result;
    }

    public function get_eth_info()
    {
        $this->db->select('*');
        $this->db->from($this->db->dbprefix('coins'));
        $this->db->where('id',$this->coin_id);
        $this->db->where('is_active',1);
        $query = $this->db->get();
        $result = $query->result_array();
        if(isset($result[0]['id'])){
            return $result[0];
        } else {
            var_dump($result);
            return false;
        }
    }

    public function get_erc20_deposit_log($chain_id,$data=null,$model=false)
    {
        $this->db->select('*');
        $this->db->from($this->db->dbprefix("coin_deposit_log"));
        $this->db->where('chain_id',$chain_id);
        $this->db->where('coin_id<>',$this->coin_id);
        if($model == false) {
            $this->db->where('status',100);
        }
        if($data!=null&&is_array($data)){
            $this->db->where($data);
        }
        $query = $this->db->get();
        return $query->result_array();
    }

    public function get_eth_deposit_log($chain_id,$data=null,$model=false)
    {
        $this->db->select('*');
        $this->db->from($this->db->dbprefix("coin_deposit_log"));
        $this->db->where('coin_id',$this->coin_id);
        if($model == false) {
            $this->db->where('status',100);
        }
        if($data!=null&&is_array($data)){
            $this->db->where($data);
        }
        $query = $this->db->get();
        return $query->result_array();
    }

    public function get_erc20_withdraw_log($chain_id,$data=null,$model=0)
    {
        $this->db->select('*');
        $this->db->from($this->db->dbprefix("coin_withdraw_log"));
        $this->db->where('chain_id',$chain_id);
        $this->db->where('coin_id<>',$this->coin_id);
        if($model == 0) {
            $this->db->where('status',210);
        }
        if($model == 1) {
            $this->db->where('status',250);
        }
        if($data!=null&&is_array($data)){
            $this->db->where($data);
        }
        $query = $this->db->get();
        return $query->result_array();
    }

    public function get_eth_withdraw_log($chain_id,$data=null,$model=false)
    {
        $this->db->select('*');
        $this->db->from($this->db->dbprefix("coin_withdraw_log"));
        $this->db->where('coin_id',$this->coin_id);
        if($model == 0) {
            $this->db->where('status',200);
        }
        if($model == 1) {
            $this->db->where('status',250);
        }
        if($data!=null&&is_array($data)){
            $this->db->where($data);
        }
        $query = $this->db->get();
        return $query->result_array();
    }

    public function update_coins($data, $id)
    {
        $this->db->update($this->db->dbprefix('coins'),$data,['id'=>intval($id)]);
    }
    
}

