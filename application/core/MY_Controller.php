<?php

class MY_Controller extends CI_Controller {
        
        protected $rpc_url = "";
        protected $rpc_url_local = "";
        protected $rpc_user = null;
        protected $rpc_pass = null;
        protected $coin_name = "";
        protected $coin_id = 0;
        protected $chain_id = 0;
        protected $token_id = 0;
        public function __construct()
        {
                parent::__construct();
                $this->load->model('Coin_model');
                $this->load->helper('url_helper');
        }
        
        protected function call($method, $params=NULL) {
            if(empty($params)){
                $odata = [
                "jsonrpc"=> "2.0",
                "method"=> $method,
                'id' =>time()
                ];
            } else {
                $odata = [
                "jsonrpc"=> "2.0",
                "method"=> $method,
                "params"=> $params,
                'id' =>time()
                ];
            }
            $out = commit_curl($this->rpc_url,false,1,$odata,$this->rpc_user,$this->rpc_pass);
            if(isset(json_decode($out,true)['result'])){
                return json_decode($out,true)['result'];
            } else {
                if(isset(json_decode($out,true)['error'])){
                    $error = json_decode($out,true)['error'];
                    $error['method'] = $method;
                    $error['when'] = date('Y-m-d H:i:s');
                    var_dump($error);
                    return $error;
                }
                
            }
        }
        
        protected function callLocal($method, $params=NULL) {
            if(empty($params)){
                $odata = [
                "jsonrpc"=> "2.0",
                "method"=> $method,
                'id' =>time()
                ];
            } else {
                $odata = [
                "jsonrpc"=> "2.0",
                "method"=> $method,
                "params"=> $params,
                'id' =>time()
                ];
            }
            $out = commit_curl($this->rpc_url_local,false,1,$odata,$this->rpc_user,$this->rpc_pass);
            if(isset(json_decode($out,true)['result'])){
                return json_decode($out,true)['result'];
            } else {
                if(isset(json_decode($out,true)['error'])){
                    $error = json_decode($out,true)['error'];
                    $error['method'] = $method;
                    $error['when'] = date('Y-m-d H:i:s');
                    var_dump($error);
                }
                return null;
            }
        }
        
        protected function callApi($url,$odata) {
            $output = commit_curl($url,false,1,$odata);
            return json_decode($output,true);
        }


        protected function get_chain_id($coin_id) {
            
            $chain_id = $this->Coin_model->get_chain_id($coin_id);
            return $chain_id;
        }
    
}

