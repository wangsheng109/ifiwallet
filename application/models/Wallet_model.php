<?php

class Wallet_model extends CI_Model {

    public function __construct($param=array())
    {
            $this->load->database();
            $this->table_user_wallet = $this->db->dbprefix('user_wallet');
            $this->table_coins = $this->db->dbprefix('coins');
            $this->table_eth_cold_wallet = $this->db->dbprefix('cold_wallet_eth');
            $this->table_deposit_log = $this->db->dbprefix('coin_deposit_log');
            $this->table_withdraw_log = $this->db->dbprefix('coin_withdraw_log');
    }

    public function get_coins($where=null,$paginate=NULL,$sort=NULL)
    {
        $this->db->select('*');
        $this->db->from($this->table_coins);
        if(!empty($where)){
            $this->db->where($where);
        }
        if(null != $sort){
            $sort_key = $sort['sort_key'];
            $sort_direction = (isset($sort['sort_direction'])&&($sort['sort_direction']==0))?'desc':'asc';
            $this->db->order_by($sort_key,$sort_direction);
        }
        else {
           $this->db->order_by('sort_id','desc'); 
        }
        if(null !=$paginate ){
                $index = intval($paginate['page']);
                $row = intval($paginate['each_page_count']);
                $this->db->limit($row,($index-1)*$row);
        }
        $query = $this->db->get();
        if(!$query){
            var_dump($this->db->error());exit;
        }
        // get total records
        if(null !=$paginate ){
            $data[0] = $query->result_array();
            $this->db->select('*');
            $this->db->from($this->table_coins);
            if(!empty($where)){
                $this->db->where($where);
            }
            $query = $this->db->get();
            $data[1] = $query->num_rows();
            return $data;
        }
        else {
            return $query->result_array();
        }
    }

    public function get_user_wallet($where=null)
    {
        $this->db->select('*');
        $this->db->from($this->table_user_wallet);
        if(!empty($where)){
            $this->db->where($where);
        }
        return $this->db->get()->result_array();
    }
    
    public function fetch_user_eth_address($user_id)
    {
        // If address already exist, then return the address directly
        $this->db->select('address');
        $this->db->where('user_id',intval($user_id));
        $res = $this->db->get($this->table_eth_cold_wallet)->row();
        if(!empty($res->address)){
            return $res->address;
        }
        
        //If the address does not exist for this user, then alloc one for him
        $this->db->select('address');
        $this->db->where('user_id',NULL);
        $res = $this->db->get($this->table_eth_cold_wallet)->row();
        if(!empty($res->address)){
            $this->db->update($this->table_eth_cold_wallet,array('user_id'=>$user_id,'update_time'=>time()),array('address'=>$res->address));
            return $res->address;
        } else {
            return false;
        }
    }

    
    public function get_deposit_logs($where=null,$paginate=NULL,$sort=NULL)
    {
        $this->db->select('*');
        $this->db->from($this->table_deposit_log);
        if(!empty($where)){
            $this->db->where($where);
        }
        if(null != $sort){
            $sort_key = $sort['sort_key'];
            $sort_direction = (isset($sort['sort_direction'])&&($sort['sort_direction']==0))?'desc':'asc';
            $this->db->order_by($sort_key,$sort_direction);
        }
        else {
           $this->db->order_by('update_time','desc'); 
        }
        if(null !=$paginate ){
                $index = intval($paginate['page']);
                $row = intval($paginate['each_page_count']);
                $this->db->limit($row,($index-1)*$row);
        }
        $query = $this->db->get();
        // get total records
        if(null !=$paginate ){
            $data[0] = $query->result_array();
            $this->db->select('*');
            $this->db->from($this->table_deposit_log);
            if(!empty($where)){
                $this->db->where($where);
            }
            $query = $this->db->get();
            $data[1] = $query->num_rows();
            return $data;
        }
        else {
            return $query->result_array();
        }
    }

    public function get_withdraw_logs($where=null,$paginate=NULL,$sort=NULL)
    {
        $this->db->select('*');
        $this->db->from($this->table_withdraw_log);
        if(!empty($where)){
            $this->db->where($where);
        }
        if(null != $sort){
            $sort_key = $sort['sort_key'];
            $sort_direction = (isset($sort['sort_direction'])&&($sort['sort_direction']==0))?'desc':'asc';
            $this->db->order_by($sort_key,$sort_direction);
        }
        else {
           $this->db->order_by('update_time','desc'); 
        }
        if(null !=$paginate ){
                $index = intval($paginate['page']);
                $row = intval($paginate['each_page_count']);
                $this->db->limit($row,($index-1)*$row);
        }
        $query = $this->db->get();
        // get total records
        if(null !=$paginate ){
            $data[0] = $query->result_array();
            $this->db->select('*');
            $this->db->from($this->table_withdraw_log);
            if(!empty($where)){
                $this->db->where($where);
            }
            $query = $this->db->get();
            $data[1] = $query->num_rows();
            return $data;
        }
        else {
            return $query->result_array();
        }
    }

    public function update_deposit_logs($data,$where)
    {
        $this->db->update($this->table_deposit_log,$data,$where);
    }

    public function update_user_wallet($amount,$where,$type=1)
    {
        $this->db->select('id');
        $this->db->from($this->table_user_wallet);
        if(null != $where){
            $this->db->where($where);
        }
        $query = $this->db->get();
        $num = $query->num_rows();
        if($num>0){
            if($type==1){
                $this->db->set('amount', '`amount`+'.$amount, FALSE);
            }
            if($type==2){
                $this->db->set('amount', '`amount`-'.$amount, FALSE);
            }
            $this->db->set('update_time', time(), FALSE);
            $this->db->where($where);
            $this->db->update($this->table_user_wallet);
        } else {
            $insert_arr = array(
                'user_id'   =>  $where['user_id'],
                'coin_id'   =>  $where['coin_id'],
                'amount'    =>  $amount,
                'update_time'  =>  time(),
                'create_time'   =>  time()
            );
            $query = $this->db->insert($this->table_user_wallet,$insert_arr);
            if($query){
                $insert_id = $this->db->insert_id();
                return $insert_id;
            } else {
                return $this->db->error();
            }
        }
        
    }

    public function frozen_coin($amount,$where,$type=0)
    {
        if($type==0){
            $this->db->set('frozen', '`frozen`+'.$amount, FALSE);
            $this->db->set('update_time', time(), FALSE);
            $this->db->where($where);
            $this->db->update($this->table_user_wallet);
        } else {
            $this->db->set('frozen', '`frozen`-'.$amount, FALSE);
            $this->db->set('amount', '`amount`-'.$amount, FALSE);
            $this->db->set('update_time', time(), FALSE);
            $this->db->where($where);
            $this->db->update($this->table_user_wallet);
        }
    }

    


    public function set_coin_withdraw_log($type=0,$data=array(),$where=null)
    {
        // insert new
        if($type==0){
            $query = $this->db->insert($this->table_withdraw_log,$data);
            if($query){
                $insert_id = $this->db->insert_id();
                return $insert_id;
            } else {
                return $this->db->error();
            }
        }
        // update old
        if($type==1){
            $this->db->update($this->table_withdraw_log,$data,$where);
        }
    }

    
}
