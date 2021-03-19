<?php

declare(strict_types=1);

namespace JustTal\SexMod;

use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;

// Thanks to SimpleLay by brokiem. This version is slightly modified
// http://github.com/brokiem/SimpleLay
class LayingEntity extends Human {

    private $player;
    
    public function __construct(Level $level, CompoundTag $nbt, Player $player) {
        parent::__construct($level, $nbt);
        $this->player = $player;
        $this->setCanSaveWithChunk(false);
    }

    public function onUpdate(int $currentTick): bool {
        if ($this->isFlaggedForDespawn()) {
            return false;
        }

        if (!$this->player->isOnline()) {
            $this->flagForDespawn();
            return false;
        }

        $this->getArmorInventory()->setContents($this->player->getArmorInventory()->getContents());
        $this->getInventory()->setContents($this->player->getInventory()->getContents());
        $this->getInventory()->setHeldItemIndex($this->player->getInventory()->getHeldItemIndex());
        return true;
    }

    public function attack(EntityDamageEvent $source): void {
        // do nothing
    }

}