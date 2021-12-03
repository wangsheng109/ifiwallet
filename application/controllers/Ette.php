<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Ette extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->load->model('ette_model');
                $this->load->model('coin_model');
                $this->rpc_url = $this->config->item('ifiRPC');
                $this->coin_name = "ifi";
                $this->coin_id = $this->config->item('ifi_coin_id');
                $this->chain_id = $this->config->item('ifi_chain_id');
        }

        
        public function test() {
            $result = $this->ette_model->get_test();
            $arr = array();
            foreach($result as $v){
                $v['extra'] = unpack("cchars/nint", $v['extraData']);
                $arr[] = $v;
            }
            var_dump($arr);
        }

        public function correct_from() {
            $source = $this->ette_model->get_wrong_txs();
            $arr = array();
            foreach($source as $v){
                $a = $this->get_trx($v['hash']);
                $arr[] = $a;
            }
            foreach($arr as $v){
                $data['from'] = $v['from'];
                $where['hash'] = $v['hash'];
                $this->ette_model->update_tx_by_hash($data,$where);
                echo "\r\n update tx ".$v['hash']." with new from: ".$v['from']."\r\n";
            }
            
        }

        // correct tx's blocknumber and timestamp
        public function correct_blocknumber() {
            $source = $this->ette_model->get_wrong_bn_txs();
            $arr = array();
            foreach($source as $v){
                $a = $this->get_trx($v['hash']);
                $arr[] = $a;
            }
            foreach($arr as $v){
                $blockNumber = base_convert($v['blockNumber'],16,10);
                $block = $this->get_block($blockNumber);
                $data['blockNumber'] = $blockNumber;
                $data['timestamp'] = base_convert($block['timestamp'],16,10);
                
                $where['hash'] = $v['hash'];
                $this->ette_model->update_tx_by_hash($data,$where);
                echo "\r\n update tx ".$v['hash']." with new blocknumber: ".$data['blockNumber']." and timestamp: ".$data['timestamp']."\r\n";
                
            }
        }

        public function correct_block() {
            $check_block = $this->ette_model->next_check_block();
            if(!is_numeric($check_block)){
                var_dump($check_block);
                exit;
            }
            $block_num = intval($check_block);
            $best_block = $this->get_best_block();
            $final_block = ($block_num+50>$best_block)?$best_block:$block_num+50;
            echo "\r\n check from the block number :".$block_num." with later 50 ones\r\n";
            for($i=$block_num;$i<$final_block;$i++){
                $block = $this->get_block($i);
                $blockNum = base_convert($block['number'],16,10);
                echo "\r\n get block with number : ".$blockNum." \r\n";
                // check transactions in block :
                if(count($block['transactions'])>0){
                    $this->correct_txs_in_block($block['transactions'],$block['timestamp'],$i);
                }
                // check block from database
                $has_block = $this->ette_model->has_block($blockNum);
                echo "\r\n has block result : ".$has_block." \r\n";
                if(strlen($has_block)<4) {
                    echo "\r\n this block is not in db, need to insert new";
                    //insert new block into db
                    $data = array(
                        'hash' => $block['hash'],
                        'number' => $blockNum,
                        'time'  =>  base_convert($block['timestamp'],16,10),
                        'parenthash'    =>  $block['parentHash'],
                        'difficulty'    =>  base_convert($block['difficulty'],16,10),
                        'gasused'   =>  base_convert($block['gasUsed'],16,10),
                        'gaslimit'  =>  base_convert($block['gasLimit'],16,10),
                        'nonce' =>  base_convert($block['nonce'],16,10),
                        'miner' =>  $this->get_signers($blockNum),  // TO DO, in POA it is signer
                        'size'  =>  base_convert($block['size'],16,10),
                        'stateroothash' =>  $block['stateRoot'],
                        'unclehash' =>  $block['sha3Uncles'],
                        'txroothash'  => $block['transactionsRoot'], 
                        'receiptroothash'   =>  $block['receiptsRoot'],
                        'inputdata' =>  $block['extraData'],
                        'tx_num'    =>  count($block['transactions'])
                    );
                    $this->ette_model->insert_block($data);
                    echo "\r\n block with hash ".$block['hash']." inserted to db\r\n";
                } else {
                    echo "\r\n this block is in db, just need to check hash";
                    if($has_block !== $block['hash']) {
                        $blockUpdate['hash'] = $block['hash'];
                        $blockUpdate['tx_num'] = count($block['transactions']);
                        $blockUpdate['miner'] = $this->get_signers($blockNum);
                        $blockWhere['number'] = $blockNum;
                        $this->ette_model->update_block($blockUpdate,$blockWhere);
                        echo "\r\n block with hash ".$block['hash']." updated to db\r\n";
                    } else {
                        $blockUpdate['miner'] = $this->get_signers($blockNum);
                        $this->ette_model->update_block($blockUpdate,$blockWhere);
                        echo "\r\n block with hash ".$block['hash']." in db is correct, just update the miner \r\n";
                    }
                    
                }
                
            }
            $new_var['value'] = $final_block;
            $where['name'] = "next_check_block";
            $this->ette_model->update_config_vars($new_var,$where);
        }

        private function correct_txs_in_block($txs=array(),$timestamp,$blockNumber) {
            foreach($txs as $trx) {
                $tx = $trx['hash'];
                $has = $this->ette_model->has_tx($tx);
                if($has<=0){
                    echo "\r\n tx with hash :".$tx." in block ".$blockNumber." does not exists, need to insert";
                    $trx = $this->get_trx($tx);
                    $data = array(
                        'hash'  =>  $tx,
                        'from'  =>  $trx['from'],
                        'to'    =>  $trx['to'],
                        'value' =>  base_convert($trx['value'],16,10),
                        'input_data'    =>  $trx['input'],
                        'gas'   =>  base_convert($trx['gas'],16,10),
                        'gasPrice'  =>  base_convert($trx['gasPrice'],16,10),
                        'cost'  =>  0,
                        'nonce' =>  base_convert($trx['nonce'],16,10),
                        'state' =>  1,
                        'blockhash' =>  $trx['blockHash'],
                        'blockNumber'   =>  $blockNumber,
                        'timestamp' =>  $timestamp
                    );
                    $this->ette_model->insert_transactions($data);
                    echo "\r\n insert transactions successfully with hash : ".$tx;
                }
            }
        }

        public function get_index() {
            $node_number = $this->ette_model->get_node_number();
            $address_number = $this->ette_model->get_address_number();
            $best_block = $this->get_best_block();
            $tx_count = $this->ette_model->get_tx_count();
            $signer_count = $this->ette_model->get_signers_count();
            $init_time = 
            $data = array(
                'block_height'  =>  $best_block,
                'transactions'  =>  $tx_count,
                'signers'   =>  $signer_count,
                'node_machines' =>  $node_number,
                'wallets'   =>  $address_number,
                'tps'   =>  intval($tx_count/(time()-1628779325))
            );

            $this->output->set_header("Access-Control-Allow-Origin: * ");
            $this->output->set_output(json_encode($data,true));
        }

        public function get_transactions() {
            $best_block = $this->get_best_block();
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $max_block = isset($input_data['max_block'])?$input_data['max_block']:$best_block;
            $current_page = isset($input_data['current_page'])?$input_data['current_page']:1;
            $items_per_page = isset($input_data['items_per_page'])?$input_data['items_per_page']:15;
            $res = $this->ette_model->get_trx($max_block,$current_page,$items_per_page);
            $data = $res[1];
            foreach($data as $k => $v) {
                $data[$k]['age'] = time()-$v['timestamp'];
            }
            $result = array(
                'total_records' =>  $res[0],
                'data'  =>  $data
            );
            $this->output->set_header("Access-Control-Allow-Origin: * ");
            $this->output->set_output(json_encode($result,true));
        }
        
        public function decrypt_tool() {
            echo decrypt($this->config->item('test_private_key'))."\r\n";
        }
        
        public function encrypt_tool($privateKey) {
            echo encrypt($privateKey)."\r\n";
        }

        public function get_signers($num) {
            $method  = "clique_getSigners";
            $param = ['0x'.dechex($num)];
            $result = $this->call($method,$param);
            $random_num = rand(0,count($result)-1);
            return $result[$random_num];
        }

        public function update_common_signers() {
            $signers = $this->ette_model->get_signers();
            foreach($signers[1] as $k => $v) {
                $m_block = $this->ette_model->get_signer_m_block($v['address']);
                $data['min_block'] = $m_block->min_number; 
                $data['max_block'] = $m_block->max_number; 
                //get blocks numbers in time period
                $seven_days = $this->ette_model->get_blocks_count_by_singer_time(time()-7*24*3600,$v['address']);
                $one_month = $this->ette_model->get_blocks_count_by_singer_time(time()-30*24*3600,$v['address']);
                $total_blocks = $this->ette_model->get_blocks_count_by_singer_time(0,$v['address']);
                $data['days7'] = $seven_days;
                $data['days30'] = $one_month;
                $data['total_blocks'] = $total_blocks;
                $this->ette_model->update_signers($data,array('address' =>  $v['address']));
                echo "\r\n update signer :". $v['address']."\r\n";
            }
        }

        public function get_common_signers() {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $current_page = isset($input_data['current_page'])?$input_data['current_page']:1;
            $items_per_page = isset($input_data['items_per_page'])?$input_data['items_per_page']:15;
            $res = $this->ette_model->get_signers($current_page,$items_per_page);
            $signers['total_records']   =   $res[0];
            $signers['data']    =   $res[1];
            $this->output->set_header("Access-Control-Allow-Origin: * ");
            $this->output->set_output(json_encode($signers,true));
        }

        //获取区块带分野
        public function get_blocks() {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $current_page = isset($input_data['current_page'])?$input_data['current_page']:1;
            $items_per_page = isset($input_data['items_per_page'])?$input_data['items_per_page']:15;
            $res = $this->ette_model->get_all_blocks($current_page,$items_per_page);
            $all_blocks =   $res[1];
            foreach($all_blocks as $k => $v) {
                $all_blocks[$k]['age'] = time() - $v['time'];
            }
            $data['total_records']  =   $res[0];
            $data['data']   =   $all_blocks;
            $this->output->set_header("Access-Control-Allow-Origin: * ");
            $this->output->set_output(json_encode($data,true));
        }

        //获取节点所签名的区块
        public function get_signed_blocks() {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $address = isset($input_data['address'])?$input_data['address']:"0x";
            $current_page = isset($input_data['current_page'])?$input_data['current_page']:1;
            $items_per_page = isset($input_data['items_per_page'])?$input_data['items_per_page']:15;
            $res = $this->ette_model->get_signed_blocks($current_page,$items_per_page,$address);
            $signed_blocks = $res[1];
            foreach($signed_blocks as $k => $v) {
                $signed_blocks[$k]['age'] = time() - $v['time'];
            }
            $data = array(
                'total_records' =>  $res[0],
                'data'  =>  $signed_blocks
            );
            $this->output->set_header("Access-Control-Allow-Origin: * ");
            $this->output->set_output(json_encode($data,true));
        }

        //获取节点交易
        public function get_address_trx() {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $address = isset($input_data['address'])?$input_data['address']:"0x";
            $current_page = isset($input_data['current_page'])?$input_data['current_page']:1;
            $items_per_page = isset($input_data['items_per_page'])?$input_data['items_per_page']:15;
            $res = $this->ette_model->get_add_trx($current_page,$items_per_page,$address);
            $add_trx    =   $res[1];
            foreach($add_trx as $k => $v) {
                $add_trx[$k]['age'] = time() - $v['timestamp'];
            }
            $data = array(
                "total_records" =>  $res[0],
                "data"  =>  $add_trx
            );
            $this->output->set_header("Access-Control-Allow-Origin: * ");
            $this->output->set_output(json_encode($data,true));
        }

        //获取地址类型
        public function get_address_type()  {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $address = isset($input_data['address'])?$input_data['address']:"0x";
            $is_s = $this->ette_model->check_s($address);
            $data = array();
            if($is_s) {
                $data['type']   =   's';
                
            } else {
                $is_c = $this->ette_model->check_n($address);
                if($is_c) {
                    $data['type']   =   'c';
                } else {
                    $data['type']   =   'n';
                }
            }
            $this->output->set_header("Access-Control-Allow-Origin: * ");
            $this->output->set_output(json_encode($data,true));
        }

        //获取节点位置
        public function get_nodes_location() {
            $input_data = json_decode(trim(file_get_contents('php://input')), true);
            $current_page = isset($input_data['current_page'])?$input_data['current_page']:1;
            $items_per_page = isset($input_data['items_per_page'])?$input_data['items_per_page']:15;
            $res = $this->ette_model->get_nodes($current_page,$items_per_page);
            $nodes = $res[1];
            foreach($nodes as $k => $v) {
                $local_ip = $v['local_ip'];
                $url = "http://api.ipstack.com/".$local_ip."?access_key=f03837ea28b5f80f3229d75382fec415&format=1";
                $result = commit_curl($url);
                $arr = json_decode($result,true);
                $nodes[$k]['latitude'] = $arr['latitude'];
                $nodes[$k]['longitude'] = $arr['longitude'];
            }
            $data['total_records']  =   $res[0];
            $data['data']   =   $nodes;
            $this->output->set_header("Access-Control-Allow-Origin: * ");
            $this->output->set_output(json_encode($data,true));
        }

        //更新节点数据
        public function update_nodes_info() {
            $nodes = $this->ette_model->get_nodes();
            foreach($nodes as $v) {
                $total_award = $this->ette_model->get_award_by_node_time(0,$v['owner_address']);
                $day30_award = $this->ette_model->get_award_by_node_time(time()-30*24*3600,$v['owner_address']);
                $day60_award = $this->ette_model->get_award_by_node_time(time()-60*24*3600,$v['owner_address']);
                $last30_award = $day60_award - $day30_award;
                $increase_ratio =   0;
                if($last30_award == 0) {
                    $increase_ratio = 100;
                } else {
                    $increase_ratio = 100*($day30_award - $last30_award)/$last30_award;
                }
                // update nodes information
                $data = array(
                    'total_reward'  =>  $total_award,
                    'reward_30days' =>  $day30_award,
                    'increase_ratio'   =>  $increase_ratio
                );
                $this->ette_model->update_node($data,array('owner_address'=>$v['owner_address']));
                echo "\r\n Update node information on onwer address : ".$v['owner_address']."\r\n";
            }
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

        public function get_best_block() {
            $method  = "eth_blockNumber";
            $param = [];
            $result = $this->call($method,$param);
            if(is_array($result)){
                exit("\r\n error when getting nonce \r\n");
            }
            $count = base_convert($result,16,10);
            // echo "\r\n best block : ".$count."\r\n";
            return $count;
        }

        public function get_trx($hash) {
            $method  = "eth_getTransactionByHash";
            $param = [$hash];
            $result = $this->call($method,$param);
            return $result;
        }

        public function get_block($num) {
            $method  = "eth_getBlockByNumber";
            $hex_num = "0x".base_convert($num,10,16);
            $param = [$hex_num,true];
            $result = $this->call($method,$param);
            return $result;
        }


        private function get_balance($address) {
            $method="eth_getBalance";
            $params=[$address,"latest"];
            $result = hexdec($this->call($method,$params));
            return $result;
        }


        private function toWei($value) {
            $float = floatval($value*(1.0E+18));
            return number_format($float,0,'.','');
        }
        
        private function toEther($value) {
            $float = $value/(1.0E+18);
            return number_format($float,4,'.','');
        }

        private function ethSynced() {
            $method1  = "eth_syncing";
            $out_arr1 = $this->call($method1);
            echo "print eth syncing: \r\n";
            var_dump($out_arr1);
            $method2  = "eth_blockNumber";
            $out_arr2 = $this->call($method2);
            $num = hexdec($out_arr2);
            echo "print block number: \r\n";
            var_dump($num);
            if(!$out_arr1&&is_numeric($num)&&$num>0){
                return $num;
            } else {
                return false;
            }
        }
}


