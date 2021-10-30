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
		$this->contract="0x4D2f63d6826603B84D12C1C7dd33aB7F3BDe7553";
	}


	public function test()
	{
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		//$privateKey = isset($input_data['privateKey'])?$input_data['privateKey']:"";
		//$util = new Util;
		//$addr=$util->publicKeyToAddress($util->privateKeyToPublicKey($privateKey));
		//$from = $_POST['from'];
		//$num = $_GET['num'];
		//$wei=$this->ifiTowei($num);
		//$data= array('code'=>'1','msg'=>dechex($wei));
		$data= array('code'=>'1','msg'=>$input_data);
		$this->output->set_output(json_encode($data,true));
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
		$consume=0;
		$reward=$this->psql->query("select ifnull(sum(cast(total_reward AS DECIMAL(30))),0) num from nodes where owner_address='".$address_org."'")->row_array()['num'];
		$in=$this->psql->query("select IFNULL(SUM(cast(`value` AS DECIMAL(30))),0) num from transactions WHERE `to`='".$address_org."'")->row_array()['num'];
		$out=$this->psql->query("select IFNULL(SUM(cast(`value` AS DECIMAL(30))),0) num from transactions WHERE `from`='".$address_org."'")->row_array()['num'];
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


