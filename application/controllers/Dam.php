<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'libraries/Transaction.php');
use Web3p\EthereumTx\Transaction;
use Web3p\EthereumUtil\Util;
class Dam extends MY_Controller
{

	public function __construct()
	{
		parent::__construct();
		$this->load->model('ette_model');
		$this->load->model('coin_model');
		$this->rpc_url = $this->config->item('ifiRPC');
		$this->coin_name = "ifi";
		$this->coin_id = $this->config->item('ifi_coin_id');
		$this->chain_id = $this->config->item('ifi_chain_id');
		$this->rpc_url_pn = "http://18.216.66.9:8545";
		$this->contract=$this->config->item("ifi_contract_address");//"0xB1F052E948A63b1c560D569BBd8501B6B6D0690a";
		$this->chain_id_pn = "18888";
		$this->gas=210000;
		$this->gasLimit=8000000;
		$this->gasPrice=0;//-1时根据web3.eth.getGasPrice()获取
	}


	public function test()
	{
		$aa="8.805737880419684e20";
		$bb=str_replace(",","",number_format($aa));
		$cc=str_replace(",","",$bb);
		$data= array('code'=>'1','bb'=>$bb,'cc'=>$cc);
		$this->output->set_output(json_encode($data,true));
	}
	function NumToStr($num){
		if (stripos($num,'e')===false) return $num;
		$num = trim(preg_replace('/[=\'"]/','',$num,1),'"');//出现科学计数法，还原成字符串
		$result = "";
		while ($num > 0){
			$v = $num - floor($num / 10)*10;
			$num = floor($num / 10);
			$result   =   $v . $result;
		}
		return $result;
	}
	private function buildData($param){
		$data = '';
		$eol = "\r\n";
		$upload = $param['upload'];
		unset($param['upload']);

		foreach ($param as $name => $content) {
			$data .= "--" . static::$delimiter . "\r\n"
				. 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n"
				. $content . "\r\n";
		}
		// 拼接文件流
		$data .= "--" . static::$delimiter . $eol
			. 'Content-Disposition: form-data; name="file"; filename="' . $param['filename'] . '"' . "\r\n"
			. 'Content-Type:application/octet-stream'."\r\n\r\n";

		$data .= $upload . "\r\n";
		$data .= "--" . static::$delimiter . "--\r\n";
		return $data;
	}

	public function app_config()
	{
		$rpc_url=$this->config->item('ifiRPC_PN') == null ? $this->rpc_url_pn : $this->config->item('ifiRPC_PN');
		$contract=$this->contract;
		$rdata=array('status'=>1,'msg'=>'',"rpc_url"=>$rpc_url,'contract'=>$contract,'gas'=>$this->gas,'gasLimit'=>$this->gasLimit,'gasPrice'=>$this->gasPrice,'chain_id'=>$this->chain_id_pn);
		$this->output->set_output(json_encode($rdata,true));
	}

	public function get_balance()
	{
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$address_org=$address = isset($input_data['address'])?$input_data['address']:"";
		if(!$address){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'address can not empty'],true));
			return;
		}
		if(substr($address,0,2)=="0x"){
			$address=substr($address,2);
		}
		$contract = $this->contract;
		$funcSelector = "0x70a08231";
		$data = $funcSelector . "000000000000000000000000" . $address;
		$method  = "eth_call";
		$param1 = [
			"data"  => $data,
			"to"    =>  $contract
		];
		$params  = [$param1,"latest"];
		$result = $this->call($method,$params);
		if(is_array($result)){
			$result['status']=0;
			$result['msg']='查询余额失败';
			$this->output->set_output(json_encode($result,true));
			return;
		}
		$result=hexdec($result);//weiToifi(hexdec($result))
		$this->psql = $this->load->database('ette',true);
		$reward=$this->psql->query("select ifnull(sum(cast(total_reward AS DECIMAL(30))),0) num from nodes where owner_address='".$address_org."'")->row_array()['num'];
		$in=$this->psql->query("select IFNULL(SUM(cast(`value` AS DECIMAL(30))),0) num from transactions WHERE `to`='".$address_org."'")->row_array()['num'];
		$out=$this->psql->query("select IFNULL(SUM(cast(`value` AS DECIMAL(30))),0) num from transactions WHERE `from`='".$address_org."'")->row_array()['num'];
		$consume=$reward+$in-$out-$result;
		$rdata=array('status'=>1,'msg'=>'',"balance"=>$result,'reward'=>$reward,'consume'=>$consume,'in'=>$in,'out'=>$out);
		$this->output->set_output(json_encode($rdata,true));
	}
	public function get_transaction()
	{
		//$address = $_GET['address'];
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$address = isset($input_data['address'])?$input_data['address']:"";
		$page_index = isset($input_data['page_index'])?$input_data['page_index']:1;
		$page_size = isset($input_data['page_size'])?$input_data['page_size']:10;
		if(!$address){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'address can not empty'],true));
			return;
		}
		$start_row=($page_index-1)*$page_size;
		$this->psql = $this->load->database('ette',true);
		$total_result=$this->psql->query("select count(`hash`) num from transactions where `from`='".$address."' or `to`='".$address."' order by `timestamp`")->row_array();
		$result_array=$this->psql->query("select `hash`,`from`,`to`,`value`,gas,gasprice,cost,nonce,state,blockhash,blockNumber,`timestamp` from transactions where `from`='".$address."' or `to`='".$address."' order by `timestamp` limit ".$start_row.",".$page_size." ")->result_array();
		$rdata=array('status'=>1,'msg'=>'','total'=>$total_result['num'],"data"=>$result_array);
		$this->output->set_output(json_encode($rdata,true));
	}

	public function transfer()
	{
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$amount = isset($input_data['amount'])?$input_data['amount']:0;
		$privateKey = isset($input_data['privateKey'])?$input_data['privateKey']:"";
		$to = isset($input_data['to'])?$input_data['to']:"";
		if(!$amount||!$privateKey||!$to)
		{
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'Request params not valid'],true));
			return;
		}
		if($amount<=0){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'Amount not valid'],true));
			return;
		}
		if (substr($to, 0, 2) == "0x") {
			$to = substr($to, 2);
		}
		$util = new Util;
		$from=$util->publicKeyToAddress($util->privateKeyToPublicKey($privateKey));
		$contract = $this->contract;
		$funcSelector = "0xa9059cbb";
		$amount=dechex($amount);//dechex($this->ifiTowei($amount))
		$amt_hex = $amount;
		$data = $funcSelector . "000000000000000000000000" . $to;
		if (substr($amt_hex, 0, 2) == "0x") {
			$amt_hex = substr($amt_hex, 2);
		}
		$len = strlen($amt_hex);
		$amt_val = "";
		$i = 0;
		while ($i < 64 - $len) {
			$amt_val .= "0";
			$i++;
		}
		$amt_val .= $amt_hex;
		$data .= $amt_val;
		$gas = '0x' . dechex(93334);
		$gasPrice = '0x' . dechex($this->config->item('gas_price'));
		$nonce = $this->get_nonce($from);
		if(is_array($nonce)){
			$this->output->set_output(json_encode($nonce,true));
			return;
		}
		$cnonce = '0x' . dechex($nonce);
		$param = [
			'nonce'     => $cnonce,
			'from'      => $from,
			'to'        => $contract,
			'gas'       => $gas,
			'gasPrice'  => $gasPrice,
			'data'      => $data,
			'chainId'   =>  $this->config->item('ifi_real_chain_id')
		];
		$transaction = new Transaction($param);
		$signedTransaction = $transaction->sign($privateKey);
		$method  = "eth_sendRawTransaction";
		$params  = ["0x" . $signedTransaction];
		$out_arr = $this->call($method, $params);
		if(!is_array($out_arr)){
			$out_arr=array('status'=>1,'msg'=>'',"hash"=>$out_arr);
		}
		else{
			$out_arr['status']=0;
			$out_arr['msg']='转账失败';
		}
		$this->output->set_output(json_encode($out_arr,true));
	}

	public function get_run_time() {
		$run_time=0;
		$current_run_time=0;
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$address = isset($input_data['address'])?$input_data['address']:"";
		$chequebook_address = isset($input_data['chequebook_address'])?$input_data['chequebook_address']:"";
		$local_ip = isset($input_data['local_ip'])?$input_data['local_ip']:"";
		if(!$address){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'address can not empty'],true));
			return;
		}
		if(!$chequebook_address){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'chequebook_address can not empty'],true));
			return;
		}
		if(!$local_ip){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'local_ip can not empty'],true));
			return;
		}
		$this->psql = $this->load->database('ette',true);
		$result=$this->psql->query("SELECT TIMESTAMPDIFF(SECOND,(SELECT b.startup_time FROM nodes_startup b WHERE a.owner_address=b.owner_address and a.chequebook_address=b.chequebook_address and a.local_ip=b.local_ip order by b.startup_time ASC LIMIT 1),last_updated) run_time,TIMESTAMPDIFF(SECOND,(SELECT b.startup_time FROM nodes_startup b WHERE a.owner_address=b.owner_address and a.chequebook_address=b.chequebook_address and a.local_ip=b.local_ip order by b.startup_time DESC LIMIT 1),last_updated) c_run_time FROM nodes a WHERE owner_address='".$address."' and chequebook_address='".$chequebook_address."' and local_ip='".$local_ip."'")->row_array();
		if($result&&count($result)>0){
			$run_time=$result['run_time'];
			$current_run_time=$result['c_run_time'];
		}
		$rdata=array('status'=>1,'msg'=>'','run_time'=>$run_time,"current_run_time"=>$current_run_time);
		$this->output->set_output(json_encode($rdata,true));
	}
	public function check_device() {
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$address = isset($input_data['address'])?$input_data['address']:"";
		if(!$address){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'address can not empty'],true));
			return;
		}
		$has_device="0";
		$this->psql = $this->load->database('ette',true);
		$result=$this->psql->query("SELECT * FROM nodes WHERE owner_address='".$address."' order by last_updated desc limit 1")->row_array();
		if($result&&count($result)>0){
			$has_device=$result['status'];
		}
		$rdata=array('status'=>1,'msg'=>'','has_device'=>$has_device);
		$this->output->set_output(json_encode($rdata,true));
	}
	public function device_accounts() {
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$list = isset($input_data['list'])?$input_data['list']:"";
		if(!$list){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'$list can not empty'],true));
			return;
		}
		$list=json_decode($list,true);
		$nlist=[];
		$this->psql = $this->load->database('ette',true);
		foreach ($list as $index=>$obj)
		{
			$result=$this->psql->query("SELECT `status` FROM nodes WHERE owner_address='".$obj['address']."' order by last_updated desc limit 1")->row_array();
			if($result&&count($result)>0){
				$obj['flag']=$result['status']==1?"Active":"Inactive";
				$funcSelector = "0x70a08231";
				$data = $funcSelector . "000000000000000000000000" . substr($obj['address'],2);
				$method  = "eth_call";
				$param1 = ["data" => $data, "to" => $this->contract];
				$params  = [$param1,"latest"];
				$blance = $this->call($method,$params);
				if(is_array($blance)){
					$obj['blance']=0;
				}
				else {
					$obj['blance'] = hexdec($blance);
					$obj['blance']=str_replace(",","",number_format($obj['blance']));
				}
				array_push($nlist,$obj);
			}
//			else{
//				$obj['flag']="-";
//				$funcSelector = "0x70a08231";
//				$data = $funcSelector . "000000000000000000000000" . substr($obj['address'],2);
//				$method  = "eth_call";
//				$param1 = ["data" => $data, "to" => $this->contract];
//				$params  = [$param1,"latest"];
//				$blance = $this->call($method,$params);
//				if(is_array($blance)){
//					$obj['blance']=0;
//				}
//				else {
//					$obj['blance'] = hexdec($blance);
//					$obj['blance']=str_replace(",","",number_format($obj['blance']));
//				}
//				array_push($nlist,$obj);
//			}
		}
		$this->output->set_output(json_encode($nlist,true));
	}
	public function device() {
		$run_time=0;
		$current_run_time=0;
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$address = isset($input_data['address'])?$input_data['address']:"";
		if(!$address){
			$this->output->set_output(json_encode(['status'=>0,'msg'=>'address can not empty'],true));
			return;
		}
		$chequebook_address = "";
		$local_ip = "";
		$total_reward=0;
		$total_consumed=0;
		$location="";
		$status="Inactive";
		$tot=0;
		$cot=0;
		$this->psql = $this->load->database('ette',true);
		$result=$this->psql->query("SELECT * FROM nodes WHERE owner_address='".$address."' order by last_updated desc limit 1")->row_array();
		if($result&&count($result)>0){
			$chequebook_address=$result['chequebook_address'];
			$local_ip=$result['local_ip'];
			$total_reward=str_replace(",","",number_format($result['total_reward']));//$result['total_reward'];
			$status=$result['status']==1?"Active":"Inactive";

			$funcSelector = "0x70a08231";
			$data = $funcSelector . "000000000000000000000000" . substr($address,2);
			$method  = "eth_call";
			$param1 = ["data" => $data, "to" => $this->contract];
			$params  = [$param1,"latest"];
			$blance = $this->call($method,$params);
			if(is_array($blance)){
				$blance=0;
			}
			$blance=hexdec($blance);
			$in=$this->psql->query("select IFNULL(SUM(cast(ifi_amount AS DECIMAL(30))),0) num from transactions_dam WHERE `to`='".$address."'")->row_array()['num'];
			$out=$this->psql->query("select IFNULL(SUM(cast(ifi_amount AS DECIMAL(30))),0) num from transactions_dam WHERE `from`='".$address."'")->row_array()['num'];
			$total_consumed=$total_reward+$in-$out-$blance;////消费总额可通过（总激励额 + 转入 - 转出 - 余额）简单计算，目前不要求精确数值。
			if($total_consumed<0) $total_consumed=0;
			else $total_consumed=str_replace(",","",number_format($total_consumed));
		    $url="http://ip-api.com/json/".$local_ip;//."?lang=zh-CN";
		 //	$result = commit_curl($url);
			$result=geturl($url);
			if($result!='')
			{
				$arr = json_decode($result,true);
				if($arr["country"]!=''&&$arr["city"]!='')
			    {
			     $location=$arr["city"].", ".$arr["country"];
		        }
			}
			else
            {  

            $url = "http://api.ipstack.com/".$local_ip."?access_key=b110acacd3a7ce2b43d72ed401e6a5c9&format=1";//"?access_key=ce28c8d21809d0498fb2176b95addb7b&format=1";
	   //	$result = commit_curl($url);
			$result=geturl($url);
			if($result!='')
			{ 
			   $arr = json_decode($result,true);
			   if($arr["country_name"]!=''&&$arr["city"]!='')
			   {
				$location=$arr["city"].", ".$arr["country_name"];
	           }
            }
			
            }

			//$result=$this->psql->query("SELECT TIMESTAMPDIFF(SECOND,(SELECT b.startup_time FROM nodes_startup b WHERE a.owner_address=b.owner_address and a.chequebook_address=b.chequebook_address and a.local_ip=b.local_ip order by b.startup_time ASC LIMIT 1),last_updated) run_time,TIMESTAMPDIFF(SECOND,(SELECT b.startup_time FROM nodes_startup b WHERE a.owner_address=b.owner_address and a.chequebook_address=b.chequebook_address and a.local_ip=b.local_ip order by b.startup_time DESC LIMIT 1),last_updated) c_run_time FROM nodes a WHERE owner_address='".$address."' and chequebook_address='".$chequebook_address."' and local_ip='".$local_ip."'")->row_array();
			$result=$this->psql->query("SELECT TIMESTAMPDIFF(SECOND,(SELECT b.startup_time FROM nodes_startup b WHERE a.owner_address=b.owner_address order by b.startup_time ASC LIMIT 1),last_updated) run_time,TIMESTAMPDIFF(SECOND,(SELECT b.startup_time FROM nodes_startup b WHERE a.owner_address=b.owner_address order by b.startup_time DESC LIMIT 1),last_updated) c_run_time FROM nodes a WHERE owner_address='".$address."'")->row_array();
			if($result&&count($result)>0){
				$tot=round($result['run_time']*1.0/60/60,2);
				$cot=round($result['c_run_time']*1.0/60/60,2);
			}
		}
		$rdata=array('status'=>1,'msg'=>'','total_reward'=>$total_reward,"total_consumed"=>$total_consumed,"location"=>$location,"status"=>$status,"tot"=>$tot,"cot"=>$cot);
		$this->output->set_output(json_encode($rdata,true));
	}
	public function reward_list(){
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$address = isset($input_data['address'])?$input_data['address']:"";
		$page_index = isset($input_data['page_index'])?$input_data['page_index']:1;
		$page_size = isset($input_data['page_size'])?$input_data['page_size']:10;
		if(!$address){
			//$this->output->set_output(json_encode(['status'=>0,'msg'=>'address can not empty'],true));
			//return;
		}
		$start_row=($page_index-1)*$page_size;
		$this->psql = $this->load->database('ette',true);
		$total_result=$this->psql->query("select count(`log_id`) num from ifi_award_log where `node_address`='".$address."'")->row_array();
		$result_array=$this->psql->query("select b.`name` typeName,log_id,ifi_amount,`timestamp` ,FROM_UNIXTIME(`timestamp`) from ifi_award_log a,ifi_award_type b where a.type=b.type and `node_address`='".$address."' order by `timestamp` desc limit ".$start_row.",".$page_size." ")->result_array();
   //	$result_array=$this->psql->query("select b.`name` typeName,log_id,ifi_amount,`timestamp` ,FROM_UNIXTIME(`timestamp`) from ( select type,log_id, ifi_amount,`timestamp` from ifi_award_log  where  node_address='".$address."' order by `timestamp` desc limit  ".$start_row.",".$page_size.") a left join ifi_award_type b on a.type=b.type ")->result_array();

		$rdata=array('status'=>1,'msg'=>'','total'=>$total_result['num'],"data"=>$result_array);
		$this->output->set_output(json_encode($rdata,true));
	}
	public function trans(){
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$address = isset($input_data['address'])?$input_data['address']:"";
		$this->psql = $this->load->database('ette',true);
		$sent=$this->psql->query("select IFNULL(SUM(cast(ifi_amount AS DECIMAL(30))),0) num from transactions_dam where `from`='".$address."'")->row_array()['num'];
		$received=$this->psql->query("select IFNULL(SUM(cast(ifi_amount AS DECIMAL(30))),0) num from transactions_dam where `to`='".$address."'")->row_array()['num'];
		$rdata=array('status'=>1,'msg'=>'','sent'=>$sent,"received"=>$received);
		$this->output->set_output(json_encode($rdata,true));
	}
	public function save_transfer_log() {
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$hash = isset($input_data['hash'])?$input_data['hash']:"";
		$from = isset($input_data['from'])?$input_data['from']:"";
		$to = isset($input_data['to'])?$input_data['to']:"";
		$ifi_amount = isset($input_data['ifi_amount'])?$input_data['ifi_amount']:"";
		$timestamp = isset($input_data['timestamp'])?$input_data['timestamp']:"";
		if($hash==""||$from==""||$to=="")
		{
			$rdata=array('status'=>0,'msg'=>'params error');
			$this->output->set_output(json_encode($rdata,true));
			return;
		}
		$this->psql = $this->load->database('ette',true);
		$data = array(
			'hash'  =>  $hash,
			'from'  =>  $from,
			'to'    =>  $to,
			'ifi_amount' =>  $ifi_amount,
			'timestamp' =>  $timestamp
		);
		$this->psql->insert('transactions_dam', $data);
		$rdata=array('status'=>1,'msg'=>'');
		$this->output->set_output(json_encode($rdata,true));
	}

	public function get_nonce($address) {
		$method  = "eth_getTransactionCount";
		$param = [$address,"latest"];
		$result = $this->call($method,$param);
		if(is_array($result)){
			return ["status"=>0,"msg"=>"error when getting nonce "];
		}
		$count = hexdec($result);
		return $count;
	}

	private  function weiToifi($wei){
		return rtrim(rtrim(bcdiv($wei,10000000000000000),'0'),'.');
	}
	private  function ifiTowei($ifi){
		return number_format($ifi*(1.0E+16),0,'.','');
	}
}


