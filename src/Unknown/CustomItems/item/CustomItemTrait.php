<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use customiesdevs\customies\item\component\AllowOffHandComponent;
use customiesdevs\customies\item\component\CanDestroyInCreativeComponent;
use customiesdevs\customies\item\component\CooldownComponent;
use customiesdevs\customies\item\component\DamageComponent;
use customiesdevs\customies\item\component\DiggerComponent;
use customiesdevs\customies\item\component\DisplayNameComponent;
use customiesdevs\customies\item\component\DurabilityComponent;
use customiesdevs\customies\item\component\EnchantableSlotComponent;
use customiesdevs\customies\item\component\EnchantableValueComponent;
use customiesdevs\customies\item\component\FoodComponent;
use customiesdevs\customies\item\component\FuelComponent;
use customiesdevs\customies\item\component\GlintComponent;
use customiesdevs\customies\item\component\HandEquippedComponent;
use customiesdevs\customies\item\component\HoverTextColorComponent;
use customiesdevs\customies\item\component\IconComponent;
use customiesdevs\customies\item\component\MaxStackSizeComponent;
use customiesdevs\customies\item\component\RarityComponent;
use customiesdevs\customies\item\component\ShooterComponent;
use customiesdevs\customies\item\component\ShouldDespawnComponent;
use customiesdevs\customies\item\component\ThrowableComponent;
use customiesdevs\customies\item\component\UseAnimationComponent;
use customiesdevs\customies\item\component\UseDurationComponent;
use customiesdevs\customies\item\component\UseModifiersComponent;
use customiesdevs\customies\item\component\WearableComponent;
use customiesdevs\customies\item\ItemComponentsTrait;

/**
 * Liga uma {@link ItemDefinition} ao item do servidor e aos componentes enviados ao cliente.
 *
 * Fica em um trait porque cada tipo de item herda de uma classe diferente do PocketMine
 * (Item, Food, Tool, Armor) e o PHP não tem herança múltipla.
 */
trait CustomItemTrait {
	use ItemComponentsTrait;

	private ItemDefinition $definition;

	public function __construct(ItemDefinition $definition) {
		$this->definition = $definition;
		parent::__construct(
			new ItemIdentifier(ItemTypeIds::newId()),
			$definition->getDisplayName(),
			$definition->getEnchantmentTags()
		);
		$this->initCustomComponents();
	}

	public function getDefinition(): ItemDefinition {
		return $this->definition;
	}

	/**
	 * Monta os componentes que o cliente recebe na tabela de itens do Customies.
	 *
	 * Não usamos o initComponent() do Customies de propósito: ele infere tudo das classes do
	 * PocketMine e não dá acesso aos valores do YAML.
	 */
	private function initCustomComponents(): void {
		$definition = $this->definition;

		$this->addComponent(new IconComponent($definition->getTexture()));
		$this->addComponent(new DisplayNameComponent($definition->getDisplayName()));
		$this->addComponent(new MaxStackSizeComponent($definition->getMaxStackSize()));
		$this->addComponent(new CanDestroyInCreativeComponent($definition->canDestroyInCreative()));
		$this->addComponent(new HandEquippedComponent($definition->isHandEquipped()));
		$this->addComponent(new ShouldDespawnComponent($definition->shouldDespawn()));

		if($definition->allowsOffHand()) {
			$this->addComponent(new AllowOffHandComponent());
		}
		if($definition->hasGlint()) {
			$this->addComponent(new GlintComponent());
		}
		if($definition->getRarity() !== null) {
			$this->addComponent(new RarityComponent($definition->getRarity()));
		}
		if($definition->getHoverTextColor() !== "") {
			$this->addComponent(new HoverTextColorComponent($definition->getHoverTextColor()));
		}
		if($definition->getFuelTime() > 0) {
			// O componente do cliente conta em segundos; o servidor conta em ticks.
			$this->addComponent(new FuelComponent($definition->getFuelTime() / 20));
		}
		if($definition->getAttackDamage() > 0) {
			$this->addComponent(new DamageComponent($definition->getAttackDamage()));
		}

		$food = $definition->getFood();
		if($food !== null) {
			$this->addComponent(new FoodComponent($food["always_edible"], $food["nutrition"], $food["saturation"], $food["residue"]));
		}

		$armor = $definition->getArmor();
		if($armor !== null) {
			$this->addComponent(new WearableComponent(match ($armor["slot"]) {
				0 => WearableComponent::SLOT_ARMOR_HEAD,
				1 => WearableComponent::SLOT_ARMOR_CHEST,
				2 => WearableComponent::SLOT_ARMOR_LEGS,
				3 => WearableComponent::SLOT_ARMOR_FEET,
				default => WearableComponent::SLOT_ARMOR
			}, $armor["defense"]));
		}

		$type = $definition->getType();
		if($type === ItemDefinition::TYPE_TOOL || $type === ItemDefinition::TYPE_ARMOR || $type === ItemDefinition::TYPE_BOW) {
			$this->addComponent(new DurabilityComponent($definition->getMaxDurability()));
		}

		if($type === ItemDefinition::TYPE_THROWABLE) {
			$this->addComponent(new ThrowableComponent(true));
		}

		$bow = $definition->getBow();
		if($bow !== null) {
			// Sem o shooter o cliente não segura o botão de usar nem manda o "soltar" ao servidor.
			// O use_duration enorme é o mesmo do arco vanilla: o jogador segura o quanto quiser.
			$this->addComponent(new ShooterComponent($bow["ammo"], true, true, true, false, $bow["draw_time"], true));
			$this->addComponent(new UseModifiersComponent(0.35, 3600.0));
		}

		$animation = $definition->getUseAnimation();
		if($animation !== null) {
			$this->addComponent(new UseAnimationComponent($animation));
		}
		if($definition->getUseDuration() > 0) {
			$this->addComponent(new UseDurationComponent($definition->getUseDuration()));
		}

		$cooldown = $definition->getCooldown();
		if($cooldown !== null) {
			$this->addComponent(new CooldownComponent($cooldown["category"], $cooldown["duration"]));
		}

		$enchantable = $definition->getEnchantable();
		if($enchantable !== null) {
			$this->addComponent(new EnchantableSlotComponent($enchantable["slot"]));
			$this->addComponent(new EnchantableValueComponent($enchantable["value"]));
		}

		$digger = $definition->getDigger();
		if($digger !== null) {
			$component = new DiggerComponent($digger["use_efficiency"]);
			foreach($digger["destroy_speeds"] as $entry){
				$component->withTags($entry["speed"], ...$entry["tags"]);
			}
			$this->addComponent($component);
		}
	}

	public function getMaxStackSize(): int {
		return $this->definition->getMaxStackSize();
	}

	public function getFuelTime(): int {
		return $this->definition->getFuelTime();
	}

	public function getAttackPoints(): int {
		return $this->definition->getAttackDamage();
	}

	public function getCooldownTicks(): int {
		$cooldown = $this->definition->getCooldown();
		return $cooldown === null ? 0 : (int) ($cooldown["duration"] * 20);
	}

	/** Mesma tag dos itens vanilla da categoria: uma pérola customizada trava as pérolas comuns também. */
	public function getCooldownTag(): ?string {
		return $this->definition->getCooldown()["tag"] ?? null;
	}

	public function isFireProof(): bool {
		return $this->definition->isFireProof();
	}
}
