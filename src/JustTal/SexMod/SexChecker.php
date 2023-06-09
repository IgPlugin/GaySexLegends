<?php

declare(strict_types=1);

namespace JustTal\SexMod;

use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskScheduler;

class SexChecker extends Task {

	private $player;

	private $scheduler;

	public function __construct(Player $player, TaskScheduler $scheduler) {
		$this->player = $player;
		$this->scheduler = $scheduler;
	}

	public function onRun(): void {
		if (!$this->player->isOnline()) {
			unset(Main::$sexing[$this->player->getName()]);
			throw new CancelTaskException();
			return;
		}
		$pos = $this->player->getPosition()->subtract(0, -0.5, 0);
		$this->checkBlock($this->player->getWorld()->getBlockAt((int) $pos->getX(), (int) $pos->getY(), (int) $pos->getZ(), false, false));
		$this->checkBlock($this->player->getWorld()->getBlockAt((int) $pos->getX(), (int) ($pos->getY() - 0.5), (int) $pos->getZ(), false, false));
	}

	public function checkBlock(Block $block) {
		if ($block == null) {
			return;
		}
		if ($block instanceof Bed) {
			if (in_array($this->player->getName(), Main::$sexing)) {
				return;
			}
			foreach ($this->player->getWorld()->getNearbyEntities($this->player->getBoundingBox()) as $entity) {
				if ($entity instanceof Player) {
					if ($entity === $this->player) {
						continue;
					}
					$otherPlayer = $entity;
					Main::$sexing[$this->player->getName()] = $this->player->getName();
					Main::$sexing[$otherPlayer->getName()] = $otherPlayer->getName();
					if (!$block->isHeadPart()) {
						$block = $block->getOtherHalf();
					}
					Main::getInstance()->setLay($otherPlayer, $block->getPosition()->asVector3());
					$this->scheduler->scheduleRepeatingTask(new SexingTask($this->player, $otherPlayer, $this->scheduler), 1);
					$this->player->setNoClientPredictions(true);
					$packet = AnimateEntityPacket::create(
						animation: "animation.player.sexing",
						nextState: "",
						stopExpression: "",
						stopExpressionVersion: 0,
						controller: "",
						blendOutTime: 0.0,
						actorRuntimeIds: [$this->player->getId()]
					);
					NetworkBroadcastUtils::broadcastPackets($this->player->getServer()->getOnlinePlayers(), [$packet]);

					break;
				}
			}
		}
	}
}
