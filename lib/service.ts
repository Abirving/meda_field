import { AppError, id, sha256, addisDate, datePlus, kickoff, validateSlot, normalizePhone, cleanText, csvCell, DEMO_ACCOUNTS, slotHours, timeLabel, type Person, type Pitch, type Booking, type Slot } from './domain.ts';
import { seed } from './seed.ts';

type Row=Record<string,any>;
const BOOKING_SELECT='SELECT b.*,f.name AS field_name,f.subcity,f.art,f.format,u.name AS player_name,u.phone,d.status AS dispute_status FROM bookings b JOIN fields f ON f.id=b.field_id JOIN users u ON u.id=b.user_id LEFT JOIN disputes d ON d.booking_id=b.id ';
export class TurfService {
  db:D1Database; space:string; now:number;
  constructor(db:D1Database,space:string,now=Date.now()) { this.db=db; this.space=space; this.now=now; }
  statement(sql:string,...args:any[]) { return this.db.prepare(sql).bind(...args); }
  async one<T=Row>(sql:string,...args:any[]) { return this.statement(sql,...args).first<T>(); }
  async all<T=Row>(sql:string,...args:any[]) { return (await this.statement(sql,...args).all<T>()).results; }
  async init() { await seed(this.db,this.space,this.now); }
  async user(required=false,roles:string[]=[]) {
    const user=await this.one<Person>('SELECT u.* FROM users u JOIN spaces s ON s.active_user_id=u.id WHERE s.id=? AND u.space=?',this.space,this.space);
    if(required && !user) throw new AppError(401,'Sign in to continue.');
    if(user?.status==='suspended') throw new AppError(403,'This demo account is suspended. Switch accounts to continue.');
    if(user && roles.length && !roles.includes(user.role)) throw new AppError(403,'This action is not available for your role.');
    return user;
  }
  audit(actor:string,action:string,detail:string) { return this.statement('INSERT INTO audits (id,space,actor,action,detail,created_at) VALUES (?,?,?,?,?,?)',id(),this.space,actor,action,detail,this.now); }
  async field(fid:string) {
    const pitch=await this.one<Pitch>('SELECT * FROM fields WHERE id=? AND space=?',fid,this.space);
    if(!pitch) throw new AppError(404,'Pitch not found.');
    return pitch;
  }
  async booking(bid:string) {
    const b=await this.one<Booking>(BOOKING_SELECT+'WHERE b.id=? AND b.space=?',bid,this.space);
    if(!b)throw new AppError(404,'Booking not found.');
    return b;
  }
  async bootstrap() {
    const space=await this.one('SELECT commission FROM spaces WHERE id=?',this.space);
    const user=await this.one<Person>('SELECT u.* FROM users u JOIN spaces s ON s.active_user_id=u.id WHERE s.id=?',this.space);
    return {user,fields:await this.all<Pitch>('SELECT * FROM fields WHERE space=? AND active=1 ORDER BY art',this.space),today:addisDate(this.now),maxDate:datePlus(addisDate(this.now),30),commission:space!.commission,demo:true,accounts:DEMO_ACCOUNTS};
  }
  async signIn(body:Row) {
    let user:Person|null=null;
    if(body.mode==='staff') {
      const account=DEMO_ACCOUNTS.find(a=>a.email===body.email && a.role!=='player');
      if(!account||body.password!=='MedaDemo!2026')throw new AppError(401,'Use the listed demo staff credentials.');
      user=await this.one<Person>('SELECT * FROM users WHERE space=? AND national_id=?',this.space,account.national_id);
    } else {
      const fan=String(body.national_id??'');
      if(!/^\d{10}$/.test(fan))throw new AppError(400,'Enter a 10-digit test FAN. Do not use a real National ID.');
      user=await this.one<Person>('SELECT * FROM users WHERE space=? AND national_id=?',this.space,fan);
      if(user&&user.role!=='player')throw new AppError(403,'Staff must use email and password.');
      if(!user) {
        const uid=id();
        await this.statement("INSERT INTO users (id,space,phone,name,role,national_id,created_at) VALUES (?,?,?,?,'player',?,?) ON CONFLICT(space,national_id) DO NOTHING",uid,this.space,'DEMO-'+fan,'Test Citizen '+fan.slice(-4),fan,this.now).run();
        user=await this.one<Person>('SELECT * FROM users WHERE space=? AND national_id=?',this.space,fan);
      }
    }
    if(!user||user.status!=='active')throw new AppError(403,'This account is unavailable.');
    await this.db.batch([this.statement('UPDATE spaces SET active_user_id=? WHERE id=?',user.id,this.space),this.audit(user.name,'mock_signin','Demo authentication; no National ID service contacted.')]);
    return {user};
  }
  async expireHolds() { await this.statement("DELETE FROM slot_locks WHERE kind='hold' AND expires_at<=? AND field_id IN (SELECT id FROM fields WHERE space=?)",this.now,this.space).run(); }
  async availability(fid:string,date:string) {
    const field=await this.field(fid);
    if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||!Number.isFinite(Date.parse(date+'T12:00:00Z'))||new Date(date+'T12:00:00Z').toISOString().slice(0,10)!==date||date<addisDate(this.now)||date>datePlus(addisDate(this.now),30))throw new AppError(400,'Choose a real date within the next 30 days.');
    await this.expireHolds();
    const locks=await this.all('SELECT hour,kind FROM slot_locks WHERE field_id=? AND date=?',fid,date);
    const slots:Slot[]=slotHours(field.opens,field.closes).map(hour=>{
      const lock=locks.find(l=>l.hour===hour);
      return {hour,price:hour>=field.peak_start?field.peak_price:field.price,status:kickoff(date,hour)<=this.now?'past':!field.active?'blocked':lock?(lock.kind==='blocked'?'blocked':lock.kind==='hold'?'hold':'booked'):'available'};
    });
    return {field,date,slots,server_time:this.now};
  }
  async holdSlot(body:Row) {
    const user=(await this.user(true,['player']))!,f=await this.field(String(body.field_id));
    if(!f.active)throw new AppError(400,'This field is under maintenance.');
    validateSlot(body.date,body.hour,f.opens,f.closes,this.now);
    await this.expireHolds();
    const existing=await this.one('SELECT * FROM slot_locks WHERE field_id=? AND date=? AND hour=?',f.id,body.date,body.hour);
    if(existing?.kind==='hold'&&existing.user_id===user.id)return {hold:existing};
    const hid=id();
    try{await this.statement("INSERT INTO slot_locks (id,field_id,date,hour,kind,user_id,expires_at) VALUES (?,?,?,?,'hold',?,?)",hid,f.id,body.date,body.hour,user.id,this.now+300000).run();}
    catch(error){if(await this.one('SELECT id FROM slot_locks WHERE field_id=? AND date=? AND hour=?',f.id,body.date,body.hour))throw new AppError(409,'That slot is booked, blocked, or on hold. Choose another time.');throw error;}
    const held=await this.one('SELECT * FROM slot_locks WHERE id=?',hid);if(!held)throw new AppError(409,'Hold was released. Try again.');return {hold:held};
  }
  async releaseHold(hid:string) {
    const user=(await this.user(true,['player']))!;
    await this.statement("DELETE FROM slot_locks WHERE id=? AND user_id=? AND kind='hold'",hid,user.id).run();
    return {ok:true};
  }
  async createBooking(body:Row) {
    const user=(await this.user(true,['player']))!;
    const key=cleanText(body.idempotency_key,12,100,'Request key');
    const prior=await this.one('SELECT id FROM bookings WHERE space=? AND user_id=? AND idempotency_key=?',this.space,user.id,key);
    if(prior)return {booking:await this.booking(prior.id),replayed:true};
    const hold=await this.one("SELECT l.* FROM slot_locks l JOIN fields f ON f.id=l.field_id WHERE l.id=? AND l.user_id=? AND l.kind='hold' AND l.expires_at>? AND f.space=?",String(body.hold_id),user.id,this.now,this.space);
    if(!hold){const replay=await this.one('SELECT id FROM bookings WHERE space=? AND user_id=? AND idempotency_key=?',this.space,user.id,key);if(replay)return {booking:await this.booking(replay.id),replayed:true};throw new AppError(409,'Your five-minute hold expired. Select a slot again.');}
    const field=await this.field(hold.field_id);
    validateSlot(hold.date,hold.hour,field.opens,field.closes,this.now);
    if(!field.active)throw new AppError(409,'This field is under maintenance. Choose another field.');
    const team=cleanText(body.team,2,60,'Team name');
    if(body.method!=='telebirr')throw new AppError(400,'Meda Pass uses Telebirr.');
    if(!['success','failed'].includes(body.simulation))throw new AppError(400,'Choose a demo payment result.');
    const success=body.simulation==='success',bid=id(),reference='MP-'+id().replaceAll('-','').slice(0,10).toUpperCase();
    const price=hold.hour>=field.peak_start?field.peak_price:field.price;
    const statements=[this.statement(`INSERT INTO bookings (id,space,user_id,field_id,date,hour,amount,commission,status,payment_status,payment_method,team,reference,token,idempotency_key,created_at,credit_used,paid_amount)
      SELECT ?,?,u.id,l.field_id,l.date,l.hour,?,ROUND(?*s.commission/100.0),?,?,'telebirr',?,?,?,?,?,CASE WHEN ? THEN MIN(u.store_credit,?) ELSE 0 END,CASE WHEN ? THEN ?-MIN(u.store_credit,?) ELSE 0 END
      FROM slot_locks l JOIN users u ON u.id=l.user_id JOIN spaces s ON s.id=u.space JOIN fields f ON f.id=l.field_id
      WHERE l.id=? AND l.kind='hold' AND l.expires_at>? AND u.id=? AND f.active=1`,bid,this.space,price,price,success?'confirmed':'payment_failed',success?'simulated_paid':'simulated_failed',team,reference,'MP:'+id(),key,this.now,success?1:0,price,success?1:0,price,price,hold.id,this.now,user.id)];
    if(success) {
      statements.push(this.statement('UPDATE users SET store_credit=store_credit-COALESCE((SELECT credit_used FROM bookings WHERE id=?),0) WHERE id=?',bid,user.id));
      statements.push(this.statement("UPDATE slot_locks SET kind='booking',booking_id=?,expires_at=NULL WHERE id=? AND kind='hold' AND EXISTS(SELECT 1 FROM bookings WHERE id=?)",bid,hold.id,bid));
    } else statements.push(this.statement("DELETE FROM slot_locks WHERE id=? AND kind='hold' AND EXISTS(SELECT 1 FROM bookings WHERE id=?)",hold.id,bid));
    statements.push(this.statement('INSERT INTO audits (id,space,actor,action,detail,created_at) SELECT ?,?,?,?,?,? WHERE EXISTS(SELECT 1 FROM bookings WHERE id=?)','payment:'+hold.id,this.space,user.name,success?'booking_paid':'payment_failed',reference,this.now,bid));
    try{await this.db.batch(statements);}catch(error){
      const replay=await this.one('SELECT id FROM bookings WHERE space=? AND user_id=? AND idempotency_key=?',this.space,user.id,key);
      if(replay)return {booking:await this.booking(replay.id),replayed:true};
      if(await this.one('SELECT id FROM audits WHERE id=?','payment:'+hold.id))throw new AppError(409,'This hold has already been used.');
      throw error;
    }
    if(!await this.one('SELECT id FROM bookings WHERE id=?',bid)){const replay=await this.one('SELECT id FROM bookings WHERE space=? AND user_id=? AND idempotency_key=?',this.space,user.id,key);if(replay)return {booking:await this.booking(replay.id),replayed:true};throw new AppError(409,'This hold expired or was already used. Select another slot.');}
    return {booking:await this.booking(bid)};
  }
  async myBookings() {
    const user=(await this.user(true))!;
    return {bookings:await this.all<Booking>(BOOKING_SELECT+'WHERE b.space=? AND b.user_id=? ORDER BY b.date DESC,b.hour DESC',this.space,user.id)};
  }
  async cancelBooking(bid:string) {
    const user=(await this.user(true,['player']))!,b=await this.booking(bid);
    if(b.user_id!==user.id)throw new AppError(403,'This booking belongs to another player.');
    if(b.status==='cancelled')return {booking:b};
    if(b.status!=='confirmed')throw new AppError(400,'This booking cannot be cancelled.');
    if(kickoff(b.date,b.hour)-this.now<=7200000)throw new AppError(400,'Cancel more than two hours before the start. No cancellation at the two-hour boundary.');
    try{await this.db.batch([
      this.statement("INSERT INTO credits (id,user_id,booking_id,amount,reason,created_at) SELECT ?,user_id,id,amount,'cancellation',? FROM bookings WHERE id=? AND status='confirmed'",'credit:'+bid,this.now,bid),
      this.statement("UPDATE users SET store_credit=store_credit+(SELECT amount FROM credits WHERE booking_id=?) WHERE id=? AND EXISTS(SELECT 1 FROM bookings WHERE id=? AND status='confirmed')",bid,user.id,bid),
      this.statement("UPDATE bookings SET status='cancelled',payment_status='store_credit' WHERE id=? AND status='confirmed'",bid),
      this.statement("DELETE FROM slot_locks WHERE booking_id=? AND EXISTS(SELECT 1 FROM bookings WHERE id=? AND status='cancelled')",bid,bid),
      this.audit(user.name,'booking_cancelled',b.reference+' · Store credit '+b.amount+' ETB; no cash reversal'),
    ]);}catch(error){if((await this.booking(bid)).status!=='cancelled')throw error;}
    return {booking:await this.booking(bid)};
  }
  scope(user:Person,alias='f'):{sql:string;args:any[]} {
    if(user.role==='admin')return {sql:'1=1',args:[]};
    if(user.role==='subcity')return {sql:alias+'.subcity=?',args:[user.subcity]};
    if(user.role==='woreda')return {sql:alias+'.subcity=? AND '+alias+'.woreda=?',args:[user.subcity,user.woreda]};
    if(user.role==='operator')return {sql:alias+'.operator_id=?',args:[user.id]};
    return {sql:alias+'.owner_id=?',args:[user.id]};
  }
  async opsDashboard() {
    const user=(await this.user(true,['operator','woreda','subcity','admin']))!,scope=this.scope(user);
    return {user,fields:await this.all('SELECT f.* FROM fields f WHERE f.space=? AND '+scope.sql,this.space,...scope.args),bookings:await this.all(BOOKING_SELECT+'WHERE b.space=? AND '+scope.sql+' ORDER BY b.date,b.hour',this.space,...scope.args)};
  }
  async maintenance(fid:string,body:Row) {
    const user=(await this.user(true,['woreda','subcity','admin']))!,scope=this.scope(user);
    if(!await this.one('SELECT f.id FROM fields f WHERE f.id=? AND f.space=? AND '+scope.sql,fid,this.space,...scope.args))throw new AppError(403,'This field is outside your jurisdiction.');
    if(typeof body.active!=='boolean')throw new AppError(400,'Set an active status.');
    await this.db.batch([this.statement('UPDATE fields SET active=? WHERE id=?',body.active?1:0,fid),this.audit(user.name,'field_status_changed',fid+' · '+(body.active?'ACTIVE':'MAINTENANCE'))]);
    return {ok:true};
  }
  async reportIssue(bid:string,body:Row) {
    const user=(await this.user(true,['player']))!,b=await this.booking(bid);
    if(b.user_id!==user.id)throw new AppError(403,'This booking belongs to another player.');
    if(b.status==='payment_failed')throw new AppError(400,'No payment was collected for this attempt.');
    const reason=cleanText(body.reason,10,1000,'Description');
    if(await this.one('SELECT id FROM disputes WHERE booking_id=?',bid))throw new AppError(409,'An issue has already been reported for this booking.');
    try { await this.db.batch([
      this.statement("INSERT INTO disputes (id,space,booking_id,user_id,reason,status,created_at) VALUES (?,?,?,?,?,'open',?)",id(),this.space,bid,user.id,reason,this.now),
      this.audit(user.name,'dispute_opened',b.reference),
    ]); } catch(error) { if(await this.one('SELECT id FROM disputes WHERE booking_id=?',bid))throw new AppError(409,'An issue has already been reported for this booking.'); throw error; }
    return {ok:true};
  }
  async ownerDashboard() {
    const user=(await this.user(true,['owner']))!;
    return {
      fields:await this.all<Pitch>('SELECT * FROM fields WHERE space=? AND owner_id=? ORDER BY art',this.space,user.id),
      bookings:await this.all<Booking>(BOOKING_SELECT+'WHERE b.space=? AND f.owner_id=? ORDER BY b.date DESC,b.hour DESC',this.space,user.id),
      commission:(await this.one('SELECT commission FROM spaces WHERE id=?',this.space))!.commission,
    };
  }
  async editField(fid:string,body:Row) {
    const user=(await this.user(true,['owner']))!,f=await this.field(fid);
    if(f.owner_id!==user.id)throw new AppError(403,'You can only edit your own pitches.');
    const name=cleanText(body.name,3,60,'Pitch name'),description=cleanText(body.description,20,600,'Description');
    for(const value of [body.price,body.peak_price])if(!Number.isInteger(value)||value<100||value>20000)throw new AppError(400,'Hourly prices must be whole birr between 100 and 20,000.');
    await this.db.batch([
      this.statement('UPDATE fields SET name=?,description=?,price=?,peak_price=? WHERE id=?',name,description,body.price,body.peak_price,fid),
      this.audit(user.name,'field_updated',name+' · Standard '+body.price+' ETB / Peak '+body.peak_price+' ETB'),
    ]);
    return {field:await this.field(fid)};
  }
  async blockSlot(fid:string,body:Row) {
    await this.expireHolds();
    const user=(await this.user(true,['owner']))!,f=await this.field(fid);
    if(f.owner_id!==user.id)throw new AppError(403,'You can only manage your own pitches.');
    validateSlot(body.date,body.hour,f.opens,f.closes,this.now);
    if(body.action==='unblock') {
      await this.db.batch([
        this.statement("DELETE FROM slot_locks WHERE field_id=? AND date=? AND hour=? AND kind='blocked'",fid,body.date,body.hour),
        this.audit(user.name,'slot_opened',f.name+' · '+body.date+' '+body.hour+':00'),
      ]);
    } else if(body.action==='block') {
      const reason=cleanText(body.reason??'Pitch maintenance',3,100,'Reason');
      try { await this.db.batch([
        this.statement("INSERT INTO slot_locks (id,field_id,date,hour,kind,reason) VALUES (?,?,?,?,'blocked',?)",id(),fid,body.date,body.hour,reason),
        this.audit(user.name,'slot_blocked',f.name+' · '+body.date+' '+body.hour+':00 · '+reason),
      ]); } catch(error) { if(await this.one('SELECT id FROM slot_locks WHERE field_id=? AND date=? AND hour=?',fid,body.date,body.hour))throw new AppError(409,'This slot is already booked or blocked.'); throw error; }
    } else throw new AppError(400,'Choose block or unblock.');
    return this.availability(fid,body.date);
  }
  async checkIn(body:Row) {
    const user=(await this.user(true,['operator','owner']))!;
    const token=cleanText(body.token,6,100,'Entry code');
    const b=await this.one<Booking>(BOOKING_SELECT+'WHERE b.space=? AND b.token=? AND '+(user.role==='operator'?'f.operator_id=?':'f.owner_id=?'),this.space,token,user.id);
    if(b&&String(body.field_id)!==b.field_id)throw new AppError(400,'Wrong field. Select the gate you are operating.');
    if(!b)throw new AppError(404,'Entry code not found for your pitches.');
    if(b.status!=='confirmed')throw new AppError(400,b.status==='checked_in'?'This pass has already been used.':'This pass is not valid.');
    const start=kickoff(b.date,b.hour);
    if(this.now<start||this.now>=start+3600000)throw new AppError(400,'Check-in is allowed only during the booked 60-minute play time.');
    if(b.payment_status!=='simulated_paid')throw new AppError(400,'This booking is not paid.');
    let result;
    try { result=await this.db.batch([
      this.statement("UPDATE bookings SET status='checked_in',checked_at=? WHERE id=? AND status='confirmed' RETURNING id",this.now,b.id),
      this.statement('INSERT INTO audits (id,space,actor,action,detail,created_at) VALUES (?,?,?,?,?,?)','checkin:'+b.id,this.space,user.name,'player_checked_in',b.reference,this.now),
    ]); } catch(error) {
      if(await this.one('SELECT id FROM audits WHERE id=?','checkin:'+b.id))throw new AppError(409,'This pass has already been used.');
      throw error;
    }
    if(!result[0].results.length)throw new AppError(409,'This pass has already been used.');
    return {booking:await this.booking(b.id)};
  }
  async adminDashboard() {
    await this.user(true,['admin']);
    const bookings=await this.all<Booking>(BOOKING_SELECT+'WHERE b.space=? ORDER BY b.created_at DESC',this.space);
    return {
      bookings,users:await this.all<Person>('SELECT * FROM users WHERE space=? ORDER BY role,name',this.space),
      disputes:await this.all('SELECT d.*,b.reference,b.amount,b.status AS booking_status,u.name AS player_name,f.name AS field_name FROM disputes d JOIN bookings b ON b.id=d.booking_id JOIN users u ON u.id=d.user_id JOIN fields f ON f.id=b.field_id WHERE d.space=? ORDER BY d.created_at DESC',this.space),
      audits:await this.all('SELECT * FROM audits WHERE space=? ORDER BY created_at DESC LIMIT 40',this.space),
      commission:(await this.one('SELECT commission FROM spaces WHERE id=?',this.space))!.commission,
    };
  }
  async setCommission(body:Row) {
    const user=(await this.user(true,['admin']))!;
    if(!Number.isInteger(body.commission)||body.commission<5||body.commission>10)throw new AppError(400,'Commission must be a whole percentage from 5 to 10.');
    await this.db.batch([this.statement('UPDATE spaces SET commission=? WHERE id=?',body.commission,this.space),this.audit(user.name,'commission_changed',body.commission+'% for new bookings')]);
    return {commission:body.commission};
  }
  async setUserStatus(uid:string,body:Row) {
    const actor=(await this.user(true,['admin']))!;
    const user=await this.one<Person>('SELECT * FROM users WHERE id=? AND space=?',uid,this.space);
    if(!user)throw new AppError(404,'User not found.');
    if(user.role==='admin')throw new AppError(400,'Admin accounts cannot be suspended here.');
    if(!['active','suspended'].includes(body.status))throw new AppError(400,'Invalid account status.');
    await this.db.batch([this.statement('UPDATE users SET status=? WHERE id=?',body.status,uid),this.audit(actor.name,'user_status_changed',user.name+' · '+body.status)]);
    return {ok:true};
  }
  async resolveDispute(did:string,body:Row) {
    const user=(await this.user(true,['admin']))!,d=await this.one('SELECT * FROM disputes WHERE id=? AND space=?',did,this.space);
    if(!d)throw new AppError(404,'Issue not found.');
    if(d.status!=='open')throw new AppError(409,'This issue has already been resolved.');
    if(!['resolved','refunded','dismissed'].includes(body.action))throw new AppError(400,'Choose a resolution.');
    const resolution=cleanText(body.resolution,5,600,'Resolution note');
    const b=await this.booking(d.booking_id), statements=[];
    if(body.action==='refunded') {
      if(b.payment_status!=='simulated_paid')throw new AppError(400,'This payment has already been refunded or was not collected.');
      statements.push(this.statement("INSERT INTO credits (id,user_id,booking_id,amount,reason,created_at) VALUES (?,?,?,?,'dispute',?)",'credit:'+b.id,b.user_id,b.id,b.amount,this.now));
      statements.push(this.statement('UPDATE users SET store_credit=store_credit+? WHERE id=?',b.amount,b.user_id));
      statements.push(this.statement("UPDATE bookings SET status='refunded',payment_status='store_credit' WHERE id=? AND payment_status='simulated_paid' AND EXISTS(SELECT 1 FROM disputes WHERE id=? AND status='open')",b.id,did));
      statements.push(this.statement("DELETE FROM slot_locks WHERE booking_id=? AND EXISTS(SELECT 1 FROM bookings WHERE id=? AND status='refunded')",b.id,b.id));
    }
    statements.push(this.statement("UPDATE disputes SET status=?,resolution=?,resolved_at=? WHERE id=? AND status='open'",body.action,resolution,this.now,did));
    statements.push(this.statement('INSERT INTO audits (id,space,actor,action,detail,created_at) VALUES (?,?,?,?,?,?)','resolve:'+did,this.space,user.name,'dispute_'+body.action,b.reference+' · '+resolution,this.now));
    try { await this.db.batch(statements); } catch(error) {
      if(await this.one('SELECT id FROM audits WHERE id=?','resolve:'+did))throw new AppError(409,'This issue has already been resolved.');
      throw error;
    }
    return {ok:true};
  }
  async exportBookings(month:string) {
    const user=(await this.user(true,['owner','admin']))!;
    if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(month))throw new AppError(400,'Choose a valid report month.');
    const records=await this.all<Booking>(BOOKING_SELECT+'WHERE b.space=? AND b.date LIKE ?'+(user.role==='owner'?' AND f.owner_id=?':'')+' ORDER BY b.date,b.hour',...[this.space,month+'%',...(user.role==='owner'?[user.id]:[])]);
    const rows=[['Reference','Pitch','Date (EAT)','Hour (24h)','Team','Status','Payment status','Gross ETB','Commission ETB','Owner net ETB']];
    for(const b of records){const paid=b.payment_status==='simulated_paid'; rows.push([b.reference,b.field_name,b.date,timeLabel(b.hour),b.team,b.status,b.payment_status,String(paid?b.amount:0),String(paid?b.commission:0),String(paid?b.amount-b.commission:0)]);}
    return '\uFEFF'+rows.map(row=>row.map(csvCell).join(',')).join('\r\n');
  }
}
