import {readFileSync,existsSync,mkdirSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
const config=resolve('dist/server/wrangler.json');
if(!existsSync(config))throw new Error('Build first: npm run build');
const journal=JSON.parse(readFileSync('drizzle/meta/_journal.json','utf8'));
const record='.wrangler/turfpass-migrations.json';
const applied=existsSync(record)?JSON.parse(readFileSync(record,'utf8')):{};
for(const entry of journal.entries){
 const path='drizzle/'+entry.tag+'.sql';
 const hash=createHash('sha256').update(readFileSync(path)).digest('hex');
 if(applied[entry.tag]){if(applied[entry.tag]!==hash)throw new Error('Applied migration changed: '+path);continue;}
 const result=spawnSync(process.execPath,['--import','./scripts/sites-env.mjs','./node_modules/wrangler/bin/wrangler.js','d1','execute','DB','--local','--config',config,'--persist-to','.wrangler/state','--file',path],{stdio:'inherit'});
 if(result.status!==0)process.exit(result.status??1);
 applied[entry.tag]=hash;mkdirSync('.wrangler',{recursive:true});writeFileSync(record,JSON.stringify(applied,null,2)+'\n');
}
console.log('Local TurfPass database is ready. Sample data loads on the first visit.');
