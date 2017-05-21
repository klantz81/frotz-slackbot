<?php

require_once("vendor/autoload.php");


use Mpociot\BotMan\BotManFactory;
use React\EventLoop\Factory;
use Mpociot\BotMan\Interfaces\StorageInterface;


const SLEEP_USEC = 100000;
const SLACK_TOKEN = "SLACK_TOKEN";
const BOT_ID = "<@BOT_ID>";
const PIPES_DESC = array(
   0 => array("pipe", "r"),
   1 => array("pipe", "w"),
   2 => array("file", "error.txt", "a")
);
const PROCESS = "frotz -p -d hhgg.z5";


class MyStorage implements StorageInterface {
        private $data;
    
        public function __construct() {
                $this->data = array();
        }

        public function save(array $data, $key) {
                $this->data[$key] = $data;
        }
        public function get($key) {
                return isset($this->data[$key]) ? $this->data[$key] : null;
        }

        public function delete($key) {
                if (isset($this->data[$key]))
                        unset($this->data[$key]);
        }

        public function all() {
                return $this->data;
        }
}


$loop = Factory::create();
$botman = BotManFactory::createForRTM(['slack_token' => SLACK_TOKEN], $loop, null, new MyStorage());


$botman->hears('{cmd}', function($bot, $cmd) {
        echo $cmd."\n";

        if (stristr($cmd, BOT_ID) === FALSE)
                return;


        $data = $bot->channelStorage()->get();

        if (isset($data['process']) && $cmd == "restart") {

                $status = proc_get_status($data['process']);
                exec("kill -9 {$status['pid']}");

                fclose($data['pipes'][0]);
                fclose($data['pipes'][1]);
                proc_close($data['process']);

                $process = proc_open(PROCESS, PIPES_DESC, $pipes, ".");
                stream_set_blocking($pipes[1], false);

                $bot->channelStorage()->save(array("process"=>$process, "pipes"=>$pipes));

        } else if (isset($data['process'])) {
                fwrite($data['pipes'][0], $cmd."\n", strlen($cmd."\n"));
                fflush($data['pipes'][0]);

        } else {
                $process = proc_open(PROCESS, PIPES_DESC, $pipes, ".");
                stream_set_blocking($pipes[1], false);

                $bot->channelStorage()->save(array("process"=>$process, "pipes"=>$pipes));

        }



        $data = $bot->channelStorage()->get();


        for ($i = 0; $i < 10; $i++) {
                usleep(SLEEP_USEC);
                $res = stream_get_contents($data['pipes'][1]);
                $res = preg_replace("!\e\[?.*?[\@-~]!", "", $res);
		if (strlen($res) > 0)
			break;
	}

	echo $cmd."\n".$res."\n\n";

	if (strlen($res) > 0)
		$bot->reply($res);

});


$loop->run();
