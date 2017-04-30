<?php

namespace MasterF\MyLand;

# Base #
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

# Commands #
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

# File IO #
use pocketmine\utils\Config;
use PocketMoney\PocketMoney;


class MyLand extends PluginBase {

    /** @var $instance MyLand this plugin */
    static $instance = null;

    /** @var $db SQLite3DataProvider */
    private $db;

    /** @var $worldProtect String[] */
    private $worldProtect = [];

    private $setPos = [];

    private $messages = [];

    private $landPrice = 0;

    /** @var  $pm PocketMoney */
    private $pm;

    /**********
     *   API   *
     **********/
    /**
     * get Instance
     * @return MyLand
     */
    public static function getInstance() {
        return self::$instance;
    }

    public function getLand($x, $z, $world) {
        return $this->db->getLand($x, $z, $world);
    }

    public function existsLand($x, $z, $world) {
        return $this->db->existsLand($x, $z, $world);
    }


    public function createLand($owner, $start, $end, $world) {
        $this->db->createLand(strtolower($owner), $start[0], $start[1], $end[0], $end[1], $world);
    }

    /**
     * @param array $xz
     * @param String $world
     * @param Player $player
     * @return bool
     */
    public function isEditable(array $xz, String $world, Player $player) {
        if($player->isOp()) return true; //OPかどうか.

        $land = $this->getLand($xz[0], $xz[1], $world);

        if($land === null) { //(土地保護がない場合に)プロテクチョがかかってるかどうか.
            return !$this->isWorldProtect($world);
        }

        $name = strtolower($player->getName());

        if ($land["owner"] === $name) { //オーナーかどうか.
            return true;
        }

        return $this->db->existsGuest($land["id"], $name); //ゲストさんかどうか.
    }

    /**
     * @param String $world
     * @return mixed
     */
    public function isWorldProtect(String $world) {
        return array_search($world, $this->worldProtect);
    }

    /**********
     *  NOAPI  *
     **********/

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {

        if(!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }

        $this->db = new SQLite3DataProvider($this->getDataFolder());
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        $this->saveResource("Config.yml");
        $this->saveResource("Message.yml");

        $config = new Config($this->getDataFolder()."Config.yml");
        $this->worldProtect = $config->get("worldprotect");
        $this->landPrice = $config->get("landprice");

        $msg    = new Config($this->getDataFolder()."Message.yml");
        $this->messages = $this->parseMessages($msg->getAll());

        $this->pm = $this->getServer()->getPluginManager()->getPlugin("PocketMoney");

        if($this->pm === null) {
            $this->getLogger()->alert($this->getMessage("server.error1"));
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }

    }

    /**
     * @param CommandSender $sender
     * @param Command $cmd
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {

        if(!count($args) > 0 or !$sender instanceof Player) {
            $this->helpMsg($sender);
            return true;
        }

        $name = strtolower($sender->getName());

        switch($args[0]) {
            case "start":
                $x = intval($sender->x);
                $z = intval($sender->z);
                $this->setPos[$name][0] = [$x, $z];

                $sender->sendMessage($this->getMessage("player.PositionSet", [1, $x, $z]));
                if(!empty($this->setPos[$name]) and count($this->setPos[$name]) >= 2) {
                    $price = $this->calculateLandPrice($name);
                    $sender->sendMessage($this->getMessage("player.landPrice", [$price]));
                }

                return true;

                break;

            case "end":
                $x = intval($sender->x);
                $z = intval($sender->z);
                $this->setPos[$name][1] = [$x, $z];

                $sender->sendMessage($this->getMessage("player.PositionSet", [2, $x, $z]));

                if(!empty($this->setPos[$name]) and count($this->setPos[$name]) >= 2) {
                    $price = $this->calculateLandPrice($name);
                    $sender->sendMessage($this->getMessage("player.landPrice", [$price]));
                }

                return true;
                break;

            case "buy":
                if(empty($this->setPos[$name]) or count($this->setPos[$name]) < 2) {
                    $sender->sendMessage($this->getMessage("player.buy-error.error0"));
                    return true;
                }

                $world = $sender->getLevel()->getName();

                $start[0] = min($this->setPos[$name][0][0], $this->setPos[$name][1][0]);
                $start[1] = max($this->setPos[$name][0][0], $this->setPos[$name][1][0]);
                $end[0] = min($this->setPos[$name][0][1], $this->setPos[$name][1][1]);
                $end[1] = max($this->setPos[$name][0][1], $this->setPos[$name][1][1]);

                if(!$this->checkOverlap($start, $end, $world)) {
                    $price = ($start[1] + 1 - $start[0]) * ($end[1] + 1 - $end[0]) * $this->landPrice;

                    $nameB = $sender->getName();
                    if($this->pm->getMoney($nameB) >= $price) {
                        $this->createLand($name, $start, $end, $world);
                        $sender->sendMessage($this->getMessage("player.buy", [$price]));
                        $this->pm->grantMoney($nameB, $price);
                        return true;
                    } else {
                        $sender->sendMessage($this->getMessage("player.buy-error.error1"));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->getMessage("land.used-place"));
                    return true;
                }
                break;

            case "invite":

                if(count($args) >= 3) {
                    $id = $args[1];
                    $land = $this->db->getLandById($id);

                    if($land !== null) {

                        if($land["owner"] === $name) {
                            $guestName = strtolower($args[2]);

                            if(!$this->db->existsGuest($id, $guestName)) {
                                $sender->sendMessage($this->getMessage("system.invite.msg2", [$guestName, $id]));
                                $player = $this->getServer()->getPlayerExact($guestName);

                                if($player !== null) {
                                    $sender->sendMessage($this->getMessage("system.invite.msg1", [$name, $id]));
                                }

                                $this->db->addGuest($id, $guestName);
                            } else {
                                $sender->sendMessage($this->getMessage("system.invite.error1"));
                            }
                        } else {
                            $sender->sendMessage($this->getMessage("land.error2"));
                        }
                    } else {
                        $sender->sendMessage($this->getMessage("land.error1"));
                    }
                } else {
                    $sender->sendMessage($this->getMessage("cmd.error1"));
                }

                return true;
                break;

            case "here":
                $level = $sender->getLevel();
                $x = intval($sender->x);
                $z = intval($sender->z);
                $world = $level->getName();
                $landId = -1;

                if($this->existsLand($x, $z, $world)) {
                    $land = $this->getLand($x, $z, $world);
                    $landId = $land["id"];
                }

                if($landId === -1) {
                    $sender->sendMessage($this->getMessage("land.error1"));
                } else {
                    $sender->sendMessage($this->getMessage("land.land-number", [$landId]));
                }

                return true;
                break;

            case "help":
                $this->helpMsg($sender);
                break;
        }


    }

    public function checkOverlap($start, $end, $world) {

        foreach($this->db->getAllLands() as $land) {
            if($land["world"] === $world) {
                if(// 内側に土地があったら
                ($land["startx"] >= $start[0] and $land["endx"] <= $end[0] and
                    $land["startz"] >= $start[1] and $land["endz"] <= $end[1])
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getMessage($str, $param = []) {
        $str = $this->messages[$str] ?? $str;

        foreach($param as $key => $val) {
            $str = preg_replace("/{%$key}/", $val, $str);
        }

        return $str;
    }


    public function parseMessages($message) {
        $result = [];
        foreach($message as $key => $msg) {

            if(is_array($msg)) {

                foreach($this->parseMessages($msg) as $key2 => $msg2) {
                    $result[$key . "." . $key2] = $msg2;
                }
            } else {
                $result[$key] = $msg;
            }
        }

        return $result;

    }

    /**
     * @param String $name
     * @return int
     */
    public function calculateLandPrice(String $name) {
        $start[0] = min($this->setPos[$name][0][0], $this->setPos[$name][1][0]);
        $start[1] = max($this->setPos[$name][0][0], $this->setPos[$name][1][0]);
        $end[0] = min($this->setPos[$name][0][1], $this->setPos[$name][1][1]);
        $end[1] = max($this->setPos[$name][0][1], $this->setPos[$name][1][1]);
        return ($start[1] + 1 - $start[0]) * ($end[1] + 1 - $end[0]) * $this->landPrice;

    }

    public function helpMsg(CommandSender $sender)
    {
        $messages = ["help0", "help1", "help2", "help3", "help4", "help5"];

        foreach($messages as $msg) {
            $sender->sendMessage($this->getMessage("cmd." . $msg));
        }
    }
}
