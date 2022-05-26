<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'libraries/Transaction.php');
use Web3p\EthereumTx\Transaction;
class Irc20 extends MY_Controller {
    
        public function __construct()
        {
            parent::__construct();
            $this->load->model('ette_model');
            $this->load->model('ifi_coin_model');
            $this->rpc_url = $this->config->item('ifiRPC');
            $this->coin_name = "ifi";
            $this->coin_id = $this->config->item('ifi_coin_id');
            $this->chain_id = $this->config->item('ifi_chain_id');
        }
		function getLogPath() 
		{
            $path='/var/log/voyager_log/';	
            $logPath=$path.'voyager_'.date('Y_m_d_H_i_s').'.log';			
            if ($handle = opendir(realpath($path))) { 
            $filenames=array();		
            while (false !== ($file = readdir($handle))) {    
                if ($file === '.' || $file === '..') {    
                    continue;    
                }
                $this_file = $path . '/' . $file;
                if (is_file($this_file)) {
					array_push($filenames,$file);
                }
            }
            closedir($handle);
            $len=count($filenames);	
            			
			if($len>0)
			{ 
		    $temp=$filenames[0];
			for($i=0;$i<$len;$i++)
			{
				if(strcmp($filenames[$i],$temp)>0)
				{
					$temp=$filenames[$i];
				}
			}
			if(filesize($path.$temp)<=200*1024*1024)
			{ 	
			$logPath=$path.$temp;
			}	
			}						
        }
	//	echo $logPath;
		return $logPath; 
    }
		public function saveLog1($data)
		{    
			$logPath=$this->getLogPath();		
			saveLog($data,$logPath);
		}
        
        public function get_token_balance1($address){
         $contract=$this->config->item("ifi_contract_address");
            if(substr($address,0,2)=="0x"){
                $address=substr($address,2);
            } 
            $funcSelector = "0x70a08231";
            $data = $funcSelector . "000000000000000000000000" . $address;
            $method  = "eth_call";
            $param1 = [
                "data"  => $data,
                "to"    =>  $contract
            ];
            $params  = [$param1,"latest"];
            $result = $this->call($method,$params);
           // return (hexdec($result));
           echo (double)(hexdec($result));
        }


        public function get_token_balance($address,$contract,$dec){
            if(substr($address,0,2)=="0x"){
                $address=substr($address,2);
            } 
            $funcSelector = "0x70a08231";
            $data = $funcSelector . "000000000000000000000000" . $address;
            $method  = "eth_call";
            $param1 = [
                "data"  => $data,
                "to"    =>  $contract
            ];
            $params  = [$param1,"latest"];
            $result = $this->call($method,$params);
            return (hexdec($result)/$dec);
        }

        public function get_nonce($address) {
            $method  = "eth_getTransactionCount";
            $param = [$address,"latest"];
            $result = $this->call($method,$param);
            if(is_array($result)){
                exit("\r\n error when getting nonce \r\n");
            }
            $count = hexdec($result);
            return $count;
        }
        
        public function send_ifi()
        {
            $to = $_GET['address'];
            $amount = isset($_GET['amount'])?$_GET['amount'] : 9;
            $from = $this->config->item("ifiPayAccount");
            $fromPri = decrypt($this->config->item("encrypted_ifi_wallet"));
            $contract = $this->config->item("ifi_contract_address");
            $tx_res = $this->send_token($this->add_random($amount),$from,$fromPri,$contract,$to);
            echo "\r\n send ifi sucessfully with tx hash : ".$tx_res."\r\n";
        }

     /*   public function get_ifi()
        {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $owner_address = $input_data['owner_address'];
            $cpu_name = $input_data['cpu_name'];
            $cpu_score = $input_data['cpu_score'];
            $local_ip = $input_data['local_ip'];
            $data = array(
                'owner_address' =>  $owner_address,
                'cpu_name'    =>  $cpu_name,
                'cpu_score' =>  $cpu_score,
                'local_ip'  =>  $local_ip,
                'last_updated' => date('Y-m-d H:i:s')
            );
            $this->ette_model->set_node($data, $owner_address);
            // send ifi
            $from = $this->config->item("ifiPayAccount");
            $fromPri = decrypt($this->config->item("encrypted_ifi_wallet"));
            $contract = $this->config->item("ifi_contract_address");
            $ifi_amount = $this->add_random($cpu_score);
            //send ifi to 5 different account
            // $tx_res1 = $this->send_token($this->cal($ifi_amount,10,100),$from,$fromPri,$contract,$this->config->item("a_address"));
            // $tx_res2 = $this->send_token($this->cal($ifi_amount,5,100),$from,$fromPri,$contract,$this->config->item("b_address"));
            // $tx_res3 = $this->send_token($this->cal($ifi_amount,5,100),$from,$fromPri,$contract,$this->config->item("c_address"));
            // $tx_res4 = $this->send_token($this->cal($ifi_amount,20,100),$from,$fromPri,$contract,$this->config->item("d_address"));
            //$tx_res = $this->send_token($this->cal($ifi_amount,60,100),$from,$fromPri,$contract,$owner_address);


              $succeeded=false;
             $isFirst=true;
             $nonce = $this->get_nonce($from);
             while(!$succeeded){            
            // if(!$isFirst) sleep(2);
            // $isFirst=false;
             $tx_res = $this->send_token1($this->cal($ifi_amount,60,100),$from,$fromPri,$contract,$owner_address,0,$nonce);
             if(!is_array($tx_res)&&!($tx_res=='')) $succeeded=true;
            // if(is_array($tx_res)) $this->saveLog1($owner_address. PHP_EOL .json_encode($tx_res));
            // if($tx_res==''){ $this->saveLog1($owner_address. PHP_EOL);}
             $nonce=$nonce+1;
            }



            if(is_array($tx_res)){
                echo "\r\n send ifi failed with error : ".json_encode($tx_res,true)."\r\n";
            } else {
                $data = array(
                    'node_address'  =>  $owner_address,
                    'ifi_amount'    =>  base_convert($ifi_amount,16,10),
                    'timestamp'     =>  time(),
                    'from_account'  =>  $from,
                    'tx_hash'       =>  $tx_res
                );
                $this->ette_model->insert_award_log($data);
                echo  "\r\n the server score is ".$cpu_score."\r\n";
                echo "\r\n send ifi sucessfully with tx hash : ".$tx_res."\r\n";
            }
        }*/
		
		
		
		 public function get_ifi()
        {   
		    $tx_res='';
			$res=array("resCode"=>-1,"errorMsg"=>"","transactionHash"=>"","amount"=>0.00);
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $owner_address = isset($input_data['owner_address'])?$input_data['owner_address']:"";
            $cpu_name = isset($input_data['cpu_name'])?$input_data['cpu_name']:"";
			$local_ip = isset($input_data['local_ip'])?$input_data['local_ip']:"";
            $cpu_score = isset($input_data['cpu_score'])?$input_data['cpu_score']:0;
			$cpu_physicsScore=isset($input_data['physicsScore'])?$input_data['physicsScore']:0;
			$idCode=isset($input_data['idCode'])?$input_data['idCode']:"";
            $apiKey=isset($input_data['apiKey'])?$input_data['apiKey']:"";
            $local_apiKey="e1628fd41c0a0bf3fe673ac5a52de0370b32bdc484d19f15feb012c748ed459c";
			$logicalScore=isset($input_data['logicalScore'])?$input_data['logicalScore']:0;
			$max=0.15*1000000000000000000;
			$min=0.05*1000000000000000000;
			$oneMax=0.00;
			$oneMin=0.00;
			$ratio=1.00;
			
			
            if(strcmp($apiKey,$local_apiKey)!=0)
            {
                $res["errorMsg"]="error,you are not authorized";
				echo json_encode($res);
				return;
            }
            
			if($owner_address=='')
			{
				$res["errorMsg"]="error,you did not send address";
				echo json_encode($res);
				return;
			}
			if(!preg_match('/^0x[0-9,a-z]{40}$/i', $owner_address))
			{
				$res["errorMsg"]="error,the address you sent is illegal";
				echo json_encode($res);
				return;
			}
			if(!is_numeric($cpu_score))
			{
				$res["errorMsg"]="error,score should be a number";
				echo json_encode($res);
				return;
			}
			if($cpu_score<=0 )
			{
				$res["errorMsg"]="error,score should be bigger than 0";
				echo json_encode($res);
				return;
			}
			
			if(!is_numeric($cpu_physicsScore))
			{
				$res["errorMsg"]="error,physicsScore should be a number";
				echo json_encode($res);
				return;
			}
			if($cpu_physicsScore<=0 )
			{
				$res["errorMsg"]="error,physicsScore should be bigger than 0";
				echo json_encode($res);
				return;
			}

			
			if($idCode!='')
			{
			 $url="http://116.63.82.233:8086/apiKey/checkDeviceId";
					 $params=array("Id"=>$idCode,"apiKey"=>"4af0653b48e511eca704fa163e796a24");
					 $rs=posturl($url,$params);
					
					 if(isset($rs)){
					   if(is_array($rs)){
						 // echo json_encode($rs)."\r\n";
						if($rs["code"]==0){ 
					       $ratio=1.15;
						}					
					   }
                      }
			 }
		    $max=$max*$ratio;
			$min=$min*$ratio;
			$oneMax=$max/48.00;
			$oneMin=$min/48.00;
			 $metaengine_score=$cpu_score*0.60;
			 
			 if($metaengine_score<$oneMin)
			 {
				 $metaengine_score=$oneMin;
				 $cpu_score=$metaengine_score/0.60;
			 }
			 if($metaengine_score>$oneMax)
			 {
				 $metaengine_score=$oneMax;
				 $cpu_score=$metaengine_score/0.60;
			 }
           $test_data=$this->ette_model->get_totalAwardToday($owner_address);
		 
		  
             	if($test_data[0]<=0)
				{
				$res["errorMsg"]="error,the address is not registered";
				echo json_encode($res);
				return;
				}	
                if($test_data[1]==48)
				{
					if($test_data[2]+$metaengine_score<$min)
					{
						$metaengine_score=$min-$test_data[2];
						$cpu_score=$metaengine_score/0.60;
					}
                }
                if($test_data[1]>48)
				{
				$res["errorMsg"]="error,you have sent enough time today ";
				echo json_encode($res);
				return;
				}	
				
				
				if($test_data[2]>=$max)
				{
				$res["errorMsg"]="error,you have sent enough reward today ";
				echo json_encode($res);
				return;
				}
                if($test_data[2]+$metaengine_score>$max)	
				{
					$metaengine_score=$max-$test_data[2];
					$cpu_score=$metaengine_score/0.60;
				}
				
              				
		 
            // echo  "\r\n the server score is ".$cpu_score."\r\n";
            
            $data = array(
                'owner_address' =>  $owner_address,
                'cpu_name'    =>  $cpu_name,
                'cpu_score' =>  $cpu_physicsScore,
                'local_ip'  =>  $local_ip,
				'logicalScore'=>$logicalScore,
                'last_updated' => date('Y-m-d H:i:s')
            );
            $this->ette_model->set_node($data, $owner_address);
            // send ifi
            $from = $this->config->item("ifiPayAccount");
            $fromPri = decrypt($this->config->item("encrypted_ifi_wallet"));
            $contract = $this->config->item("ifi_contract_address");
            $ifi_amount = $cpu_score;//$this->add_random($cpu_score);
                         
            $metaengine_reward=$ifi_amount*0.6;

         //   $totalAwardToday=$this->ette_model->get_totalAwardToday($owner_address);

         //   if($totalAwardToday+$metaengine_reward>0.3*1000000000000000000) return;
		   $other_address=$this->ette_model->get_other_accounts();
		  
            $accounts=array("foundation"=>array("address"=>$other_address[0],"ratio"=>0.1),"developer"=>array("address"=>$other_address[1],"ratio"=>0.1),"metaengine"=>array("address"=>$owner_address,"ratio"=>0.6));

            $data=$this->ette_model->get_active_signers();
            $weights=$data[0];
            $signers=$data[1];
            $ratios=0.2;
            $singerName='';
            $singerRatio=0;
            $amount=0;
			$ifi_balance=0;
			$ifi_balance1=0;
            $len=count($signers);
            for($i=0;$i<$len;$i++)
            {   
		          if($weights>0)
				  {
                 $signerRatio=$ratios*$signers[$i]["weight"]/$weights;
				  }
				  else
				  {
			    $signerRatio=1.00/($len*1.00);
				  }
                 $singerName="signer".$i;
                // array_push($accounts, array("address"=>($signers[$i]["address"]),"ratio"=>$signerRatio));
				$accounts[$singerName]=array("address"=>($signers[$i]["address"]),"ratio"=>$signerRatio);
            }


           $ifi_balance=$this->get_token_balance($owner_address,$this->config->item('ifi_contract_address'),1);
           foreach ($accounts as $key => $value)
           {
         
             $amount=$ifi_amount*$value["ratio"];
             $owner_address=$value["address"];
			 if($amount<=0) continue;
			 if($owner_address=='') continue;
			// echo "\n".$key."  ".$amount."\n";
			 
             $succeeded=false;
             $isFirst=true;
             $nonce = $this->get_nonce($from);
             while(!$succeeded){            
             $tx_res = $this->send_token1(dechex($amount),$from,$fromPri,$contract,$owner_address,0,$nonce);
             if(!is_array($tx_res)&&!($tx_res=='')) $succeeded=true;
			 if(is_array($tx_res)) $this->saveLog1($owner_address. PHP_EOL .json_encode($tx_res));
			 if($tx_res==''){ $this->saveLog1($owner_address. PHP_EOL);}
             $nonce=$nonce+1;
            }


             if(strcmp($key,"metaengine")==0){
            if(is_array($tx_res)){
             //   echo "\r\n send ifi failed with error : ".json_encode($tx_res,true)."\r\n";
			 $res["resCode"]=-1;
			 $res["errorMsg"]=json_encode($tx_res);
            } else {
                $data = array(
                    'node_address'  =>  $owner_address,
                    'ifi_amount'    =>  $amount,
                    'timestamp'     =>  time(),
                    'from_account'  =>  $from,
                    'tx_hash'       =>  $tx_res,
					'ifi_balance'   =>$ifi_balance//$this->get_token_balance($owner_address,$this->config->item('ifi_contract_address'),1)
                );
				
				$res["resCode"]=200;
			 $res["transactionHash"]=$tx_res;
			 $res["amount"]=round($amount*1.00/1000000000000000000.0000,18);
				
			//	if($amount<=0) $data["status"]=1;
              $id= $this->ette_model->insert_award_log($data);
			 //  echo "\r\n send ".($amount/1000000000000000000.00)." SLK sucessfully with tx hash : ".$tx_res."\r\n";
			   $times=0;
              //   echo "\ninsert id:".$id."\n";
               
				
				while($times<0&&$amount>0)
				{   
			        sleep(2);
				/*	$ifi_balance1=$this->get_token_balance($owner_address,$this->config->item('ifi_contract_address'),1);
					if($ifi_balance1>$ifi_balance) 
					{  
				      $data["ifi_balance"]=$ifi_balance1;
					  $data["status"]=1;
				      $this->ette_model->update_ifi_award_log($id,$data);
						break;
					}
					$times++;	*/
                 /*	$trx=$this->get_trx($tx_res);
					if($trx["transactionIndex"]!='')
					{
					   $ifi_balance1=$this->get_token_balance($owner_address,$this->config->item('ifi_contract_address'),1);
					   $data["ifi_balance"]=$ifi_balance1;
					   $data["status"]=1;
				       $this->ette_model->update_ifi_award_log($id,$data);
					   break;
					}
					$times++;		
					*/					
			   	     $trxR=$this->get_trxR($tx_res);
			        if($trxR!=''){
					if(hexdec($trxR["status"])==1)
					{  
					   $ifi_balance1=$this->get_token_balance($owner_address,$this->config->item('ifi_contract_address'),1);
					   $data["ifi_balance"]=$ifi_balance1;
					   $data["status"]=1;
				       $this->ette_model->update_ifi_award_log($id,$data);
					   break;
					}
					}
					$times++;							
				}											
                }
            }
        }
		
		echo json_encode($res);

        
        }

        //store the incentive_reward in db
        public function set_incentive_reward()
        {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $owner_address = $input_data['owner_address'];
            $ifi_amount = $input_data['ifi_amount'];
            $tx_res = $input_data['tx_res'];
            $from_account = $input_data['from_account'];

            $data = array(
                'node_address'  =>  $owner_address,
                'ifi_amount'    =>  $ifi_amount,
                'timestamp'     =>  time(),
                'from_account'  =>  $from_account,
                'type'          =>  1,
                'tx_hash'       =>  $tx_res
            );
            $this->ette_model->insert_award_log($data);
            //send ifi to 4 different account
            $from = $this->config->item("ifiPayAccount");
            $fromPri = decrypt($this->config->item("encrypted_ifi_wallet"));
            $contract = $this->config->item("ifi_contract_address");
            // $tx_res1 = $this->send_token($this->cal($ifi_amount,10,100),$from,$fromPri,$contract,$this->config->item("a_address"));
            // $tx_res2 = $this->send_token($this->cal($ifi_amount,5,100),$from,$fromPri,$contract,$this->config->item("b_address"));
            // $tx_res3 = $this->send_token($this->cal($ifi_amount,5,100),$from,$fromPri,$contract,$this->config->item("c_address"));
            // $tx_res4 = $this->send_token($this->cal($ifi_amount,20,100),$from,$fromPri,$contract,$this->config->item("d_address"));
            echo "\r\n set incentive reward sucessfully\r\n";
        }

        public function register_node()
        {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $owner_address = $input_data['owner_address'];
            $chequebook_address = $input_data['chequebook_address'];
            $local_ip = $input_data['local_ip'];
            $data = array(
                'owner_address' =>  $owner_address,
                'chequebook_address'    =>  $chequebook_address,
                'local_ip'  =>  $local_ip,
				'location'=>$this->get_location($local_ip),
                'last_updated' => date('Y-m-d H:i:s')
            );
            $data1 = array(
                'owner_address' =>  $owner_address,
                'chequebook_address'    =>  $chequebook_address,
                'local_ip'  =>  $local_ip,
                'startup_time' => date('Y-m-d H:i:s')
            );
            $this->ette_model->set_node($data, $owner_address);
            $this->ette_model->insert_node_startup($data1);
            echo "\r\n register/update the node at the init \r\n";
        }

        

        private function add_random($amount)
        {
            $dec_amount = base_convert($amount,16,10);
            $new_dec = $dec_amount*(rand(0,20)+100)/100;
            return base_convert($new_dec,10,16);
        }

    public function test()
    {
       /* $res = $this->send_token(
        "9a6df3aabc",
        "0x0Ab100518367dba7470fE5B2b403387972d453B4",
        "a2df2ce01d913148bab1aa95d32049227d325db58a0396ae36f88fd1baecd02a",
        "0x4D2f63d6826603B84D12C1C7dd33aB7F3BDe7553",
        "0xbed13479c186003fdf2dfc932c3467e7e4431a0e"
        );
        echo "\r\n res : ".$res."\r\n";*/
		
		 $data["ifi_balance"]=2000;
					  $data["status"]=1;
				      $this->ette_model->update_ifi_award_log(222313,$data);
    }    

    private function cal($amount,$time,$mul){
        $amt_dec = base_convert($amount,16,10);
        $to_dec = $amt_dec*$time/$mul;
        $to_hex = base_convert($to_dec,10,16);
        return $to_hex;
    }
   

     private function send_token1($amount, $from, $privateKey, $contract, $to, $type = 0,$nonce)
    {

        if (substr($to, 0, 2) == "0x") {
            $to = substr($to, 2);
        }
        $funcSelector = "0xa9059cbb";
        // $amt_hex = base_convert($amount,10,16);
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
        // $gas = '0x' . dechex(193334);
        $gas = '0x' . dechex(333334);
        $gasPrice = '0x' . dechex($this->config->item('gas_price'));
        if ($type == 1) {
            $gas = '0x' . dechex(93334);
        }
      //  $nonce = $this->get_nonce($from);
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
        return $out_arr;
    }

    private function send_token($amount, $from, $privateKey, $contract, $to, $type = 0)
    {

        if (substr($to, 0, 2) == "0x") {
            $to = substr($to, 2);
        }
        $funcSelector = "0xa9059cbb";
        // $amt_hex = base_convert($amount,10,16);
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
        // $gas = '0x' . dechex(193334);
        $gas = '0x' . dechex(333334);
        $gasPrice = '0x' . dechex($this->config->item('gas_price'));
        if ($type == 1) {
            $gas = '0x' . dechex(93334);
        }
        $nonce = $this->get_nonce($from);
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
        return $out_arr;
    }

     
	 public function send_token_test()
    {
		  $input_data = json_decode(trim(file_get_contents('php://input')), true);
		  $to=$input_data['to'];
		  $amount=$input_data['amount'];
		 // echo $amount;
		  $amount=$amount."";
		  if(is_string($amount))
		  {
			  echo " amount is string".PHP_EOL;
		  }
        $amount=base_convert($amount,10,16);
		echo $amount.PHP_EOL;
		return;
		$from = $this->config->item("ifiPayAccount");
        $privateKey = decrypt($this->config->item("encrypted_ifi_wallet"));
        $contract = $this->config->item("ifi_contract_address");
		$type = 0;
			
        if (substr($to, 0, 2) == "0x") {
            $to = substr($to, 2);
        }
        $funcSelector = "0xa9059cbb";
        // $amt_hex = base_convert($amount,10,16);
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
        // $gas = '0x' . dechex(193334);
        $gas = '0x' . dechex(333334);
        $gasPrice = '0x' . dechex($this->config->item('gas_price'));
        if ($type == 1) {
            $gas = '0x' . dechex(93334);
        }
        $nonce = $this->get_nonce($from);
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
       echo $out_arr;
    }

        
        
        private function toWei($value,$dec) {
            $float = 0;
            switch(intval($dec)){
                case 1:
                    $float = $value*(1.0E+1);
                    break;
                case 2:
                    $float = $value*(1.0E+2);
                    break;
                case 3:
                    $float = $value*(1.0E+3);
                    break;
                case 4:
                    $float = $value*(1.0E+4);
                    break;  
                case 5:
                    $float = $value*(1.0E+5);
                    break;
                case 6:
                    $float = $value*(1.0E+6);
                    break;
                case 7:
                    $float = $value*(1.0E+7);
                    break;
                case 8:
                    $float = $value*(1.0E+8);
                    break;
                case 9:
                    $float = $value*(1.0E+9);
                    break;
                case 10:
                    $float = $value*(1.0E+10);
                    break;
                case 11:
                    $float = $value*(1.0E+11);
                    break;
                case 12:
                    $float = $value*(1.0E+12);
                    break;  
                case 13:
                    $float = $value*(1.0E+13);
                    break;
                case 14:
                    $float = $value*(1.0E+14);
                    break;
                case 15:
                    $float = $value*(1.0E+15);
                    break;
                case 16:
                    $float = $value*(1.0E+16);
                    break; 
                case 17:
                    $float = $value*(1.0E+17);
                    break;
                case 18:
                    $float = $value*(1.0E+18);
                    break;
                default:
                    $float = $value*(1.0E+1);
                    break;
            }
            return number_format($float,0,'.','');
        }
		
		 function get_location($local_ip)
          {
            $location='';
            $url="https://api.ip138.com/ip/?ip=".$local_ip."&datatype=jsonp&token=85223d293701aea82a5e4faeb502fb9e";
            $result=geturl($url);
            
            if($result!='')
            {   
              $arr = json_decode($result,true);
			  if($arr["ret"]=='ok')
			  {
              if($arr["data"][0]=='中国'&&$arr["data"][2]!='')
              {
              $city_CN=$arr["data"][2];           
              $arr=$this->ette_model->get_city($city_CN);
              if($arr!=''){
              $city_EN=$arr[0]["City_EN"];
              $location=$city_EN.", China";
              }
              }
			  }

            }
            if($location=="")
            {
         

            $url="http://ip-api.com/json/".$local_ip;//."?lang=zh-CN";
         // $result = commit_curl($url);
            $result=geturl($url);
            if($result!='')
            {   

                $arr = json_decode($result,true);
                if($arr["country"]!=''&&$arr["city"]!='')
                {
                 $location=$arr["city"].", ".$arr["country"];

                }

            }
            if($location=="")
            {  

            $url = "http://api.ipstack.com/".$local_ip."?access_key=b110acacd3a7ce2b43d72ed401e6a5c9&format=1";//"?access_key=ce28c8d21809d0498fb2176b95addb7b&format=1";
       //   $result = commit_curl($url);
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
            }
            return $location;
        }
		
	 public function get_trx($hash) {
            $method  = "eth_getTransactionByHash";
            $param = [$hash];
            $result = $this->call($method,$param);
            return $result;
        }
		
    public function get_trxR($hash) {
            $method  = "eth_getTransactionReceipt";
            $param = [$hash];
            $result = $this->call($method,$param);
            return $result;
        }	

}


