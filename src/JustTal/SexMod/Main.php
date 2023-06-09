<?php

declare(strict_types=1);

namespace JustTal\SexMod;

use NhanAZ\libRegRsp\libRegRsp;
use pocketmine\block\Air;
use pocketmine\block\tile\Bed;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class Main extends PluginBase implements Listener {
	use SingletonTrait;

	public static $layData = [];

	public static $sexing = [];

	protected function onEnable(): void {
		self::setInstance($this);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		EntityFactory::getInstance()->register(LayingEntity::class, function (World $world, CompoundTag $nbt): LayingEntity {
			return new LayingEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
		}, ['LayingEntity']);
		libRegRsp::regRsp($this);
	}

	protected function onDisable(): void {
		libRegRsp::unRegRsp($this);
	}

	public function onLogin(PlayerLoginEvent $event): void {
		$event->getPlayer()->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
	}

	public function onJoin(PlayerJoinEvent $event): void {
		$player = $event->getPlayer();
		if ($player->getGamemode()->equals(GameMode::SPECTATOR())) {
			$player->setGamemode(GameMode::SURVIVAL());
		}
		$this->getScheduler()->scheduleRepeatingTask(new SexChecker($player, $this->getScheduler()), 1);
	}

	public function onSleep(PlayerInteractEvent $event): void {
		if ($event->getBlock() instanceof Bed) {
			return;
		}
	}

	// Thanks to SimpleLay by brokiem. This version is slightly modified
	// http://github.com/brokiem/SimpleLay
	/**
	 * Helper function which creates minimal NBT needed to spawn an entity.
	 */
	public static function createBaseNBT(Vector3 $pos, ?Vector3 $motion = null, float $yaw = 0.0, float $pitch = 0.0): CompoundTag {
		return CompoundTag::create()
			->setTag("Pos", new ListTag([
				new DoubleTag($pos->x),
				new DoubleTag($pos->y),
				new DoubleTag($pos->z)
			]))
			->setTag("Motion", new ListTag([
				new DoubleTag($motion !== null ? $motion->x : 0.0),
				new DoubleTag($motion !== null ? $motion->y : 0.0),
				new DoubleTag($motion !== null ? $motion->z : 0.0)
			]))
			->setTag("Rotation", new ListTag([
				new FloatTag($yaw),
				new FloatTag($pitch)
			]));
	}

	// Thanks to SimpleLay by brokiem. This version is slightly modified
	// http://github.com/brokiem/SimpleLay
	public function setLay(Player $player): void {
		$level = $player->getWorld();
		$block = $level->getBlock($player->getPosition()->add(0, -0.5, 0));
		if ($block instanceof Air) {
			$player->sendMessage(TextFormat::colorize("&cYou can't sex here!"));
			return;
		}

		$player->saveNBT();

		$nbt = Main::createBaseNBT($player->getLocation(), null, $player->getLocation()->getYaw(), $player->getLocation()->getPitch());

		$pos = $player->getPosition()->add(0, -0.3, 0);
		$layingEntity = new LayingEntity($player->getLocation(), $player->getSkin(), $nbt, $player);
		$layingEntity->getNetworkProperties()->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.2);
		$layingEntity->getNetworkProperties()->setBlockPos(EntityMetadataProperties::PLAYER_BED_POSITION, new BlockPosition($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()));
		$layingEntity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SLEEPING, true);

		$layingEntity->setCanSaveWithChunk(false);
		$layingEntity->setNameTag($player->getDisplayName());
		$layingEntity->spawnToAll();

		$player->teleport($player->getPosition()->add(0, -0.5, 0));

		self::$layData[strtolower($player->getName())] = [
			"entity" => $layingEntity,
			"pos" => $player->getPosition()->floor()
		];

		$player->setInvisible();
		$player->setNoClientPredictions();
		$player->setScale(0.01);

		$player->sendMessage(TextFormat::colorize("&6You are now sexing!"));
		$player->sendActionBarMessage(TextFormat::colorize("Tap the sneak button to stand up"));
	}

	// Thanks to SimpleLay by brokiem. This version is slightly modified
	// http://github.com/brokiem/SimpleLay
	public function unsetLay(Player $player): void {
		$entity = self::$layData[strtolower($player->getName())];

		$player->setInvisible(false);
		$player->setNoClientPredictions(false);
		$player->setScale(1);

		$player->sendMessage(TextFormat::colorize("&6You are no longer sexing!"));
		unset(self::$layData[strtolower($player->getName())]);

		if (($entity instanceof LayingEntity) && !$entity->isFlaggedForDespawn()) {
			$entity->flagForDespawn();
		}

		$player->teleport($player->getPosition()->add(0, 1.2, 0));
	}
}
