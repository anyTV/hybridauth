<?php
	#AUTOGENERATED BY HYBRIDAUTH 2.0.9 INSTALLER - Sunday 20th of November 2011 08:14:51 PM
 
// ----------------------------------------------------------------------------------------
//	HybridAuth Config file: http://hybridauth.sourceforge.net/userguide/Configuration.html
// ----------------------------------------------------------------------------------------

return 
	array(
		"base_url"       => "http://localhost/hybridauth/2.0.9/hybridauth/", 

		"providers"      => array ( 
			// openid providers
			"OpenID" => array (
				"enabled" => true
			),

			"Google" => array ( 
				"enabled" => true,
				"keys"    => array ( "id" => "986822214453.apps.googleusercontent.com", "secret" => "6igxD8K8GfRr6YbbwWaHjBMq" ),
				"scope"   => ""
			),

			"Yahoo"  => array ( 
				"enabled" => true 
			),

			"Facebook" => array ( 
				"enabled" => true,
				"keys"    => array ( "id" => "185915804256", "secret" => "e23fb1aae9fa44b27113284a7c6a49d7" ),
				"scope"   => ""
			),

			"Twitter" => array ( 
				"enabled" => true,
				"keys"    => array ( "key" => "3Xq2hqLhP6lTU2Qh0RUeA", "secret" => "ugsQgG9d5Mh1IIZygtrpRcmwNSiuyT7giVdDqHLA" ) 
			),

			// windows live
			"Live"    => array ( 
				"enabled" => true,
				"keys"    => array ( "id" => "000000004005E70C", "secret" => "XMcnx1G1GvKLZEIPjXxulSKakpn8pgzj" ) 
			),

			"MySpace" => array ( 
				"enabled" => true,
				"keys"    => array ( "key" => "c85b177d77d84c57a7f7d83d65db8015", "secret" => "e1c47b515c31436ab1752ba58a02a0fb7d581a531667441c99c299addcb90e25" ) 
			),

			"LinkedIn" => array ( 
				"enabled" => true,
				"keys"    => array ( "key" => "QC_e8WQKkEuQ4G8BK3Cpuu3sOZwynbfbdGD8PBh7uh6qEUN-aslbDyOR8he9GAyJ", "secret" => "Yl5ZluTn2jw2qwhtsdGmRaprGzFzyIAnse8EpWH0fU_2e7bPj05idxum8mRZPEzI" ) 
			),
		),

		// if you want to enable logging, set 'debug_mode' to true  then provide a writable file by the web server on "debug_file"
		"debug_mode"            => false,

		"debug_file"            => "",
	);