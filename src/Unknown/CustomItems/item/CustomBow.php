<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use customiesdevs\customies\item\ItemComponents;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\Bow;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\world\sound\BowShootSound;
use Unknown\CustomItems\entity\CustomArrow;
use function intdiv;
use function min;

/**
 * Arco customizado: munição, dano, força, tempo de carga e efeitos vêm do items.yml.
 *
 * O onReleaseUsing segue o do Bow do PocketMine linha a linha, trocando os valores fixos pelos da
 * definição; os encantamentos (força, impacto, chama, infinidade) continuam funcionando.
 */
final class CustomBow extends Bow implements ItemComponents {
	use CustomItemTrait;

	public function getMaxDurability(): int {
		return $this->definition->getMaxDurability();
	}

	public function onReleaseUsing(Player $player, array &$returnedItems): ItemUseResult {
		$bow = $this->definition->getBow();
		$ammo = $this->getAmmo();
		if($ammo === null) {
			return ItemUseResult::FAIL;
		}

		$inventory = $this->findAmmoInventory($player, $ammo);
		if($player->hasFiniteResources() && $inventory === null) {
			return ItemUseResult::FAIL;
		}

		$location = $player->getLocation();

		$diff = $player->getItemUseDuration();
		$p = $diff / (20 * $bow["draw_time"]);
		$baseForce = min((($p ** 2) + $p * 2) / 3, 1);

		$entity = new CustomArrow(Location::fromObject(
			$player->getEyePos(),
			$player->getWorld(),
			($location->yaw > 180 ? 360 : 0) - $location->yaw,
			-$location->pitch
		), $player, $baseForce >= 1, $ammo, $bow["effects"], $bow["fire"]);
		$entity->setBaseDamage($bow["damage"]);
		$entity->setMotion($player->getDirectionVector());

		$infinity = $this->hasEnchantment(VanillaEnchantments::INFINITY());
		if($infinity || !$bow["consume_ammo"]) {
			// Flecha que não gastou munição não pode ser recolhida, senão vira fonte infinita de itens.
			$entity->setPickupMode(ArrowEntity::PICKUP_CREATIVE);
		}
		$punch = $this->getEnchantmentLevel(VanillaEnchantments::PUNCH()) + $bow["knockback"];
		if($punch > 0) {
			$entity->setPunchKnockback($punch);
		}
		if(($powerLevel = $this->getEnchantmentLevel(VanillaEnchantments::POWER())) > 0) {
			$entity->setBaseDamage($entity->getBaseDamage() + (($powerLevel + 1) / 2));
		}
		if($this->hasEnchantment(VanillaEnchantments::FLAME())) {
			$entity->setOnFire(intdiv($entity->getFireTicks(), 20) + 100);
		}
		$ev = new EntityShootBowEvent($player, $this, $entity, $baseForce * $bow["force"]);

		if($baseForce < 0.1 || $diff < $this->getMinUseDuration() || $player->isSpectator()) {
			$ev->cancel();
		}

		$ev->call();

		$entity = $ev->getProjectile();

		if($ev->isCancelled()) {
			$entity->flagForDespawn();
			return ItemUseResult::FAIL;
		}

		$entity->setMotion($entity->getMotion()->multiply($ev->getForce()));

		if($entity instanceof Projectile) {
			$projectileEv = new ProjectileLaunchEvent($entity);
			$projectileEv->call();
			if($projectileEv->isCancelled()) {
				$ev->getProjectile()->flagForDespawn();
				return ItemUseResult::FAIL;
			}

			$ev->getProjectile()->spawnToAll();
			$location->getWorld()->addSound($location, new BowShootSound());
		} else {
			$entity->spawnToAll();
		}

		if($player->hasFiniteResources()) {
			if(!$infinity && $bow["consume_ammo"]) {
				$inventory?->removeItem($ammo);
			}
			$this->applyDamage(1);
		}

		return ItemUseResult::SUCCESS;
	}

	public function canStartUsingItem(Player $player): bool {
		if(!$player->hasFiniteResources()) {
			return true;
		}
		$ammo = $this->getAmmo();
		return $ammo !== null && $this->findAmmoInventory($player, $ammo) !== null;
	}

	/**
	 * Resolvido na hora do uso e não no boot: a munição pode ser um item customizado registrado
	 * depois deste arco. Um id que não existe já foi avisado no console quando o servidor subiu.
	 */
	private function getAmmo(): ?Item {
		return StringToItemParser::getInstance()->parse($this->definition->getBow()["ammo"])?->setCount(1);
	}

	private function findAmmoInventory(Player $player, Item $ammo): ?Inventory {
		return match (true) {
			$player->getOffHandInventory()->contains($ammo) => $player->getOffHandInventory(),
			$player->getInventory()->contains($ammo) => $player->getInventory(),
			default => null
		};
	}
}
