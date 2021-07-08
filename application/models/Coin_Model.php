<?php

class Coin_Model extends CI_Model {
    
    protected $coin_id = 0;
    protected $cold_wallet = '';
    public function __construct()
    {
        $this->load->database();
    }
    
    public function get_coins($where){
        $this->db->select('*');
        $this->db->from($this->db->dbprefix('coins'));
        $this->db->where($where);
        $query = $this->db->get();
        return $query->result_array();
    }
    public function get_chain_id($coin_id)
    {
        $this->db->select('chain_id');
        $this->db->where('id',intval($coin_id));
        $res = $this->db->get($this->db->dbprefix('coins'))->row();
        return $res->chain_id;
    }
    
    public function create_account($data)
    {
        $this->db->insert($this->cold_wallet,$data);
        $insert_id = $this->db->insert_id();
        return $insert_id;
    }
    
    public function get_cold_wallet($data=null,$model=false)
    {
    //    $ready_time = $this->config->item('readyTimeStamp');
        $this->db->select('*');
        $this->db->from($this->cold_wallet);
        if($model == false) {
        //    $this->db->where('ready_time >',$ready_time);
            $this->db->where('user_id >',0);
        }
        if($data!=null&&is_array($data)){
            $this->db->where($data);
        }
        $query = $this->db->get();
        return $query->result_array();
    }
    public function delete_cold_wallet()
    {   $this->db->where('id >', 0);
        $output = $this->db->delete($this->cold_wallet);
        return $output;
    }
    public function insert_db($db_name, $data)
    {
        $this->db->insert($this->db->dbprefix($db_name),$data);
    }
    
    public function update_db($db_name, $data,$where)
    {
       
        $this->db->update($this->db->dbprefix($db_name),$data,$where);
    }
    public function get_full_deposit($txHash)
    {
        $this->db->select('*');
        $this->db->from($this->db->dbprefix("coin_deposit_log"));
        $this->db->where('tx_hash',$txHash);
        $query = $this->db->get();
        return $query->result_array();
    }
    public function get_deposit_log($data=null,$model=false)
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
    public function update_deposit_log($data,$id)
    {
        $this->db->update($this->db->dbprefix("coin_deposit_log"),$data,['id'=>intval($id)]);
    }
    public function update_withdraw_log($data,$id)
    {
        $this->db->update($this->db->dbprefix("coin_withdraw_log"),$data,['id'=>intval($id)]);
    }
    public function update_user_wallet($data)
    {
        $sql = 'INSERT INTO '.$this->db->dbprefix("user_wallet").' (user_id,coin_id,amount,update_time,create_time)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            amount=amount+?, 
            update_time=?';

        $query = $this->db->query($sql, array( $data['user_id'], 
                                               $data['coin_id'], 
                                               floatval($data['amount']), 
                                               time(),
                                               time(),
                                               floatval($data['amount']),
                                               time()
                                              ));
        return $query;
    }
    public function get_coin_accounts_count()
    {
        $this->db->select('id');
        $this->db->from($this->cold_wallet);
        $this->db->where('user_id', NULL);
        $query = $this->db->get();
        return $query->num_rows();
    }
    
    public function get_withdraw_log($status,$token_id=0)
    {
        $this->db->select('*');
        $this->db->from($this->db->dbprefix('coin_withdraw_log'));
        $this->db->where('coin_id',$this->coin_id);
        if(intval($token_id)>0) {
            $this->db->where('token_id',intval($token_id));
        }
        $this->db->where('status',$status);
        $query = $this->db->get();
        return $query->result_array();
    }
    
    public function update_cold_wallet($data, $id)
    {
        $this->db->update($this->cold_wallet,$data,['id'=>intval($id)]);
    }
    
    public function get_threshold()
    {
        $this->db->select('theshold_of_transfer');
        $this->db->where('id',$this->coin_id);
        $res = $this->db->get($this->db->dbprefix('coins'))->row();
        return $res->theshold_of_transfer;
    }
    
    public function get_sum_balance()
    {
        $this->db->select_sum('balance');
        $result = $this->db->get($this->cold_wallet)->row();
        return $result->balance;
    }
    
    public function update_chain_data($data,$chain_id)
    {
        $this->db->update($this->db->dbprefix('chain'),$data,['id'=>$chain_id]);
    }
    
    public function check_deposit_log_duplicate($tx_hash,$input_address)
    {
        $this->db->select('id');
        $this->db->from($this->db->dbprefix('coin_deposit_log'));
        $this->db->where('tx_hash',$tx_hash);
        $this->db->where('address',$input_address);
        $query = $this->db->get();
        return $query->num_rows();
    }
}
