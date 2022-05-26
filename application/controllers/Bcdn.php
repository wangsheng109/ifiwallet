<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bcdn extends CI_Controller
{

	public function __construct()
	{
		parent::__construct();
		$this->api_url = "http://13.209.39.50:1633/files";
		header("Access-Control-Allow-Origin: * ");
	}


	public function upload()
	{
		//$this->output->set_header("Access-Control-Allow-Origin: * ");
		set_time_limit(0);
		if(count($_FILES)==0){
			$this->output->set_output(json_encode(['code'=>0,'msg'=>'请上传文件！'],true));
			return;
		}
		$this->input->ip_address();
		//$input_data = json_decode(trim(file_get_contents('php://input')), true);
		//$key = isset($input_data['key'])?$input_data['key']:"";
		$key=isset($_POST['key'])?$_POST['key']:"";
		if(!$key){
			$this->output->set_output(json_encode(['code'=>0,'msg'=>'登录失效，请重新登录！'],true));
			return;
		}
		if(strlen($key)==64)
		{
			if(strpos($key,"0x")!=0)
			{
				$this->output->set_output(json_encode(['code'=>0,'msg'=>'参数错误！'],true));
				return;
			}
			else
				$key="0x"+$key;
		}
		if(strlen($key)!=66) {
			$this->output->set_output(json_encode(['code' => 0, 'msg' => '参数错误！'], true));
			return;
		}
		$fileInfo = $_FILES["file"];
		$fileName = $fileInfo["name"];
		$suffix=$this->hz_name($fileName);
		$filesize=$fileInfo["size"];
		$ip = $_SERVER['REMOTE_ADDR'];
		$ch = curl_init($this->api_url);
		$data = array();
		$data['file'] = new \CURLFile($fileInfo['tmp_name']);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT,28800);
		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			$result = curl_error($ch);
		}
		curl_close($ch);
		$result=json_decode($result,true);
		if(!isset($result["reference"])){
			$this->output->set_output(json_encode(['code'=>0,'msg'=>'上传失败！'],true));
			return;
		}
		$hash=$result["reference"];
		//保存到数据库
		$time=time();
		$this->psql = $this->load->database('ette',true);
		$data = array(
			'hash'  =>  $hash,
			'filename'  =>  $fileName,
			'suffix'    =>  $suffix,
			'key' =>  $key,
			'add_time' =>  $time,
			'filesize'=>$filesize,
			'ip'=>$ip
		);
		if($this->psql->insert('bee_files', $data)) {
			$this->output->set_output(json_encode(['code'=>1,'msg'=>'上传成功！','data'=>["filename" => $fileName, "hash" => $hash, "time" => $time]],true));
			return;
		}
		else
		{
			$this->output->set_output(json_encode(['code'=>0,'msg'=>'上传失败！'],true));
			return;
		}
	}
	public function download()
	{
		$this->output->set_header("Access-Control-Allow-Origin: * ");
		if(!isset($_GET["hash"])){
			$this->output->set_output("参数错误");
			return;
		}
		$hash=$_GET["hash"];
		if(!$hash){
			$this->output->set_output("参数错误");
			return;
		}
		//数据库获取是否存在
		$this->psql = $this->load->database('ette',true);
		$result=$this->psql->query("SELECT * FROM bee_files WHERE hash='".$hash."'")->row_array();
		if($result&&count($result)<=0){
			$this->output->set_output("文件不存在或已删除！");
			return;
		}
		$filename=$result['filename'];
		$file=file_get_contents($this->api_url."/".$hash);
		header ( 'Content-Type: application/octet-stream' );
		header ( 'Content-Disposition: attachment; filename='.$filename);
		echo $file;
	}
	public function play()
	{
		if(!isset($_GET["hash"])){
			$this->output->set_output("参数错误");
			return;
		}
		$hash=$_GET["hash"];
		if(!$hash){
			$this->output->set_output("参数错误");
			return;
		}
		$headers = [];
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		$header_joins = [];
		foreach ($headers as $k => $v) {
			if ($k == 'X-Pingplusplus-Signature' || $k == 'Content-Type')
				array_push($header_joins, $k . ': ' . $v);
		}
		$this->output->set_output(json_encode($headers,true));
		return;
		$file_url=$this->api_url."/".$hash;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $file_url);//设置要访问的 URL
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $header_joins); //模拟用户使用的浏览器
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1 );  // 使用自动跳转
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);  //设置超时时间
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1 ); // 自动设置Referer
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 收集结果而非直接展示
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // 自定义 Headers
		$result = curl_exec($ch);
		curl_close($ch);
		echo $result;
	}
	function outPutStream() {
		if(!isset($_GET["hash"])){
			$this->output->set_output("参数错误");
			return;
		}
		$hash=$_GET["hash"];
		if(!$hash){
			$this->output->set_output("参数错误");
			return;
		}
		//数据库获取是否存在
		$this->psql = $this->load->database('ette',true);
		$result=$this->psql->query("SELECT * FROM bee_files WHERE hash='".$hash."'")->row_array();
		if($result&&count($result)<=0){
			$this->output->set_output("文件不存在或已删除！");
			return;
		}
		$filename=$result['filename'];
		$file_url=$this->api_url."/".$hash;
		//$file=file_get_contents($this->api_url."/".$hash);

		$sizeTemp = $result['filesize'];
		if (is_array($sizeTemp)) {
			$size = $sizeTemp[count($sizeTemp) - 1];
		} else {
			$size = $sizeTemp;
		}
		$ch2 = curl_init();
		curl_setopt($ch2, CURLOPT_URL, $file_url);
		curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch2, CURLOPT_TIMEOUT,28800);
		$content = curl_exec($ch2);
		curl_close($ch2);
		echo $content;
	}
	private function hz_name($file_name)
	{
		$extend = explode(".", $file_name);
		$va = count($extend) - 1;
		return strtolower($extend[$va]);
	}
	public function index()
	{
		$input_data = json_decode(trim(file_get_contents('php://input')), true);
		$page_index = isset($input_data['page_index'])?$input_data['page_index']:1;
		$page_size = isset($input_data['page_size'])?$input_data['page_size']:40;
		$start_row=($page_index-1)*$page_size;
		$this->psql = $this->load->database('ette',true);
		$result_array=$this->psql->query("select id,hash,filename,suffix,left(`key`,8) `key`,add_time,filesize from bee_files order by `id` desc limit ".$start_row.",".$page_size." ")->result_array();
		$rdata=array('code'=>1,'msg'=>'获取成功',"data"=>$result_array);
		//header("Access-Control-Allow-Origin: * ");
		//$this->output->set_header("Access-Control-Allow-Origin: * ");
		$this->output->set_output(json_encode($rdata,true));
	}
}


