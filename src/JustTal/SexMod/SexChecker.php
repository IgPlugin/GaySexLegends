<?php

namespace JustTal\SexMod;

use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskScheduler;

class SexChecker extends Task {

    private $player;

    private $scheduler;

    public function __construct(Player $player, TaskScheduler $scheduler) {
        $this->player = $player;
        $this->scheduler = $scheduler;
    }

    public function onRun(int $currentTick) {
        if (!$this->player->isOnline()) {
            unset(Main::$sexing[$this->player->getName()]);
            $this->scheduler->cancelTask($this->getTaskId());
            return;
        }
        $pos = $this->player->getPosition()->subtract(0, -0.5);
        $this->checkBlock($this->player->getLevel()->getBlockAt($pos->getX(), $pos->getY(), $pos->getZ(), false, false));
        $this->checkBlock($this->player->getLevel()->getBlockAt($pos->getX(), $pos->getY() - 0.5, $pos->getZ(), false, false));
    }

    public function checkBlock(Block $block) {
        if ($block == null) {
            return;
        }
        if ($block instanceof Bed) {
            if (in_array($this->player->getName(), Main::$sexing)) {
                return;
            }
            foreach ($this->player->getLevel()->getNearbyEntities($this->player->getBoundingBox()) as $entity) {
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
                    Main::setLay($otherPlayer, $block);
                    $this->scheduler->scheduleRepeatingTask(new SexingTask($this->player, $otherPlayer, $this->scheduler), 1);
                    $this->player->setImmobile(true);
                    $packet = AnimateEntityPacket::create("animation.player.sexing", "", "", "", 0, [$this->player->getId()]);
                    $this->player->getServer()->broadcastPacket($this->player->getServer()->getOnlinePlayers(), $packet);

                    break;
                }
            }
        }
    }

}