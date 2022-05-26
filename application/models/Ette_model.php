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
    
	public function get_trans_count()
	{
	    $this->psql->select('count(hash) num');
        $this->psql->from('transactions');
        $count=($this->psql->get()->result_array())[0]["num"];
		return $count;
	}

    //public function get_trx($max_block,$current_page,$items_per_page=0) {
        public function get_trx($current_page,$items_per_page=0) {
        $start = ($current_page-1)*$items_per_page;
      //  $this->psql->select('hash,from,to,value,input_data,gas,gasprice,cost,nonce,state,blockhash,blockNumber,timestamp');
     //   $this->psql->from('transactions');
     //   $this->psql->where('blockNumber <=', $max_block);
    //    $data[0] = $this->psql->get()->num_rows();
      
     //   $this->psql->select('count(hash) num');
     //  $this->psql->from('transactions');
    //    $this->psql->where('blockNumber <=', $max_block);
    //    $data[0]=($this->psql->get()->result_array())[0]["num"];
        
		
		
	if($current_page==1)	$data[0]=$this->get_trans_count();
	else $data[0]=0;
		
		
     /* $this->psql->select('hash,from,to,value,input_data,gas,gasprice,cost,nonce,state,blockhash,blockNumber,timestamp');
        $this->psql->from('transactions');
      //  $this->psql->where('blockNumber <=', $max_block);
        $this->psql->order_by("timestamp", "desc");
        $this->psql->limit($items_per_page,$start);
        $query = $this->psql->get();
        $data[1]    =   $query->result_array();
        unset($query);*/
		$data[1]=$this->psql->query("select a.hash,a.blockNumber,a.timestamp,a.from,if(b.to is null,a.to,b.to) 'to',state from transactions a left join transactions_dam b on a.hash=b.hash order by a.TIMESTAMP desc limit ".$start.",".$items_per_page)->result_array();
        return $data;
    }

    public function get_signers($current_page=1,$items_per_page=0)
    {
        $start = ($current_page-1)*$items_per_page;
        $this->psql->select('*');
        $this->psql->from('signers');
        $data[0]    =   $this->psql->get()->num_rows();
        $this->psql->select('*');
        $this->psql->from('signers');
        if($items_per_page>0) {
            $this->psql->limit($items_per_page,$start);
        }
        $data[1] = $this->psql->get()->result_array();
        return $data;
    }
    public function update_signers($data, $where) {
        $this->psql->update('signers',$data,$where);
    }

    public function get_add_trx($current_page=1,$items_per_page=0,$address)
    {
        $start = ($current_page-1)*$items_per_page;
        $this->psql->select('hash');
        $this->psql->from('transactions');
        $this->psql->where('from',$address);
        $this->psql->or_where('to',$address);
        $data[0]    =   $this->psql->get()->num_rows();
       /* $this->psql->order_by("timestamp", "desc");
        $this->psql->select('hash,blockNumber,timestamp,from,to,state');
        $this->psql->from('transactions');
        $this->psql->where('from',$address);
        $this->psql->or_where('to',$address);
        if($items_per_page>0){
            $this->psql->limit($items_per_page,$start);
        }
        $data[1] = $this->psql->get()->result_array();*/
		$data[1]=$this->psql->query("select a.hash,a.blockNumber,a.timestamp,a.from,if(b.to is null,a.to,b.to) 'to',state from transactions a left join transactions_dam b on a.hash=b.hash where a.from='".$address."' or a.to='".$address."' order by a.TIMESTAMP desc limit ".$start.",".$items_per_page)->result_array();
        return $data;
    }


    public function get_all_blocks($current_page=1,$items_per_page=0)
    {
        $start = ($current_page-1)*$items_per_page;
        $this->psql->select('max(number) as big_block');
        $this->psql->from('blocks');
        $this->psql->order_by('time','desc');
        $data[0]    =  $this->psql->get()->row()->big_block;
        $this->psql->select('hash,number,time,difficulty,tx_num,size,miner');
        $this->psql->from('blocks');
        $this->psql->order_by('time','desc');
        if($items_per_page>0){
            $this->psql->limit($items_per_page,$start);
        }
        $data[1]    = $this->psql->get()->result_array();
        return $data;
    }

    public function get_signed_blocks($current_page=1,$items_per_page=0,$address)
    {
        $start = ($current_page-1)*$items_per_page;
        $this->psql->select('hash,number,time,difficulty,tx_num,size,miner');
        $this->psql->from('blocks');
        $this->psql->where('miner',$address);
        $data[0]    =   $this->psql->get()->num_rows();
        $this->psql->order_by('time','desc');
        if($items_per_page>0){
            $this->psql->limit($items_per_page,$start);
        }
        $this->psql->select('hash,number,time,difficulty,tx_num,size,miner');
        $this->psql->from('blocks');
        $this->psql->where('miner',$address);
        $data[1] = $this->psql->get()->result_array();
        return $data;
    }
    // check if address is signer
    public function check_s($address)
    {
        $this->psql->select('id');
        $this->psql->from('signers');
        $this->psql->where('address',$address);
        $count = $this->psql->get()->num_rows();
        if($count > 0){
            return true;
        } else {
            return false;
        }
    }

    // check if address is common node address
    public function check_n($address)
    {
        $this->psql->select('id');
        $this->psql->from('nodes');
        $this->psql->where('owner_address',$address);
        $this->psql->or_where('chequebook_address',$address);
        $count = $this->psql->get()->num_rows();
        if($count > 0){
            return true;
        } else {
            return false;
        }
    }
    public function get_nodes($current_page=1,$items_per_page=0)
    {
        $start = ($current_page-1)*$items_per_page;
        $this->psql->select('*');
        $this->psql->from('nodes');
        $data[0]    =   $this->psql->get()->num_rows();
        $this->psql->select('*');
        $this->psql->from('nodes');
        if($items_per_page>0){
            $this->psql->limit($items_per_page,$start);
        }
        $data[1] = $this->psql->get()->result_array();
        return $data;
    }

public function insert_award_log($data) {
        $this->psql->insert('ifi_award_log',$data);
        return $this->psql->insert_id();
    }

    public function get_signer_m_block($signer) {
        $this->psql->select('MAX(number) as max_number, MIN(number) as min_number');
        $this->psql->from('blocks');
        $this->psql->where('miner',$signer);
        $query = $this->psql->get();
        return $query->row();
    }

    public function get_blocks_count_by_singer_time($start_time,$signer) {
        $this->psql->select('number');
        $this->psql->from('blocks');
        $this->psql->where(['miner'=>$signer,'time >=' => $start_time]);
        $query = $this->psql->get();
        $count = $query->num_rows();
        return $count;
    }
    
    public function get_award_by_node_time($start_time,$node) {
        $this->psql->select('SUM(ifi_amount) as sum_amount');
        $this->psql->where(['node_address'=>$node,'timestamp >=' => $start_time]);
        $res = $this->psql->get('ifi_award_log')->row();
        return $res->sum_amount;
    }
    
    public function get_incentive_reward($node) {
        $this->psql->select('SUM(ifi_amount) as sum_amount');
        $this->psql->where(['node_address'=>$node,'type' => 1]);
        $res = $this->psql->get('ifi_award_log')->row();
        return $res->sum_amount;
    } 

    public function update_node($data, $where) {
        $this->psql->update('nodes',$data,$where);
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

    public function insert_node_startup($data) {
        // just insert it
        $this->psql->insert('nodes_startup',$data);
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
	
	 public function get_totalAwardToday($address)
    {
        $data[0]=0;
        $data[1]=0;
        $data[2]=0;
        $data[0]=$this->psql->query("select count(id) num from nodes where owner_address='".$address."'")->row_array()["num"]; 
        $this->psql->query("insert into ifi_award_counts(address,times,last_updated) select '".$address."',1,now() where not exists (select 1 from ifi_award_counts where address= '".$address."')");
        $this->psql->query("update ifi_award_counts set times=1,last_updated=now() where curdate()!=date(last_updated) and address='".$address."'");
        $this->psql->query("update ifi_award_counts set times=times+1,last_updated=now() where curdate()=date(last_updated) and address='".$address."'");
        $data[1]=$this->psql->query("select count(id) num from ifi_award_counts where address='".$address."'")->row_array()["num"];
        $data[2]=$this->psql->query("select sum(ifi_amount) totalAward from ifi_award_log  where CURDATE()=date(from_unixtime(`timestamp`)) and node_address='".$address."'")->row_array()["totalAward"]; 
        return $data;
    }

    public function get_other_accounts()
    {
      $data[0]=$this->psql->query("select address from other_accounts where type=0")->row_array()["address"];
      $data[1]=$this->psql->query("select address from other_accounts where type=1")->row_array()["address"];
      if(!isset($data[0])) $data[0]="";
      if(!isset($data[1])) $data[1]="";
      return $data;
    }

    public function get_active_signers()
    {
        $data[0]=$this->psql->query("select sum(weight) weights from signers")->row_array()["weights"];
        $data[1]=$this->psql->query("select address,weight from signers")->result_array();
        return $data;
    }

    public function update_ifi_award_log($id,$data)
    {  
        $where="log_id=".$id;
        $this->psql->update('ifi_award_log',$data,$where);
	  // $this->psql->query("update ifi_award_log set ifi_balance=".$data["ifi_balance"].",status=".$data["status"]." where log_id=".$id);
    }

    public function get_city($city_CN)
    {
        $data=$this->psql->query("select City_EN from city_names where City='".$city_CN."' or City_Admaster='".$city_CN."'")->result_array();
        return $data; 
    }


    public function get_active_nodes($current_page,$items_per_page)
    {   
       if($current_page<1) return null;
       $total_items=($current_page-1)*$items_per_page;
       $data[0]=$this->psql->query("select count(id) num from nodes where unix_timestamp(now())-unix_timestamp(last_updated)<=3600")->row_array()["num"];
       $data[1]=$this->psql->query("select count(id) num from nodes")->row_array()["num"];
      //$data[2]=$this->psql->query("select owner_address,total_reward,location,round(timestampdiff(second, (select startup_time  from nodes_startup where owner_address=nodes.owner_address  order by startup_time desc limit 1),last_updated)/3600.00,2) COT,round(timestampdiff(second, (select startup_time from nodes_startup where owner_address=nodes.owner_address  order by startup_time asc limit 1),last_updated)/3600.00,2) TOT,if(timestampdiff(second,nodes.last_updated,now())<=3600,'Active','Inactive') Status from nodes order by nodes.last_updated desc limit ". $total_items.",".$items_per_page)->result_array();
      $data[2]=$this->psql->query("select round((UNIX_TIMESTAMP(a.last_updated)-UNIX_TIMESTAMP(max(b.startup_time)))/3600.00,2) COT,round((UNIX_TIMESTAMP(a.last_updated)-UNIX_TIMESTAMP(min(b.startup_time)))/3600.00,2) TOT,a.owner_address,a.location,a.total_reward,if(timestampdiff(second,a.last_updated,now())<=3600,'Active','Inactive') Status from (select id,total_reward,location,owner_address,last_updated from nodes order by last_updated desc limit ".$total_items.",".$items_per_page.") a left join nodes_startup b on a.owner_address=b.owner_address group by a.id order by a.last_updated desc")->result_array();
  return $data;
    }

    public function get_ifi_award_log($address,$current_page,$items_per_page)
    {
        if($current_page<1) return null;
        $total_items=($current_page-1)*$items_per_page;
        $data[0]=$this->psql->query("select count(log_id) num from ifi_award_log where node_address='".$address."'")->row_array()["num"];
        $data[1]=$this->psql->query("select b.name type,a.ifi_amount amount,a.ifi_balance balance,from_unixtime(a.timestamp) time, if(a.status=1,'Confirmed','Unconfirmed') status from (select type, timestamp,status,ifi_amount,ifi_balance from ifi_award_log  where node_address='".$address."'order by timestamp desc limit ".$total_items.",".$items_per_page.") a inner join ifi_award_type b on a.type=b.type")->result_array();
        return $data;
    }

    public function get_summaryOfDay()
    {
        $data=$this->psql->query("select * from summaryOfDay")->result_array();
        return $data;
    }

}
