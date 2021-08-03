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
                $this->ette_model->update_from_by_hash($data,$where);
                echo "\r\n update tx ".$v['hash']." with new from: ".$v['from']."\r\n";
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
            $final_block = ($block_num+500>$best_block)?$best_block:$block_num+500;
            echo "\r\n check from the block number :".$block_num." with later 500 ones\r\n";
            for($i=$block_num;$i<$final_block;$i++){
                $block = $this->get_block($i);
                $blockNum = base_convert($block['number'],16,10);
                echo "\r\n get block with number : ".$blockNum." \r\n";
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
                        'miner' =>  '0x0000000000000000000000000000000000000000',  // TO DO, in POA it is signer
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
                        $blockHash['hash'] = $block['hash'];
                        $blockWhere['number'] = $blockNum;
                        $this->ette_model->update_block_hash($blockHash,$blockWhere);
                        echo "\r\n block with hash ".$block['hash']." updated to db\r\n";
                    } else {
                        echo "\r\n block with hash ".$block['hash']." in db is correct, nothing to do\r\n";
                    }
                    
                }
                
            }
            $new_var['value'] = $final_block;
            $where['name'] = "next_check_block";
            $this->ette_model->update_config_vars($new_var,$where);
        }
        
        
        public function decrypt_tool() {
            echo decrypt($this->config->item('test_private_key'))."\r\n";
        }
        
        public function encrypt_tool($privateKey) {
            echo encrypt($privateKey)."\r\n";
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


