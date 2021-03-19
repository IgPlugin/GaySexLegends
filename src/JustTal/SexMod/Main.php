<?php

namespace JustTal\SexMod;

use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\tile\Bed;
use ReflectionClass;

class Main extends PluginBase implements Listener {

    public static $layData = [];

    public static $sexing = [];

    public function onEnable() : void {
        $this->getLogger()->info("SEX MOD IS ENABLE");

        $this->saveResource("resource.mcpack", true);
        $this->loadPack();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        Entity::registerEntity(LayingEntity::class, true, ["LayingEntity"]);
    }

    public function loadPack() : void {
        $manager = $this->getServer()->getResourcePackManager();
        $pack = new ZippedResourcePack($this->getDataFolder() . "resource.mcpack");

        $reflection = new ReflectionClass($manager);

        $property = $reflection->getProperty("resourcePacks");
        $property->setAccessible(true);

        $currentResourcePacks = $property->getValue($manager);
        $currentResourcePacks[] = $pack;
        $property->setValue($manager, $currentResourcePacks);

        $property = $reflection->getProperty("uuidList");
        $property->setAccessible(true);
        $currentUUIDPacks = $property->getValue($manager);
        $currentUUIDPacks[strtolower($pack->getPackId())] = $pack;
        $property->setValue($manager, $currentUUIDPacks);

        $property = $reflection->getProperty("serverForceResources");
        $property->setAccessible(true);
        $property->setValue($manager, true);
    }

    public function onDisable() : void {
        $this->getLogger()->info("SEX MOD IS DISABLE !??!?!? WTF !??!?");
    }

    public function onLogin(PlayerLoginEvent $event) : void {
        $event->getPlayer()->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
    }

    public function onJoin(PlayerJoinEvent $event) : void {
        $player = $event->getPlayer();
        if ($player->getGamemode() == 3) {
            $player->setGamemode(0);
        }
        $this->getScheduler()->scheduleRepeatingTask(new SexChecker($player, $this->getScheduler()), 1);
    }

    public function onSleep(PlayerInteractEvent $event) : void {
        if ($event->getBlock() instanceof Bed) {
            return;
        }
    }

    // Thanks to SimpleLay by brokiem. This version is slightly modified
    // http://github.com/brokiem/SimpleLay
    public static function setLay(Player $player, Vector3 $pos) : void {
        $player->saveNBT();

        $nbt = Entity::createBaseNBT($player, null, 0, 0);
        $nbt->setTag($player->namedtag->getTag("Skin"));

        $layingEntity = Entity::createEntity("LayingEntity", $player->getLevelNonNull(), $nbt, $player);

        if (!$layingEntity instanceof LayingEntity) {
            return;
        }

        $layingEntity->getDataPropertyManager()->setFloat(LayingEntity::DATA_BOUNDING_BOX_HEIGHT, 0.2);
        $layingEntity->getDataPropertyManager()->setBlockPos(LayingEntity::DATA_PLAYER_BED_POSITION, $pos);
        $layingEntity->setGenericFlag(LayingEntity::DATA_FLAG_SLEEPING, true);

        $layingEntity->setNameTag($player->getDisplayName());
        $layingEntity->spawnToAll();

        $player->teleport($player->add(0, -1));

        self::$layData[$player->getLowerCaseName()] = $layingEntity;

        $player->setInvisible();
        $player->setImmobile();
        $player->setGamemode(3);
        $player->setScale(0.01);
    }

    // Thanks to SimpleLay by brokiem. This version is slightly modified
    // http://github.com/brokiem/SimpleLay
    public static function unsetLay(Player $player) : void {
        $entity = self::$layData[$player->getLowerCaseName()];

        $player->setGamemode(0);
        $player->setInvisible(false);
        $player->setImmobile(false);
        $player->setScale(1);

        unset(self::$layData[$player->getLowerCaseName()]);

        if (($entity instanceof LayingEntity) && !$entity->isFlaggedForDespawn()) {
            $entity->flagForDespawn();
        }
    }

}