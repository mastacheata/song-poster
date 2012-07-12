<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class OAuthConsumer
{
	public $key;
	public $secret;

	function __construct($key, $secret, $callback_url=NULL) {
		$this->key = $key;
		$this->secret = $secret;
		$this->callback_url = $callback_url;
	}

	function __toString() {
		return "OAuthConsumer[key=$this->key,secret=$this->secret]";
	}
}

/* End of File: oauthconsumer.php */
/* Location: ./application/libraries/oauthconsumer.php */