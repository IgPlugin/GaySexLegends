<?php

declare(strict_types=1);

namespace JustTal\SexMod;

use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\CancelTaskException;
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
		$this->stamina = (((mt_rand(10, 12) * ($this->player->getXpManager()->getXpLevel() == 0 ? 1 : ($this->player->getXpManager()->getXpLevel() * 1.2))) + (mt_rand(10, 12) * ($this->sexingPlayer->getXpManager()->getXpLevel() == 0 ? 1 : ($this->sexingPlayer->getXpManager()->getXpLevel() * 1.2)))) / 2) * 2;
		if ($this->stamina < 400) {
			$this->stamina = mt_rand(300, 400);
		}
		$this->currentTick = 0;
		$this->lastSexMeter = "";

		$this->player->sendMessage(TextFormat::GREEN . "Your stamina is " . $this->stamina . "!");
		$this->sexingPlayer->sendMessage(TextFormat::GREEN . "Your stamina is " . $this->stamina . "!");
	}

	public function onRun(): void {
		if (!$this->player->isOnline() || !$this->sexingPlayer->isOnline()) {
			throw new CancelTaskException();
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

			Main::getInstance()->unsetLay($this->sexingPlayer);

			$packet = AnimateEntityPacket::create(
				animation: "animation.player.bob",
				nextState: "",
				stopExpression: "",
				stopExpressionVersion: 0,
				controller: "",
				blendOutTime: 0.0,
				actorRuntimeIds: [$this->player->getId()]
			);
			NetworkBroadcastUtils::broadcastPackets($this->player->getServer()->getOnlinePlayers(), [$packet]);

			$this->sexingPlayer->teleport($this->player->getWorld()->getSafeSpawn());
			$this->player->teleport($this->player->getWorld()->getSafeSpawn());

			$this->player->setNoClientPredictions(false);

			unset(Main::$sexing[$this->player->getName()]);
			unset(Main::$sexing[$this->sexingPlayer->getName()]);

			$xp = mt_rand(10, 12) * ($this->player->getXpManager()->getXpLevel() == 0 ? 1 : ($this->player->getXpManager()->getXpLevel() * 1.2));
			$sexingXp = mt_rand(10, 12) * ($this->sexingPlayer->getXpManager()->getXpLevel() == 0 ? 1 : ($this->sexingPlayer->getXpManager()->getXpLevel() * 1.2));

			$this->player->sendMessage(TextFormat::GREEN . "You earned " . $xp . " experience points for this sexventure!");
			$this->player->getXpManager()->addXp((int)$xp, true);

			$this->sexingPlayer->sendMessage(TextFormat::GREEN . "You earned " . $sexingXp . " experience points for this sexventure!");
			$this->sexingPlayer->getXpManager()->addXp((int)$sexingXp, true);

			throw new CancelTaskException();
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
				$this->scheduler->scheduleDelayedTask(new ClosureTask(function (): void {
					for ($i = 0; $i <= 4; $i++) {
						$this->player->dropItem(VanillaItems::BONE_MEAL()->setCustomName(TextFormat::RESET . TextFormat::WHITE . "Cum"));
						// usleep(200000); wtf?
					}
				}), 30);
			}
		}
	}

	public function playSound(Player $player, string $moan, float $pitch = 1.0) {
		$pk = new PlaySoundPacket();
		$pk->x = $player->getPosition()->getX();
		$pk->y = $player->getPosition()->getY();
		$pk->z = $player->getPosition()->getZ();
		$pk->volume = 1.0;
		$pk->pitch = $pitch;
		$pk->soundName = $moan;
		NetworkBroadcastUtils::broadcastPackets($player->getServer()->getOnlinePlayers(), [$pk]);
	}

	public function renderSexMeter(int $sexTime, int $stamina): string {
		$toDisplay = ($sexTime * 100 / $stamina) / 4;
		$meter = "";
		for ($i = 0; $i < 25; $i++) {
			$color = $i <= $toDisplay ? TextFormat::GREEN : TextFormat::GRAY;
			$meter .= $color . "=";
		}
		return TextFormat::DARK_GRAY . "[" . $meter . TextFormat::DARK_GRAY . "]";
	}
}
