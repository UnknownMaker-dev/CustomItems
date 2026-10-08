# CustomItems

Plugin de PocketMine-MP para criar itens customizados escrevendo YAML, sem precisar de código.
Toda a parte de rede e de tabela de itens é feita pelo **Customies**, que precisa estar
**instalado como plugin** em `plugins/`.

> ⚠️ **Use o fork do Altay: [altayofficial/Customies](https://github.com/altayofficial/Customies).**
>
> O upstream [CustomiesDevs/Customies](https://github.com/CustomiesDevs/Customies) (1.4.x) corrompe o
> mundo nos protocolos recentes assim que algum bloco customizado é registrado — veja o README do
> CustomBlocks para os detalhes.

## Instalação

1. Instale o **Customies** ([fork do Altay](https://github.com/altayofficial/Customies)) em
   `plugins/` — sem ele o PocketMine nem carrega este plugin.
2. Coloque a pasta `CustomItems` em `plugins/`.
3. Suba o servidor uma vez para gerar a pasta de dados do plugin.
4. Jogue os PNGs dos ícones na subpasta `textures/`.
5. Descreva os itens no `items.yml` e reinicie.

O PocketMine-MP 5 não carrega plugins em pasta sozinho — é preciso ter o **DevTools** instalado,
ou empacotar o plugin em `.phar`.

Onde fica a pasta de dados depende de `plugins.legacy-data-dir` no `pocketmine.yml`:
`plugin_data/CustomItems/` quando é `false` (o padrão do arquivo de exemplo) ou
`plugins/CustomItems/` quando é `true`. O plugin escreve o caminho exato no console a cada boot:

```
[CustomItems] Ícones dos itens: .../plugin_data/CustomItems/textures
```

> Itens customizados só podem ser registrados enquanto o servidor sobe. Qualquer mudança no
> `items.yml` exige reiniciar — não existe reload.

Funciona junto do CustomBlocks: os dois usam o mesmo Customies instalado.

## Criando um item

```yaml
items:
  customitems:rubi:
    name: "Rubi"
    texture: rubi          # <pasta de dados>/textures/rubi.png
    creative:
      category: items
```

O identificador é sempre `namespace:nome`. Use um namespace seu — `minecraft` é reservado.
O campo `type` decide o comportamento:

| `type` | Vira | Para que serve |
| --- | --- | --- |
| `item` (padrão) | `Item` | Material de craft, moeda, troféu |
| `food` | `Food` | Comida, com nutrição, saturação e resto |
| `tool` | `Tool` | Ferramenta ou arma, com durabilidade e dano |
| `armor` | `Armor` | Peça de armadura, com proteção e slot |
| `throwable` | `ProjectileItem` | Item arremessável, com dano, fogo e efeitos no alvo |
| `bow` | `Bow` | Arco com munição, dano, força e tempo de carga próprios |

O `items.yml` que vem junto traz um exemplo de cada tipo e documenta todos os campos.

## Efeitos

Comida, arremessável e arco aceitam uma lista de efeitos — ao comer, ou em quem for atingido:

```yaml
    food:
      nutrition: 4
      effects:
        - effect: regeneration
          duration: 10      # segundos
          level: 2          # 1 = nível I
        - effect: speed     # chance de 50%
          chance: 0.5
        - night_vision      # só o nome: 30 s, nível I
```

Com `food.clear_effects: true` a comida remove os efeitos que o jogador tinha, como o leite, e só
depois aplica os seus — dá para fazer poções e remédios.

Os efeitos de projétil e de flecha só pegam se o dano passou: área sem PvP, plugin que cancela o
dano ou a invencibilidade logo após um golpe também barram os efeitos e o fogo.

## Projéteis

O projétil do `throwable` e a flecha do `bow` não são salvos com o mundo: se o servidor fechar com
um deles no ar, ele some. A flecha cravada no chão pode ser recolhida e devolve a munição usada,
mesmo quando ela é um item customizado. No `throwable`, `projectile.entity` só escolhe a aparência
em voo entre as entidades que o jogo já conhece (bola de neve, ovo, pérola, bola de fogo...).

O `cooldown` vale no cliente e no servidor, e é compartilhado com os itens vanilla da mesma
categoria: uma shuriken com `category: ender_pearl` também trava as pérolas do jogador.

## Resource pack

O plugin monta sozinho o resource pack com os ícones e o coloca no topo da pilha do servidor.
O `item_texture.json` e o `manifest.json` são gerados a partir dos PNGs da pasta `textures/`;
o nome do arquivo (sem `.png`) é o nome usado no campo `texture`.

Saídas na pasta de dados do plugin:

| Arquivo | Para que serve |
| --- | --- |
| `CustomItems.mcpack` | O pack que o servidor envia aos jogadores |
| `resource_pack/` | O mesmo pack descompactado, para conferir ou editar |
| `pack-version.json` | Controla a versão do pack |

A versão do pack só sobe quando o conteúdo muda, então os jogadores não rebaixam tudo a cada boot.

Se `force-resources` estiver desligado no `config.yml`, quem recusar o download vai ver os itens
sem ícone — o plugin avisa no console quando esse é o caso.

## Comandos

| Comando | Descrição |
| --- | --- |
| `/customitems list` | Lista os itens registrados |
| `/customitems info <item>` | Mostra as propriedades de um item |
| `/customitems give [jogador] <item> [qtd]` | Entrega o item |
| `/customitems pack` | Regera o resource pack |

Aliases: `/citems`, `/ci`. Permissão: `customitems.command` (padrão: op).

## API para outros plugins

Adicione `CustomItems` no `depend` do seu `plugin.yml` e registre no `onEnable`:

```php
use Unknown\CustomItems\CustomItems;

CustomItems::getInstance()->registerItem("meuplugin:espada", [
    "name" => "Espada Lendária",
    "texture" => "espada_lendaria",
    "type" => "tool",
    "tool" => "sword",
    "tool_tier" => "netherite",
    "attack_damage" => 12,
]);
```

O array aceita exatamente os mesmos campos do `items.yml`. Para pegar o item depois:

```php
$item = CustomiesItemFactory::getInstance()->get("meuplugin:espada", 1);
```

## Limites conhecidos

- Os itens precisam ser registrados no `onEnable`; depois disso a tabela de itens já foi enviada.
- Ao remover um item do `items.yml`, as cópias que já estavam em inventários viram item desconhecido.
- O `digger` só muda a velocidade que o **cliente** prevê. Quem decide quando o bloco quebra é o
  servidor, pelos campos `tool`, `tool_tier` e `efficiency`.
- O gerador de resource pack cuida de ícones; modelos 3D, sons e traduções ficam por sua conta.
- A armadura customizada não aparece vestida no corpo do jogador: falta gerar o attachable.
- O arco não troca de ícone enquanto é puxado, e o jogador precisa ter a munição no inventário
  para puxar, mesmo com `consume_ammo: false` — quem decide se o arco pode ser puxado é o cliente.
