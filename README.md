# CustomItems

Plugin de PocketMine-MP para criar itens customizados escrevendo YAML, sem precisar de código.
Toda a parte de rede e de tabela de itens é feita pelo [Customies](https://github.com/CustomiesDevs/Customies),
que vem **embutido** — não é preciso instalar nada além deste plugin.

> Esta é a versão **com libs**. Existe também a `CustomItems-NoLibs`, que usa o Customies
> instalado no servidor. Use uma ou outra, nunca as duas.

## Instalação

1. Coloque a pasta `CustomItems` em `plugins/`.
2. Suba o servidor uma vez para gerar a pasta de dados do plugin.
3. Jogue os PNGs dos ícones na subpasta `textures/`.
4. Descreva os itens no `items.yml` e reinicie.

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

## Usando junto com o CustomBlocks

As duas versões **com libs** não convivem: cada uma traz sua própria cópia do Customies, e duas
cópias brigam pela palette que vai para o cliente. Se as duas estiverem instaladas, a primeira a
ligar se recusa e o servidor não sobe.

Para rodar blocos e itens no mesmo servidor, use as versões **sem libs** dos dois junto do plugin
Customies avulso:

```
plugins/
├── Customies/
├── CustomBlocks-NoLibs/
└── CustomItems-NoLibs/
```

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

O `items.yml` que vem junto traz um exemplo de cada tipo e documenta todos os campos.

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

## O Customies embutido

A biblioteca fica em [`src/Unknown/CustomItems/libs/customiesdevs/customies/`](src/Unknown/CustomItems/libs/customiesdevs/customies/),
com o namespace reescrito para `Unknown\CustomItems\libs\...` — o mesmo esquema de virion que o
Poggit usa. É o código do Customies 1.4.0 sem alterações, menos o `Customies.php` (o entrypoint de
plugin, que não faz sentido numa lib). O que ele fazia passou para o `onEnable` do CustomItems:
registrar o `CustomiesListener`, que liga o experimento `data_driven_items`.

O Customies é MIT; a licença original está junto em `libs/customiesdevs/customies/LICENSE`.

Para atualizar a lib: copie o `src/` da versão nova por cima e rode

```bash
find src/Unknown/CustomItems/libs -name "*.php" -exec \
  sed -i 's/\bcustomiesdevs\\customies\b/Unknown\\CustomItems\\libs\\customiesdevs\\customies/g' {} +
rm src/Unknown/CustomItems/libs/customiesdevs/customies/Customies.php
```

## Limites conhecidos

- Os itens precisam ser registrados no `onEnable`; depois disso a tabela de itens já foi enviada.
- Ao remover um item do `items.yml`, as cópias que já estavam em inventários viram item desconhecido.
- O `digger` só muda a velocidade que o **cliente** prevê. Quem decide quando o bloco quebra é o
  servidor, pelos campos `tool`, `tool_tier` e `efficiency`.
- O gerador de resource pack cuida de ícones; modelos 3D, sons e traduções ficam por sua conta.
