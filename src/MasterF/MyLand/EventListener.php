<?php

namespace MasterF\MyLand;

use pocketmine\block\Sapling;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Event;
use pocketmine\event\Listener;

class EventListener implements Listener {

    private $main;

    public function __construct(MyLand $main) {
        $this->main = $main;
    }

    public function onBreak(BlockBreakEvent $ev) {
        $this->onEvent($ev);
    }

    public function onPlace(BlockPlaceEvent $ev) {
        $this->onEvent($ev);
    }

    public function onEvent(Event $ev) {
        $block = $ev->getBlock();
        $player = $ev->getPlayer();
        $world = $player->getLevel()->getName();

        if(!$this->main->isEditable([intval($block->x), intval($block->z)], $world, $player)) {
            $ev->setCancelled();
            $player->sendPopup($this->main->getMessage("land.no-editable"));
        }

    }

}
