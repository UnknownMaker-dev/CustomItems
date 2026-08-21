<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use pocketmine\block\BlockToolType;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\ArmorTypeInfo;
use pocketmine\item\ToolTier;
use Unknown\CustomItems\libs\customiesdevs\customies\item\component\CooldownComponent;
use Unknown\CustomItems\libs\customiesdevs\customies\item\component\EnchantableSlotComponent;
use Unknown\CustomItems\libs\customiesdevs\customies\item\component\RarityComponent;
use Unknown\CustomItems\libs\customiesdevs\customies\item\component\UseAnimationComponent;
use Unknown\CustomItems\libs\customiesdevs\customies\item\CreativeInventoryInfo;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function min;
use function preg_match;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;
use function ucwords;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Representação imutável de um item customizado descrito no items.yml.
 *
 * Assim como no CustomBlocks, os dados ficam num array puro de escalares — facilita validar de uma
 * vez só e mantém a definição serializável.
 */
final class ItemDefinition {

	public const TYPE_ITEM = "item";
	public const TYPE_FOOD = "food";
	public const TYPE_TOOL = "tool";
	public const TYPE_ARMOR = "armor";

	private const TYPES = [
		self::TYPE_ITEM => self::TYPE_ITEM,
		self::TYPE_FOOD => self::TYPE_FOOD,
		self::TYPE_TOOL => self::TYPE_TOOL,
		self::TYPE_ARMOR => self::TYPE_ARMOR,
	];

	private const TOOL_TYPES = [
		"none" => BlockToolType::NONE,
		"sword" => BlockToolType::SWORD,
		"shovel" => BlockToolType::SHOVEL,
		"pickaxe" => BlockToolType::PICKAXE,
		"axe" => BlockToolType::AXE,
		"shears" => BlockToolType::SHEARS,
		"hoe" => BlockToolType::HOE,
	];

	private const TOOL_TIERS = [
		"wood" => ToolTier::WOOD,
		"gold" => ToolTier::GOLD,
		"stone" => ToolTier::STONE,
		"iron" => ToolTier::IRON,
		"diamond" => ToolTier::DIAMOND,
		"netherite" => ToolTier::NETHERITE,
	];

	private const ARMOR_SLOTS = [
		"helmet" => ArmorInventory::SLOT_HEAD,
		"head" => ArmorInventory::SLOT_HEAD,
		"chestplate" => ArmorInventory::SLOT_CHEST,
		"chest" => ArmorInventory::SLOT_CHEST,
		"leggings" => ArmorInventory::SLOT_LEGS,
		"legs" => ArmorInventory::SLOT_LEGS,
		"boots" => ArmorInventory::SLOT_FEET,
		"feet" => ArmorInventory::SLOT_FEET,
	];

	private const RARITIES = [
		"common" => RarityComponent::COMMON,
		"uncommon" => RarityComponent::UNCOMMON,
		"rare" => RarityComponent::RARE,
		"epic" => RarityComponent::EPIC,
	];

	private const USE_ANIMATIONS = [
		"none" => UseAnimationComponent::ANIMATION_NONE,
		"eat" => UseAnimationComponent::ANIMATION_EAT,
		"drink" => UseAnimationComponent::ANIMATION_DRINK,
		"block" => UseAnimationComponent::ANIMATION_BLOCK,
		"bow" => UseAnimationComponent::ANIMATION_BOW,
		"camera" => UseAnimationComponent::ANIMATION_CAMERA,
		"spear" => UseAnimationComponent::ANIMATION_SPEAR,
		"crossbow" => UseAnimationComponent::ANIMATION_CROSSBOW,
		"spyglass" => UseAnimationComponent::ANIMATION_SPYGLASS,
		"brush" => UseAnimationComponent::ANIMATION_BRUSH,
	];

	private const ENCHANTABLE_SLOTS = [
		"all" => EnchantableSlotComponent::SLOT_ALL,
		"boots" => EnchantableSlotComponent::SLOT_BOOTS,
		"chestplate" => EnchantableSlotComponent::SLOT_CHESTPLATE,
		"helmet" => EnchantableSlotComponent::SLOT_HELMET,
		"leggings" => EnchantableSlotComponent::SLOT_LEGGINGS,
		"axe" => EnchantableSlotComponent::SLOT_AXE,
		"bow" => EnchantableSlotComponent::SLOT_BOW,
		"crossbow" => EnchantableSlotComponent::SLOT_CROSSBOW,
		"elytra" => EnchantableSlotComponent::SLOT_ELYTRA,
		"fishing_rod" => EnchantableSlotComponent::SLOT_FISHING_ROD,
		"hoe" => EnchantableSlotComponent::SLOT_HOE,
		"pickaxe" => EnchantableSlotComponent::SLOT_PICKAXE,
		"shears" => EnchantableSlotComponent::SLOT_SHEARS,
		"shield" => EnchantableSlotComponent::SLOT_SHIELD,
		"shovel" => EnchantableSlotComponent::SLOT_SHOVEL,
		"sword" => EnchantableSlotComponent::SLOT_SWORD,
	];

	private const COOLDOWN_CATEGORIES = [
		"shield" => CooldownComponent::CATEGORY_SHIELD,
		"ender_pearl" => CooldownComponent::CATEGORY_PEARL,
		"goat_horn" => CooldownComponent::CATEGORY_HORN,
		"wind_charge" => CooldownComponent::CATEGORY_WINDCHARGE,
		"chorus_fruit" => CooldownComponent::CATEGORY_CHORUS,
	];

	private const CREATIVE_CATEGORIES = [
		"all" => CreativeInventoryInfo::CATEGORY_ALL,
		"commands" => CreativeInventoryInfo::CATEGORY_COMMANDS,
		"construction" => CreativeInventoryInfo::CATEGORY_CONSTRUCTION,
		"equipment" => CreativeInventoryInfo::CATEGORY_EQUIPMENT,
		"items" => CreativeInventoryInfo::CATEGORY_ITEMS,
		"nature" => CreativeInventoryInfo::CATEGORY_NATURE,
	];

	/**
	 * Tags de bloco que o cliente usa para prever a velocidade de mineração. Só afetam a animação
	 * no cliente — quem decide quando o bloco quebra continua sendo o servidor, pelo tool/tool_tier.
	 */
	private const DEFAULT_DIGGER_TAGS = [
		BlockToolType::PICKAXE => ["stone", "metal", "diamond_pick_diggable", "mob_spawner", "rail", "slab_block", "stair_block"],
		BlockToolType::AXE => ["wood", "pumpkin", "plant"],
		BlockToolType::SHOVEL => ["dirt", "sand", "gravel", "grass", "snow"],
		BlockToolType::HOE => ["leaves", "plant"],
		BlockToolType::SHEARS => ["web", "wool", "leaves"],
		BlockToolType::SWORD => ["web", "plant"],
	];

	/** @param mixed[] $data já normalizado por {@link ItemDefinition::parse()} */
	private function __construct(private readonly array $data) { }

	/**
	 * Lê e valida a entrada crua vinda do items.yml.
	 *
	 * @param mixed[] $raw
	 * @throws ItemDefinitionException se qualquer campo estiver fora do formato esperado
	 */
	public static function parse(string $identifier, array $raw): self {
		$identifier = strtolower(trim($identifier));
		if(preg_match('/^[a-z0-9_]+:[a-z0-9_]+$/', $identifier) !== 1) {
			throw new ItemDefinitionException($identifier, "o identificador precisa estar no formato 'namespace:nome' usando apenas [a-z0-9_]");
		}
		if(str_starts_with($identifier, "minecraft:")) {
			throw new ItemDefinitionException($identifier, "o namespace 'minecraft' é reservado pelo jogo");
		}

		$type = self::readEnum($identifier, $raw, "type", self::TYPES, self::TYPE_ITEM);

		$texture = self::readString($identifier, $raw, "texture", "");
		if($texture === "") {
			throw new ItemDefinitionException($identifier, "defina 'texture: <nome>' com o nome do PNG (sem a extensão)");
		}
		// Sempre em minúsculas: o gerador do resource pack registra os ícones pelo nome do arquivo
		// já rebaixado, e uma diferença de caixa faria o cliente procurar uma chave inexistente.
		$texture = strtolower($texture);

		$toolType = self::readEnum($identifier, $raw, "tool", self::TOOL_TYPES, "none");
		$tierName = self::readString($identifier, $raw, "tool_tier", "");
		if($tierName === "") {
			$harvestLevel = 0;
			$defaultDurability = 250;
			$defaultAttack = 1;
		} else {
			$tier = self::TOOL_TIERS[strtolower($tierName)] ?? null;
			if($tier === null) {
				throw new ItemDefinitionException($identifier, "tool_tier '$tierName' é inválido (use: " . implode(", ", array_keys(self::TOOL_TIERS)) . ")");
			}
			$harvestLevel = $tier->getHarvestLevel();
			$defaultDurability = $tier->getMaxDurability();
			$defaultAttack = $tier->getBaseAttackPoints();
		}

		$stackable = $type === self::TYPE_TOOL || $type === self::TYPE_ARMOR ? 1 : 64;
		$maxStackSize = min(64, max(1, self::readInt($identifier, $raw, "max_stack_size", $stackable)));

		// --- comida ---------------------------------------------------------
		$foodRaw = is_array($raw["food"] ?? null) ? $raw["food"] : [];
		if($type === self::TYPE_FOOD && $foodRaw === []) {
			throw new ItemDefinitionException($identifier, "um item do tipo 'food' precisa da seção 'food:' com pelo menos 'nutrition'");
		}
		$food = $type !== self::TYPE_FOOD ? null : [
			"nutrition" => max(0, self::readInt($identifier, $foodRaw, "nutrition", 4)),
			"saturation" => max(0.0, self::readFloat($identifier, $foodRaw, "saturation", 0.6)),
			"always_edible" => self::readBool($identifier, $foodRaw, "always_edible", false),
			"residue" => self::readString($identifier, $foodRaw, "residue", ""),
			"eat_ticks" => max(1, self::readInt($identifier, $foodRaw, "eat_ticks", 31)),
		];

		// --- armadura -------------------------------------------------------
		$armorRaw = is_array($raw["armor"] ?? null) ? $raw["armor"] : [];
		if($type === self::TYPE_ARMOR && $armorRaw === []) {
			throw new ItemDefinitionException($identifier, "um item do tipo 'armor' precisa da seção 'armor:' com pelo menos 'slot'");
		}
		$armor = $type !== self::TYPE_ARMOR ? null : [
			"slot" => self::readEnum($identifier, $armorRaw, "slot", self::ARMOR_SLOTS, "helmet"),
			"defense" => max(0, self::readInt($identifier, $armorRaw, "defense", 2)),
			"toughness" => max(0, self::readInt($identifier, $armorRaw, "toughness", 0)),
		];

		// --- digger (velocidade prevista no cliente) -------------------------
		$digger = self::readDigger($identifier, $raw, $type, $toolType);

		// --- cooldown -------------------------------------------------------
		$cooldownRaw = is_array($raw["cooldown"] ?? null) ? $raw["cooldown"] : [];
		$cooldown = $cooldownRaw === [] ? null : [
			"category" => self::readEnum($identifier, $cooldownRaw, "category", self::COOLDOWN_CATEGORIES, "ender_pearl"),
			"duration" => max(0.0, self::readFloat($identifier, $cooldownRaw, "duration", 1.0)),
		];

		// --- encantamento ---------------------------------------------------
		$enchantRaw = is_array($raw["enchantable"] ?? null) ? $raw["enchantable"] : [];
		$enchantable = $enchantRaw === [] ? null : [
			"slot" => self::readEnum($identifier, $enchantRaw, "slot", self::ENCHANTABLE_SLOTS, "all"),
			"value" => max(0, self::readInt($identifier, $enchantRaw, "value", 10)),
		];

		$creativeRaw = is_array($raw["creative"] ?? null) ? $raw["creative"] : [];

		return new self([
			"identifier" => $identifier,
			"name" => self::readString($identifier, $raw, "name", self::humanize($identifier)),
			"texture" => $texture,
			"type" => $type,

			"max_stack_size" => $maxStackSize,
			"max_durability" => max(1, self::readInt($identifier, $raw, "max_durability", $defaultDurability)),
			"attack_damage" => max(0, self::readInt($identifier, $raw, "attack_damage", $type === self::TYPE_TOOL ? $defaultAttack : 0)),
			"efficiency" => max(0.0, self::readFloat($identifier, $raw, "efficiency", 1.0)),
			"tool_type" => $toolType,
			"harvest_level" => $harvestLevel,
			"fuel_time" => max(0, self::readInt($identifier, $raw, "fuel_time", 0)),
			"fire_proof" => self::readBool($identifier, $raw, "fire_proof", false),

			"food" => $food,
			"armor" => $armor,
			"digger" => $digger,
			"cooldown" => $cooldown,
			"enchantable" => $enchantable,
			"enchantment_tags" => self::readTags($identifier, $raw, "enchantment_tags"),

			"glint" => self::readBool($identifier, $raw, "glint", false),
			"rarity" => array_key_exists("rarity", $raw) ? self::readEnum($identifier, $raw, "rarity", self::RARITIES, "common") : null,
			"hand_equipped" => self::readBool($identifier, $raw, "hand_equipped", $type === self::TYPE_TOOL),
			"allow_off_hand" => self::readBool($identifier, $raw, "allow_off_hand", false),
			"can_destroy_in_creative" => self::readBool($identifier, $raw, "can_destroy_in_creative", $toolType !== BlockToolType::SWORD),
			"should_despawn" => self::readBool($identifier, $raw, "should_despawn", true),
			"hover_text_color" => self::readString($identifier, $raw, "hover_text_color", ""),
			"use_animation" => array_key_exists("use_animation", $raw)
				? self::readEnum($identifier, $raw, "use_animation", self::USE_ANIMATIONS, "none")
				: ($type === self::TYPE_FOOD ? UseAnimationComponent::ANIMATION_EAT : null),
			"use_duration" => max(0, self::readInt($identifier, $raw, "use_duration", $food["eat_ticks"] ?? 0)),

			"creative_category" => self::readEnum($identifier, $creativeRaw, "category", self::CREATIVE_CATEGORIES, "items"),
			"creative_group" => self::readString($identifier, $creativeRaw, "group", CreativeInventoryInfo::NONE),
		]);
	}

	/** Reconstrói a definição a partir do JSON produzido por {@link ItemDefinition::toJson()}. */
	public static function fromJson(string $json): self {
		return new self(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
	}

	public function toJson(): string {
		return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
	}

	/** @return mixed[] */
	public function toArray(): array {
		return $this->data;
	}

	public function getIdentifier(): string {
		return $this->data["identifier"];
	}

	public function getDisplayName(): string {
		return $this->data["name"];
	}

	public function getTexture(): string {
		return $this->data["texture"];
	}

	public function getType(): string {
		return $this->data["type"];
	}

	public function getMaxStackSize(): int {
		return $this->data["max_stack_size"];
	}

	public function getMaxDurability(): int {
		return $this->data["max_durability"];
	}

	public function getAttackDamage(): int {
		return $this->data["attack_damage"];
	}

	public function getEfficiency(): float {
		return $this->data["efficiency"];
	}

	public function getToolType(): int {
		return $this->data["tool_type"];
	}

	public function getHarvestLevel(): int {
		return $this->data["harvest_level"];
	}

	public function getFuelTime(): int {
		return $this->data["fuel_time"];
	}

	public function isFireProof(): bool {
		return $this->data["fire_proof"];
	}

	/** @return null|array{nutrition: int, saturation: float, always_edible: bool, residue: string, eat_ticks: int} */
	public function getFood(): ?array {
		return $this->data["food"];
	}

	/** @return null|array{slot: int, defense: int, toughness: int} */
	public function getArmor(): ?array {
		return $this->data["armor"];
	}

	/** @return null|array{use_efficiency: bool, destroy_speeds: array<int, array{speed: int, tags: string[]}>} */
	public function getDigger(): ?array {
		return $this->data["digger"];
	}

	/** @return null|array{category: string, duration: float} */
	public function getCooldown(): ?array {
		return $this->data["cooldown"];
	}

	/** @return null|array{slot: string, value: int} */
	public function getEnchantable(): ?array {
		return $this->data["enchantable"];
	}

	/** @return string[] */
	public function getEnchantmentTags(): array {
		return $this->data["enchantment_tags"];
	}

	public function hasGlint(): bool {
		return $this->data["glint"];
	}

	public function getRarity(): ?string {
		return $this->data["rarity"];
	}

	public function isHandEquipped(): bool {
		return $this->data["hand_equipped"];
	}

	public function allowsOffHand(): bool {
		return $this->data["allow_off_hand"];
	}

	public function canDestroyInCreative(): bool {
		return $this->data["can_destroy_in_creative"];
	}

	public function shouldDespawn(): bool {
		return $this->data["should_despawn"];
	}

	public function getHoverTextColor(): string {
		return $this->data["hover_text_color"];
	}

	public function getUseAnimation(): ?int {
		return $this->data["use_animation"];
	}

	public function getUseDuration(): int {
		return $this->data["use_duration"];
	}

	public function getCreativeCategory(): string {
		return $this->data["creative_category"];
	}

	public function getCreativeGroup(): string {
		return $this->data["creative_group"];
	}

	public function createCreativeInfo(): CreativeInventoryInfo {
		return new CreativeInventoryInfo($this->data["creative_category"], $this->data["creative_group"]);
	}

	public function createArmorTypeInfo(): ArmorTypeInfo {
		$armor = $this->data["armor"];
		return new ArmorTypeInfo(
			$armor["defense"],
			$this->data["max_durability"],
			$armor["slot"],
			$armor["toughness"],
			$this->data["fire_proof"]
		);
	}

	/**
	 * @param mixed[] $raw
	 * @return null|array{use_efficiency: bool, destroy_speeds: array<int, array{speed: int, tags: string[]}>}
	 */
	private static function readDigger(string $identifier, array $raw, string $type, int $toolType): ?array {
		$diggerRaw = $raw["digger"] ?? null;
		if($diggerRaw === false) {
			return null;
		}

		if(!is_array($diggerRaw)) {
			// Sem configuração explícita, uma ferramenta ganha as tags padrão do seu tipo para que
			// a animação de mineração no cliente bata com o que o servidor calcula.
			$tags = self::DEFAULT_DIGGER_TAGS[$toolType] ?? null;
			if($type !== self::TYPE_TOOL || $tags === null) {
				return null;
			}
			return ["use_efficiency" => true, "destroy_speeds" => [["speed" => 6, "tags" => $tags]]];
		}

		$speeds = $diggerRaw["destroy_speeds"] ?? null;
		if(!is_array($speeds) || !array_is_list($speeds) || $speeds === []) {
			throw new ItemDefinitionException($identifier, "digger precisa de 'destroy_speeds' com pelo menos uma entrada, ou 'digger: false' para desativar");
		}

		$parsed = [];
		foreach($speeds as $index => $entry){
			if(!is_array($entry)) {
				throw new ItemDefinitionException($identifier, "digger.destroy_speeds[$index] precisa ser um mapa com 'speed' e 'tags'");
			}
			$tags = self::readTags($identifier, $entry, "tags");
			if($tags === []) {
				throw new ItemDefinitionException($identifier, "digger.destroy_speeds[$index] precisa de pelo menos uma tag");
			}
			$parsed[] = ["speed" => max(0, self::readInt($identifier, $entry, "speed", 6)), "tags" => $tags];
		}
		return [
			"use_efficiency" => self::readBool($identifier, $diggerRaw, "use_efficiency", true),
			"destroy_speeds" => $parsed,
		];
	}

	/**
	 * @param mixed[] $raw
	 * @return string[]
	 */
	private static function readTags(string $identifier, array $raw, string $key): array {
		$tags = $raw[$key] ?? [];
		if(is_string($tags)) {
			$tags = [$tags];
		}
		if(!is_array($tags) || !array_is_list($tags)) {
			throw new ItemDefinitionException($identifier, "$key precisa ser uma lista de textos");
		}
		$result = [];
		foreach($tags as $tag){
			if(!is_string($tag) || trim($tag) === "") {
				throw new ItemDefinitionException($identifier, "$key só aceita textos não vazios");
			}
			$result[] = trim($tag);
		}
		return $result;
	}

	/** @param mixed[] $raw */
	private static function readString(string $identifier, array $raw, string $key, string $default): string {
		if(!array_key_exists($key, $raw) || $raw[$key] === null) {
			return $default;
		}
		if(!is_string($raw[$key])) {
			throw new ItemDefinitionException($identifier, "$key precisa ser um texto");
		}
		return trim($raw[$key]);
	}

	/** @param mixed[] $raw */
	private static function readBool(string $identifier, array $raw, string $key, bool $default): bool {
		if(!array_key_exists($key, $raw) || $raw[$key] === null) {
			return $default;
		}
		if(!is_bool($raw[$key])) {
			throw new ItemDefinitionException($identifier, "$key precisa ser true ou false");
		}
		return $raw[$key];
	}

	/** @param mixed[] $raw */
	private static function readInt(string $identifier, array $raw, string $key, int $default): int {
		if(!array_key_exists($key, $raw) || $raw[$key] === null) {
			return $default;
		}
		if(!is_numeric($raw[$key])) {
			throw new ItemDefinitionException($identifier, "$key precisa ser um número inteiro");
		}
		return (int) $raw[$key];
	}

	/** @param mixed[] $raw */
	private static function readFloat(string $identifier, array $raw, string $key, float $default): float {
		if(!array_key_exists($key, $raw) || $raw[$key] === null) {
			return $default;
		}
		if(!is_numeric($raw[$key])) {
			throw new ItemDefinitionException($identifier, "$key precisa ser um número");
		}
		return (float) $raw[$key];
	}

	/**
	 * @param mixed[] $raw
	 * @param array<string, mixed> $values
	 */
	private static function readEnum(string $identifier, array $raw, string $key, array $values, string $default): mixed {
		$name = strtolower(self::readString($identifier, $raw, $key, $default));
		if(!array_key_exists($name, $values)) {
			throw new ItemDefinitionException($identifier, "$key '$name' é inválido (use: " . implode(", ", array_keys($values)) . ")");
		}
		return $values[$name];
	}

	/** "customitems:rubi_bruto" => "Rubi Bruto" */
	private static function humanize(string $identifier): string {
		[, $name] = explode(":", $identifier, 2);
		return ucwords(str_replace("_", " ", $name));
	}
}
