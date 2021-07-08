<?php

class Ada_model extends CI_Model {
    
    public function __construct()
    {
        $this->load->database();
    }
    
    public function create_account($data)
    {
        $this->db->insert('hcf_cold_wallet',$data);
        $insert_id = $this->db->insert_id();
        return $insert_id;
    }
    
    public function get_active($coin_id,$data=null,$model=false)
    {
        $ready_time = $this->config->item('readyTimeStamp');
        $this->db->select('*');
        $this->db->from('hcf_cold_wallet');
        $this->db->where('coin_id',$coin_id);
        if($model == false) {
            $this->db->where('ready_time >',$ready_time);
        }
        if($data!=null&&is_array($data)){
            $this->db->where($data);
        }
        $query = $this->db->get();
        return $query->result_array();
    }
    
    public function get_coin_accounts_count($coin_id)
    {
        $this->db->select('id');
        $this->db->from('hcf_cold_wallet');
        $this->db->where('coin_id',$coin_id);
        $query = $this->db->get();
        return $query->num_rows();
    }
    
    public function get_withdraw($coin_id)
    {
        $this->db->select('*');
        $this->db->from('hcf_coin_withdraw_log');
        $this->db->where('coin_id',$coin_id);
        $this->db->where('status',200);
        $query = $this->db->get();
        return $query->result_array();
    }
    
    public function get_withdraw_confirm($coin_id)
    {
        $this->db->select('*');
        $this->db->from('hcf_coin_withdraw_log');
        $this->db->where('coin_id',$coin_id);
        $this->db->where('status',210);
        $query = $this->db->get();
        return $query->result_array();
    }
    
    public function update_withdraw($data,$id)
    {
        $this->db->update('hcf_coin_withdraw_log',$data,['id'=>intval($id)]);
    }
    
    public function update_sys_withdraw($data,$id)
    {
        $this->db->update('hcf_withdraw_sys_log',$data,['id'=>intval($id)]);
    }
    
    public function get_withdraw_sys($coin_id,$amount)
    {
        $this->db->select('*');
        $this->db->from('hcf_cold_wallet');
        $this->db->where('coin_id',$coin_id);
        $this->db->where('balance >',$amount);
        $query = $this->db->get();
        return $query->result_array();
    }
    
    public function insert_withdraw_log($data)
    {
        $this->db->insert('hcf_withdraw_sys_log',$data);
    }
    
    public function get_withdraw_sys_confirm($coin_id,$cid=null)
    {
        $this->db->select('*');
        $this->db->from('hcf_withdraw_sys_log');
        $this->db->where('coin_id',$coin_id);
        $this->db->where('status',210);
        if($cid!=null&& is_numeric($cid)){
            $this->db->where('cid',$cid);
        }
        $query = $this->db->get();
        return $query->result_array();
    }
    
    public function update_cold_wallet($data, $id)
    {
        $this->db->update('hcf_cold_wallet',$data,['id'=>intval($id)]);
    }
    
    public function get_theshold($coin_id)
    {
        $this->db->select('theshold_of_transfer');
        $this->db->where('id',$coin_id);
        $res = $this->db->get('hcf_coin')->row();
        return $res->theshold_of_transfer;
    }
    
    public function get_sum_balance($coin_id)
    {
        $this->db->select_sum('balance');
        $this->db->where('coin_id', $coin_id);
        $result = $this->db->get('hcf_cold_wallet')->row();
        return $result->balance;
    }
    
    public function get_coin_data($coin_id)
    {
        $this->db->select('next_start_sequence,account_name');
        $this->db->where('coin_id', $coin_id);
        $result = $this->db->get('hcf_coin_token')->row();
        return $result;
    }
    
    public function update_coin_token($data, $coin_id)
    {
        $this->db->update('hcf_coin_token',$data,['coin_id'=>intval($coin_id)]);
    }
    
    public function check_user_trade_duplicate($tx_hash,$input_address)
    {
        $this->db->select('id');
        $this->db->from('hcf_user_trade_amount_log');
        $this->db->where('tx_hash',$tx_hash);
        $this->db->where('input_address',$input_address);
        $query = $this->db->get();
        return $query->num_rows();
    }
}
