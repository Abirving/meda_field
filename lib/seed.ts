import { id, addisDate, datePlus, type Pitch } from './domain.ts';

export async function seed(db:D1Database, space:string, now=Date.now()) {
  if(await db.prepare('SELECT id FROM spaces WHERE id=?').bind(space).first()) return;
  const key=(suffix:string)=>space+':'+suffix;
  const today=addisDate(now);
  const people=[
    ['player','+251911000001','Abel Tadesse','player'],
    ['owner','+251911000002','Hana Bekele','owner'],
    ['admin','+251911000003','Meda Operations','admin'],
    ['player2','+251911000004','Dawit Getachew','player'],
    ['owner2','+251911000005','Meron Tesfaye','owner'],
    ['operator','+251911000006','Bole Gatekeeper','operator'],
    ['woreda','+251911000007','Woreda 03 Supervisor','woreda'],
    ['subcity','+251911000008','Bole Supervisor','subcity'],
  ];
  const pitches=[
    ['bole','Bole Five','Bole','Behind the Atlas neighborhood, Bole','Your after-work home ground. A lively five-a-side pitch with a soft artificial surface, bright floodlights, and a shaded sideline for your friends.','5-a-side',800,1000,0,'4.9',48,'owner','Floodlights,Parking,Changing rooms'],
    ['atlas','Atlas Arena','Bole','Atlas area, Bole','Room for the whole squad. Our seven-a-side ground offers a wide playing surface and comfortable team benches.','7-a-side',1200,1500,1,'4.8',32,'owner','Floodlights,Parking,Showers'],
    ['cmc','CMC Green','Yeka','CMC neighborhood, Yeka','An easygoing neighborhood ground with a freshly maintained surface and good space along the touchlines.','5-a-side',700,900,2,'4.7',26,'owner2','Floodlights,Changing rooms'],
    ['sarbet','Sarbet Social','Kirkos','Sarbet area, Kirkos','A friendly, compact pitch for lunchtime games and evening rivalries. Bring your team and make it a weekly ritual.','5-a-side',650,850,3,'4.8',41,'owner','Floodlights,Parking'],
    ['summit','Summit Grounds','Bole','Summit neighborhood, Bole','A spacious seven-a-side pitch for longer passes, full-team sessions, and weekend football.','7-a-side',1000,1300,4,'4.6',19,'owner2','Floodlights,Parking,Changing rooms'],
    ['megenagna','Megenagna Arena','Yeka','Megenagna area, Yeka','An accessible community ground for a quick game with friends. Equipment and a warm welcome are waiting.','5-a-side',600,800,5,'4.7',23,'owner2','Floodlights,Showers'],
  ] as const;
  const statements=[db.prepare('INSERT INTO spaces (id,commission,created_at) VALUES (?,8,?)').bind(space,now)];
  for(let i=0;i<people.length;i++){const p=people[i];statements.push(db.prepare('INSERT INTO users (id,space,phone,name,role,created_at,national_id,subcity,woreda) VALUES (?,?,?,?,?,?,?,?,?)').bind(key(p[0]),space,p[1],p[2],p[3],now,'100000000'+(i+1),['operator','woreda','subcity'].includes(p[0])?'Bole':null,p[0]==='woreda'?'03':null));}
  for(const p of pitches) statements.push(db.prepare('INSERT INTO fields (id,space,owner_id,name,subcity,address,description,format,price,peak_price,peak_start,opens,closes,amenities,rating,reviews,art,verified,active) VALUES (?,?,?,?,?,?,?,?,?,?,17,6,23,?,?,?,?,1,1)').bind(key(p[0]),space,key(p[11]),p[1],p[2],p[3],p[4],p[5],p[6],p[7],p[12],p[9],p[10],p[8]));
  statements.push(db.prepare("UPDATE fields SET operator_id=? WHERE space=? AND subcity='Bole'").bind(key('operator'),space));
  statements.push(db.prepare("UPDATE fields SET woreda='05',sport='BASKETBALL',format='Basketball' WHERE id=?").bind(key('summit')));
  const examples=[
    ['player','bole',datePlus(today,1),18.5,1000,'confirmed','Addis United'],
    ['player2','atlas',today,19.75,1500,'confirmed','Friday Football'],
    ['player2','bole',datePlus(today,1),17.25,1000,'confirmed','The Neighbors'],
    ['player','sarbet',datePlus(today,-3),17.25,850,'checked_in','Addis United'],
    ['player2','bole',datePlus(today,-2),18.5,1000,'checked_in','Friday Football'],
    ['player2','atlas',datePlus(today,-1),16,1200,'checked_in','The Neighbors'],
  ] as const;
  for (let i=0;i<examples.length;i++) {
    const b=examples[i], bid=id(), reference='MP-'+id().replaceAll('-','').slice(0,8).toUpperCase();
    statements.push(db.prepare('INSERT INTO bookings (id,space,user_id,field_id,date,hour,amount,commission,status,payment_status,payment_method,team,reference,token,idempotency_key,created_at,checked_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)').bind(bid,space,key(b[0]),key(b[1]),b[2],b[3],b[4],Math.round(b[4]*.08),b[5],'simulated_paid','telebirr',b[6],reference,'MP:'+id(), 'seed-'+i,now-86400000,b[5]==='checked_in'?now-86400000:null));
    statements.push(db.prepare('INSERT INTO slot_locks (id,field_id,date,hour,kind,booking_id) VALUES (?,?,?,?,?,?)').bind(id(),key(b[1]),b[2],b[3],'booking',bid));
    if(i===3) statements.push(db.prepare('INSERT INTO disputes (id,space,booking_id,user_id,reason,status,created_at) VALUES (?,?,?,?,?,?,?)').bind(id(),space,bid,key('player'),'The floodlights stopped working during the last part of our game. Please review a refund.','open',now-3600000));
  }
  statements.push(db.prepare('INSERT INTO slot_locks (id,field_id,date,hour,kind,reason) VALUES (?,?,?,?,?,?)').bind(id(),key('bole'),datePlus(today,1),12.25,'blocked','Pitch maintenance'));
  statements.push(db.prepare('INSERT INTO audits (id,space,actor,action,detail,created_at) VALUES (?,?,?,?,?,?)').bind(id(),space,'Meda Pass','sandbox_initialized','Sample pitches and bookings added. No real payments.',now));
  try { await db.batch(statements); }
  catch(error) { if(!await db.prepare('SELECT id FROM spaces WHERE id=?').bind(space).first()) throw error; }
}
