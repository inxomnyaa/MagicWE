![MagicWE2's awesome wide banner!](/resources/magicwe_icon_wide.png)
<img src="/resources/magicwe_icon_wide.png" />
---
# MagicWE2
Lag free asynchronous world editor for [PMMP](https://github.com/pmmp/PocketMine-MP)

Try the new MagicWE, now way more powerful, with more support, more commands, new tools and more!

[![Poggit-CI](https://poggit.pmmp.io/ci.badge/thebigsmileXD/MagicWE2/MagicWE2/master)](https://poggit.pmmp.io/ci/thebigsmileXD/MagicWE2)
[![](https://poggit.pmmp.io/shield.state/MagicWE2)](https://poggit.pmmp.io/p/MagicWE2)
[![](https://poggit.pmmp.io/shield.api/MagicWE2)](https://poggit.pmmp.io/p/MagicWE2)
![PHPStan](https://github.com/thebigsmileXD/MagicWE2/workflows/PHPStan/badge.svg)
## Why MagicWE2?
_Rainbow sprinkles!_

Jokes aside, here is a list of pros:

- Simple usage
- Translations
- Good performance and great speeds
- Progress bars like on Windows 98!
- Sessions
- Clipboards
- Optimized item / block parsing - you can place any block, by id, name, and item!
- Alot more commands
- Command auto-completion
- Command flags (i.e. -p for relative copying/pasting, -h for hollow objects)
- UI for brush setup and configuration
- Fancy icon and optional startup ASCII art
- Direct bug reporting to GitHub
<!-- 
- schematic support
- MyPlot support
-->

## Commands
| Command | Alias | Description | Usage |
| --- | --- | --- | --- |
| `//pos1` | `//1` | `Select first position` | `//pos1` |
| `//pos2` | `//2` | `Select second position` | `//pos2` |
| `//set` | `//fill` | `Fill an area with the specified blocks` | `//set <blocks:string> [flags:text]` |
| `//replace` | | `Replace blocks in an area with other blocks` | `//replace <findblocks:string> <replaceblocks:string> [flags:text]` |
| `//copy` | | `Copy an area into a clipboard` | `//copy [flags:text]` |
| `//paste` |  | `Paste your clipboard` | `//paste [flags:text]` |
| `//wand` |  | `Gives you the selection wand` | `//wand` |
| `//togglewand` |  | `Toggle the wand tool on/off` | `//togglewand` |
| `//undo` |  | `Rolls back the last action` | `//undo` |
| `//redo` |  | `Applies the last undo action again` | `//redo` |
| `//debug` |  | `Gives you the debug stick, which gives information about the clicked block` | `//debug` |
| `//toggledebug` |  | `Toggle the debug stick on/off` | `//toggledebug` |
| `//cylinder` | `//cyl` | `Create a cylinder` | `//cylinder <blocks:string> <diameter:int> [height:int] [flags:text]` |
| `//count` | `//analyze` | `Count blocks in selection` | `//count [blocks:string] [flags:text]` |
| `//help` | `//?,//mwe,//wehelp` | `MagicWE help command` | `//help [command:string]` |
| `//version` | `//ver` | `MagicWE version` | `//version` |
| `//info` |  | `Information about MagicWE` | `//info` |
| `//report` | `//bug,//github` | `Report a bug to GitHub` | `//report [title:text]` |
| `//donate` | `//support,//paypal` | `Donate to support development of MagicWE!` | `//donate` |
| `//brush` |  | `Opens the brush tool menu` | `//brush` |
| `//flood` |  | `Opens the flood tool menu` | `//flood` |

## Planned features
- Saved sessions (saved brushes and clipboards)
- More commands, a glimpse at the plugin.yml should give you a good look what is coming up
- Command based flags, since they are currently in a global state
- Schematic and structure block data support
- Clipboard naming, exporting and switching
- ScoreboardAPI integration
- Better and more brushes. For now i suggest using [BlockSniper](https://github.com/BlockHorizons/BlockSniper) for brushes!
- [MyPlot](https://github.com/jasonwynn10/MyPlot) integration

## Fast updates
You have an urgent issue, your server is crashing or players mess with the world and start griefing?

Consider using //report to create a pre-filled GitHub issue!

Feel free to open issues, feature requests and criticism are welcome!

If you have an urgent issue, tag me on Twitter for faster response time: [@xenialdan](https://twitter.com/xenialdan)

## Quotes
- _"MagicWE2 has a new fresh coating over the plugin, with rainbow colored sprinkle topping!"_ ~ XenialDan, 2017

### Foot notes
License: GNU GENERAL PUBLIC LICENSE

Readme last updated: 4th August 2019
