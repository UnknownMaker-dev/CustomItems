<?php

declare(strict_types=1);

namespace Unknown\CustomItems\entity;

use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Throwable;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\Item;
use pocketmine\math\RayTraceResult;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\world\particle\ItemBreakParticle;
use function array_map;
use function sqrt;

/**
 * Projétil lançado por um item do tipo "throwable".
 *
 * O cliente não conhece esta entidade: ela aparece com o visual de uma entidade vanilla escolhida no
 * items.yml (bola de neve, ovo, pérola...). Não é salva com o chunk, então não precisa de save ID.
 *
 * @phpstan-import-type EffectData from \Unknown\CustomItems\item\ItemDefinition
 */
final class CustomProjectile extends Throwable implements NeverSavedWithChunkEntity {
	use HitExtrasTrait;

	/** @var array{entity: string, force: float, gravity: float, damage: float, fire: int, knockback: float, particles: bool, effects: list<EffectData>} */
	private array $settings;
	private Item $item;

	/** @param array{entity: string, force: float, gravity: float, damage: float, fire: int, knockback: float, particles: bool, effects: list<EffectData>} $settings */
	public function __construct(Location $location, ?Entity $shootingEntity, array $settings, Item $item) {
		// Antes do construtor pai: ele já chama getInitialGravity().
		$this->settings = $settings;
		$this->item = $item;
		parent::__construct($location, $shootingEntity);
		$this->setBaseDamage($settings["damage"]);
	}

	public static function getNetworkTypeId(): string {
		return EntityIds::SNOWBALL;
	}

	protected function getInitialGravity(): float {
		return $this->settings["gravity"];
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult): void {
		$motion = $this->motion;
		parent::onHitEntity($entityHit, $hitResult);
		if(!$this->damaged($entityHit)) {
			return;
		}

		$this->applyHitExtras($entityHit, $this->settings["effects"], $this->settings["fire"]);

		$knockback = $this->settings["knockback"];
		$horizontalSpeed = sqrt($motion->x ** 2 + $motion->z ** 2);
		if($knockback > 0 && $horizontalSpeed > 0) {
			$multiplier = $knockback * 0.6 / $horizontalSpeed;
			$entityHit->setMotion($entityHit->getMotion()->add($motion->x * $multiplier, 0.1, $motion->z * $multiplier));
		}
	}

	protected function onHit(ProjectileHitEvent $event): void {
		if(!$this->settings["particles"]) {
			return;
		}
		$world = $this->getWorld();
		for($i = 0; $i < 6; ++$i){
			$world->addParticle($this->location, new ItemBreakParticle($this->item));
		}
	}

	/** Igual ao do Entity, trocando só o tipo enviado ao cliente pelo visual configurado. */
	protected function sendSpawnPacket(Player $player): void {
		$player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
			$this->getId(),
			$this->getId(),
			$this->settings["entity"],
			$this->getOffsetPosition($this->location->asVector3()),
			$this->getMotion(),
			$this->location->pitch,
			$this->location->yaw,
			$this->location->yaw,
			$this->location->yaw,
			array_map(static fn(Attribute $attr): NetworkAttribute => new NetworkAttribute(
				$attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue(), $attr->getDefaultValue(), []
			), $this->attributeMap->getAll()),
			$this->getAllNetworkData(),
			new PropertySyncData([], []),
			$this->getNetworkLinks()
		));
	}
}
