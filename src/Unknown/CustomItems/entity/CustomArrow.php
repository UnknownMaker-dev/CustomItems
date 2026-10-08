<?php

declare(strict_types=1);

namespace Unknown\CustomItems\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\item\Item;
use pocketmine\math\RayTraceResult;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\player\Player;

/**
 * Flecha disparada por um arco customizado.
 *
 * Aplica os efeitos e o fogo do arco em quem ela acerta e, ao ser recolhida, devolve a munição que foi
 * gasta — que pode ser um item customizado, não só a flecha vanilla. Não é salva com o chunk.
 *
 * @phpstan-import-type EffectData from \Unknown\CustomItems\item\ItemDefinition
 */
final class CustomArrow extends Arrow implements NeverSavedWithChunkEntity {
	use HitExtrasTrait;

	/**
	 * @param list<EffectData> $effects
	 * @param Item             $ammo    o que o jogador recebe ao recolher a flecha
	 */
	public function __construct(
		Location $location,
		?Entity $shootingEntity,
		bool $critical,
		private readonly Item $ammo,
		private readonly array $effects,
		private readonly int $fireSeconds
	) {
		parent::__construct($location, $shootingEntity, $critical);
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult): void {
		parent::onHitEntity($entityHit, $hitResult);
		if($this->damaged($entityHit)) {
			$this->applyHitExtras($entityHit, $this->effects, $this->fireSeconds);
		}
	}

	/** Igual ao do Arrow, trocando a flecha vanilla pela munição usada. */
	public function onCollideWithPlayer(Player $player): void {
		if($this->blockHit === null) {
			return;
		}

		$item = (clone $this->ammo)->setCount(1);
		$playerInventory = match (true) {
			!$player->hasFiniteResources() => null,
			$player->getOffHandInventory()->getItem(0)->canStackWith($item) && $player->getOffHandInventory()->canAddItem($item) => $player->getOffHandInventory(),
			$player->getInventory()->canAddItem($item) => $player->getInventory(),
			default => null
		};

		$ev = new EntityItemPickupEvent($player, $this, $item, $playerInventory);
		if($player->hasFiniteResources() && $playerInventory === null) {
			$ev->cancel();
		}
		if($this->pickupMode === self::PICKUP_NONE || ($this->pickupMode === self::PICKUP_CREATIVE && !$player->isCreative())) {
			$ev->cancel();
		}

		$ev->call();
		if($ev->isCancelled()) {
			return;
		}

		NetworkBroadcastUtils::broadcastEntityEvent(
			$this->getViewers(),
			fn(EntityEventBroadcaster $broadcaster, array $recipients) => $broadcaster->onPickUpItem($recipients, $player, $this)
		);

		$ev->getInventory()?->addItem($ev->getItem());
		$this->flagForDespawn();
	}
}
