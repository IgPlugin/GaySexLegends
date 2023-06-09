<?php

declare(strict_types=1);

namespace JustTal\SexMod;

use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

// Thanks to SimpleLay by brokiem. This version is slightly modified
// http://github.com/brokiem/SimpleLay
class LayingEntity extends Human {

	public function __construct(Location $location, Skin $skin, ?CompoundTag $nbt = null, private ?Player $player = null) {
		parent::__construct($location, $skin, $nbt);
	}

	public function onUpdate(int $currentTick): bool {
		if ($this->isFlaggedForDespawn() || $this->player === null) {
			return false;
		}

		$this->getArmorInventory()->setContents($this->player->getArmorInventory()->getContents());
		$this->getInventory()->setHeldItemIndex($this->player->getInventory()->getHeldItemIndex());
		return true;
	}

	public function attack(EntityDamageEvent $source): void {
	}
}
