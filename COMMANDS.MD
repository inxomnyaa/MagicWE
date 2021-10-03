# Commands
This list is automatically generated. If you have noticed an error, please create an issue.

| Command | Description | Usage | Alias |
|---|---|---|---|
| `//pos1` | Set position 1 | `//pos1 ` | `//1` |
| `//pos2` | Set position 2 | `//pos2 ` | `//2` |
| `//hpos1` | Set position 1 to targeted block | `//hpos1 ` | `//h1` |
| `//hpos2` | Set position 2 to targeted block | `//hpos2 ` | `//h2` |
| `//chunk` | Set the selection to your current chunk | `//chunk ` |  |
| `//palette` | Manage block palettes | `//palette ` |  |
| `//wand` | Gives you the selection wand | `//wand ` |  |
| `//togglewand` | Toggle the wand tool on/off | `//togglewand ` | `//toggleeditwand` |
| `//debug` | Gives you the debug stick, which gives information about the clicked block | `//debug ` |  |
| `//toggledebug` | Toggle the debug stick on/off | `//toggledebug ` |  |
| `//size` | Get information about the selection | `//size ` |  |
| `//count` | Counts the number of blocks matching a mask in selection | `//count [blocks:string] [flags:text]` | `//analyze` |
| `//listchunks` | List chunks that your selection includes | `//listchunks ` |  |
| `//set` | Fill a selection with the specified blocks | `//set <blocks:string> [flags:text]` |  |
| `//replace` | Replace blocks in an area with other blocks | `//replace <findblocks:string> <replaceblocks:string> [flags:text]` |  |
| `//cylinder` | Create a cylinder | `//cylinder <blocks:string> <diameter:int> [height:int] [flags:text]` | `//cyl` |
| `//copy` | Copy the selection to the clipboard | `//copy [flags:text]` |  |
| `//paste` | Paste the clipboard's contents | `//paste [flags:text]` |  |
| `//cut` | Cut the selection to the clipboard | `//cut [flags:text]` |  |
| `//cut2` | Cut the selection to the clipboard - the new way | `//cut2 [flags:text]` |  |
| `//clearclipboard` | Clear your clipboard | `//clearclipboard ` |  |
| `//flip` | Flip the contents of the clipboard across the origin | `//flip <axis:string>` | `//mirror` |
| `//rotate` | Rotate the contents of the clipboard around the origin | `//rotate <angle:int> [aroundOrigin:bool]` |  |
| `//undo` | Rolls back the last action | `//undo ` |  |
| `//redo` | Applies the last undo action again | `//redo ` |  |
| `//clearhistory` | Clear your history | `//clearhistory ` |  |
| `//setrange` | Set tool range | `//setrange [range:int]` | `//toolrange` |
| `//limit` | Set the block change limit. Use -1 to disable | `//limit [limit:int]` |  |
| `//help` | MagicWE help command | `//help [command:string]` | `//?, //mwe, //wehelp` |
| `//version` | MagicWE version | `//version ` | `//ver` |
| `//info` | Information about MagicWE | `//info ` |  |
| `//report` | Report a bug to GitHub | `//report [title:text]` | `//bug, //github` |
| `//donate` | Donate to support development of MagicWE! | `//donate ` | `//support, //paypal` |
| `//language` | Set your language | `//language [language:string]` | `//lang` |
| `//biomelist` | Gets all biomes available | `//biomelist ` | `//biomels` |
| `//biomeinfo` | Get the biome of the targeted block | `//biomeinfo [flags:text]` |  |
| `//setbiome` | Sets the biome of your current block or region | `//setbiome <biome:int>` |  |
| `//calculate` | Evaluate a mathematical expression | `//calculate <expression:text>` | `//calc, //eval, //evaluate, //solve` |
| `//togglewaila` | Toggle the What Am I Looking At utility | `//togglewaila ` | `//waila, //wyla` |
| `//togglesidebar` | Toggle the sidebar | `//togglesidebar ` | `//sidebar` |
| `//placeallblockstates` | Place all blockstates similar to Java debug worlds | `//placeallblockstates ` |  |
| `//testapi` | Internal command for testing API methods | `//testapi ` |  |
| `//generatecommandsmd` | Generates the commands.md file | `//generatecommandsmd ` |  |
| `//brush` | Opens the brush tool menu | `//brush` |  |
| `//brush name` | Get name or rename a brush | `/brush name [name:string]` |  |
| `//flood` | Opens the flood fill tool menu | `//flood ` | `//floodfill` |