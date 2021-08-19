<?php

class Ette_model extends CI_Model {
    
    protected $coin_id = 0;
    protected $cold_wallet = '';
    public function __construct()
    {
        $this->load->database();
        $this->psql = $this->load->database('ette',true);
    }
    
    
    public function get_test(){
        $this->psql->select('*');
        $this->psql->from('blocks');
        $this->psql->limit(5);
        $query = $this->psql->get();
        return $query->result_array();
    }

    public function get_wrong_txs(){
        $this->psql->select('hash');
        $this->psql->from('transactions');
        $this->psql->where('from','0x0000000000000000000000000000000000000000');
        $this->psql->limit(50);
        $query = $this->psql->get();
        return $query->result_array();
    }

    public function get_wrong_bn_txs(){
        $this->psql->select('hash');
        $this->psql->from('transactions');
        $this->psql->where('timestamp',0);
        $this->psql->limit(250);
        $query = $this->psql->get();
        return $query->result_array();
    }

    public function update_tx_by_hash($data,$where){
        $this->psql->update('transactions',$data,$where);
    }

    public function next_check_block() {
        $this->psql->select('value');
        $this->psql->where('name','next_check_block');
        $res = $this->psql->get('config_vars')->row();
        return $res->value;
    }

    public function has_block($num) {
        $this->psql->select('hash');
        $this->psql->from('blocks');
        $this->psql->where('number', $num);
        $query = $this->psql->get();
        $count = $query->num_rows();
        if($count > 0) {
            return $query->row()->hash;
        } else {
            return 0;
        }
    }

    public function has_tx($hash) {
        $this->psql->select('hash');
        $this->psql->from('transactions');
        $this->psql->where('hash', $hash);
        $query = $this->psql->get();
        $count = $query->num_rows();
        return $count;
    }

    public function insert_block($data) {
        $this->psql->insert('blocks', $data);
    }

    public function insert_transactions($data) {
        $this->psql->insert('transactions', $data);
    }

    public function get_trx($max_block,$current_page,$items_per_page) {
        $start = ($current_page-1)*$items_per_page;
        $this->psql->select('hash,from,to,value,input_data,gas,gasprice,cost,nonce,state,blockhash,blockNumber,timestamp');
        $this->psql->from('transactions');
        $this->psql->where('blockNumber <=', $max_block);
        $this->psql->limit($items_per_page,$start);
        $query = $this->psql->get();
        return $query->result_array();
    }

    public function update_block($data, $where) {
        $this->psql->update('blocks',$data,$where);
    }

    public function update_config_vars($data,$where) {
        $this->psql->update('config_vars',$data,$where);
    }

    public function set_node($data,$address) {
        $this->psql->select('id');
        $this->psql->from('nodes');
        $this->psql->where('owner_address', $address);
        $query = $this->psql->get();
        $count = $query->num_rows();
        if($count > 0) {
            // just update it
            $data['last_updated'] = date('Y-m-d H:i:s');
            $this->psql->update('nodes', $data, array('owner_address'=>$address));
        } else {
            // insert it
            $this->psql->insert('nodes', $data);
        }
    }

    public function get_node_number() {
        $this->psql->select('id');
        $this->psql->from('nodes');
        $this->psql->where('last_updated >', date('Y-m-d H:i:s', time()-3600));
        $query = $this->psql->get();
        $count = $query->num_rows();
        return $count;
    }

    public function get_address_number() {
        $this->psql->select('id');
        $this->psql->from('nodes');
        $query = $this->psql->get();
        $count = $query->num_rows();
        return $count;
    }

    public function get_tx_count() {
        $this->psql->select('hash');
        $this->psql->from('transactions');
        $query = $this->psql->get();
        $count = $query->num_rows();
        return $count;
    }

    public function get_signers_count() {
        $this->psql->select('id');
        $this->psql->from('signers');
        $query = $this->psql->get();
        $count = $query->num_rows();
        return $count;
    }

}
