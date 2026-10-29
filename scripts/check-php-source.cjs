// Optional static check: PHP_PARSER_PATH=/path/to/php-parser node scripts/check-php-source.cjs
const fs=require('node:fs'),path=require('node:path');
const Parser=require(process.env.PHP_PARSER_PATH||'php-parser');let parsed=0,errors=[];
function walk(dir){for(const item of fs.readdirSync(dir,{withFileTypes:true})){if(['vendor','node_modules','storage','cache'].includes(item.name))continue;const file=path.join(dir,item.name);if(item.isDirectory())walk(file);else if(file.endsWith('.php')&&!file.endsWith('.blade.php')){try{new Parser.Engine({parser:{version:'8.3'}}).parseCode(fs.readFileSync(file,'utf8'),file);parsed++;}catch(e){errors.push(file+': '+e.message);}}}}
walk('laravel');console.log(JSON.stringify({parsed,errors},null,2));if(errors.length)process.exit(1);
