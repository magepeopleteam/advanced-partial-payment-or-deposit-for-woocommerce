<?php

/* Mage Log Class */

if( !class_exists('Mep_Log') ) {

    class Mep_log
    {
        public $path;
        public $name;

        public function __construct()
        {
            $this->name = 'partial_log.txt';
            $this->path = WCPP_PLUGIN_DIR . 'partial_log.txt';
        }

        public function open() 
        {
            $file = fopen($this->path . $this->name, 'a');

            return $file;
        }

        public function log($str, $fun = false)
        {
            if(!$str) return;
            
            $dateTime = current_datetime()->format('Y-m-d H:i:s A');

            $str = $str ? $dateTime. ':: ' . $str . PHP_EOL : '';
            if($fun) {
                $str .= 'Called from ::' . $fun . PHP_EOL;
            }
            $str .= '********************************************************************' . PHP_EOL;
            
            $file = $this->open();

            fwrite($file, $str);

            $this->close($file);
        }

        public function close($file) 
        {
            fclose($file);
        }
    }

}