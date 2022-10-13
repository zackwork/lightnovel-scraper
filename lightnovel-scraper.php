<?php

    /*
    Plugin Name: LightNovel Scraper
    Plugin URI: 
    Description: Automatic Scrap Light Novels & Web Novel into madara theme from online source.
    Version: 1.0.0
    Author: ZackSnyder
    Author URI: 
    License: 
    */

    if(!defined('WP_LNS_PATH')){
        define('WP_LNS_PATH', plugin_dir_path(__FILE__) );
    }
    if(!defined('WP_LNS_URL')){
        define('WP_LNS_URL', plugin_dir_url(__FILE__) );
    }
    

    class LightNovel_Initiator{
        public function __construct()
        {
            $this->hooks();
            $this->init();
        }

        private function hooks(){
            add_action('admin_menu', array($this, 'lightnovel_admin_menu_option') );
            
            
        }
        private function init(){
            include WP_LNS_PATH . "vendor/autoload.php";
            include WP_LNS_PATH . "workers/workers.php";   
        }
        

        public function lightnovel_admin_menu_option(){
            
            add_menu_page(
                'Light Novel Scraper',
                'Light Novel Scraper', 
                'manage_options', 
                'lightnovel-scraper', 
                array($this, 'lightnovel_scraper'), 
            'dashicons-screenoptions', 2);
            
            add_submenu_page('lightnovel-settings', 'Light Novel Scraper', 'Light Novel Scraper',
             'manage_options', 'lightnovel-scraper', array($this, 'lightnovel_scraper'), 1);
            
        }

        public function lightnovel_scraper(){        
            include WP_LNS_PATH . "template/lightnovel.php";
        }
    }

    $LightNovel_Initiator = new LightNovel_Initiator();
?>