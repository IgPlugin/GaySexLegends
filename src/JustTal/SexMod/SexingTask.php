<?php

namespace JustTal\SexMod;

use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\TextFormat;

class SexingTask extends Task {

    private $player;

    private $sexingPlayer;

    private $scheduler;

    private $stamina;

    private $currentTick;

    private $lastSexMeter;

    public function __construct(Player $player, Player $sexingPlayer, TaskScheduler $scheduler) {
        $this->player = $player;
        $this->sexingPlayer = $sexingPlayer;
        $this->scheduler = $scheduler;
        $this->stamina = (((mt_rand(10, 12) * ($this->player->getXpLevel() == 0 ? 1 : ($this->player->getXpLevel() * 1.2))) + (mt_rand(10, 12) * ($this->sexingPlayer->getXpLevel() == 0 ? 1 : ($this->sexingPlayer->getXpLevel() * 1.2)))) / 2) * 2;
        if ($this->stamina < 400) {
            $this->stamina = mt_rand(300, 400);
        }
        $this->currentTick = 0;
        $this->lastSexMeter = "";

        $this->player->sendMessage(TextFormat::GREEN . "Your stamina is " . $this->stamina . "!");
        $this->sexingPlayer->sendMessage(TextFormat::GREEN . "Your stamina is " . $this->stamina . "!");
    }

    public function onRun(int $currentTick) {
        if (!$this->player->isOnline() || !$this->sexingPlayer->isOnline()) {
            $this->scheduler->cancelTask($this->getTaskId());
            return;
        }
        $this->currentTick++;

        $newSexMeter = $this->renderSexMeter($this->currentTick, $this->stamina);
        if ($this->lastSexMeter != $newSexMeter) {
            $this->playSound($this->player, "random.click");
            $this->playSound($this->sexingPlayer, "random.click");
        }
        $this->lastSexMeter = $newSexMeter;
        $this->player->sendActionBarMessage($newSexMeter);
        $this->sexingPlayer->sendActionBarMessage($newSexMeter);

        if ($this->currentTick >= $this->stamina) {
            $this->playSound($this->player, "random.toast", 2);
            $this->playSound($this->sexingPlayer, "random.toast", 2);

            $this->player->sendMessage(TextFormat::GREEN . "Successfully finished sex!");
            $this->sexingPlayer->sendMessage(TextFormat::GREEN . "Successfully finished sex!");

            Main::unsetLay($this->sexingPlayer);

            $packet = AnimateEntityPacket::create("animation.player.bob", "", "", "", 0, [$this->player->getId()]);
            $this->player->getServer()->broadcastPacket($this->player->getServer()->getOnlinePlayers(), $packet);

            $this->sexingPlayer->teleport($this->player->getLevel()->getSafeSpawn());
            $this->player->teleport($this->player->getLevel()->getSafeSpawn());

            $this->player->setImmobile(false);

            unset(Main::$sexing[$this->player->getName()]);
            unset(Main::$sexing[$this->sexingPlayer->getName()]);

            $xp = mt_rand(10, 12) * ($this->player->getXpLevel() == 0 ? 1 : ($this->player->getXpLevel() * 1.2));
            $sexingXp = mt_rand(10, 12) * ($this->sexingPlayer->getXpLevel() == 0 ? 1 : ($this->sexingPlayer->getXpLevel() * 1.2));

            $this->player->sendMessage(TextFormat::GREEN . "You earned " . $xp . " experience points for this sexventure!");
            $this->player->addXp($xp, true);

            $this->sexingPlayer->sendMessage(TextFormat::GREEN . "You earned " . $sexingXp . " experience points for this sexventure!");
            $this->sexingPlayer->addXp($sexingXp, true);

            $this->scheduler->cancelTask($this->getTaskId());
            $this->scheduler->scheduleRepeatingTask(new SexChecker($this->player, $this->scheduler), 1);
            return;
        }

        if (mt_rand(0, 100) <= 25) {
            if (mt_rand(0, 100) <= 2) {
                $this->playSound($this->sexingPlayer, "Moan");
                $this->playSound($this->player, "Moan");
            } elseif (mt_rand(0, 100) <= 2) {
                $this->playSound($this->player, "Father");
                $this->playSound($this->sexingPlayer, "Father");
            } else if (mt_rand(0, 100) <= 2) {
                $this->scheduler->scheduleDelayedTask(new ClosureTask(function (int $currentTick) : void {
                    for ($i = 0; $i <= 4; $i++) {
                        $this->player->dropItem(ItemFactory::get(351, 15)->setCustomName(TextFormat::RESET . TextFormat::WHITE . "Cum"));
                        usleep(200000);
                    }
                }), 30);
            }
        }
    }

    public function playSound(Player $player, string $moan, float $pitch = 1.0) {
        $pk = new PlaySoundPacket();
        $pk->x = $player->x;
        $pk->y = $player->y;
        $pk->z = $player->z;
        $pk->volume = 1.0;
        $pk->pitch = $pitch;
        $pk->soundName = $moan;
        $player->getServer()->broadcastPacket($player->getServer()->getOnlinePlayers(), $pk);
    }

    public function renderSexMeter(int $sexTime, int $stamina) : string {
        $toDisplay = ($sexTime * 100 / $stamina) / 4;
        $meter = "";
        for ($i = 0; $i < 25; $i++) {
            $color = $i <= $toDisplay ? TextFormat::GREEN : TextFormat::GRAY;
            $meter .= $color . "=";
        }
        return TextFormat::DARK_GRAY . "[" . $meter . TextFormat::DARK_GRAY . "]";
    }

}