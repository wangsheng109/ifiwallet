<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class IPFS extends MY_Controller {
    
        public function __construct()
        {
                parent::__construct();
                $this->rpc_url = "http://localhost:5001";
        }
        
        
}


