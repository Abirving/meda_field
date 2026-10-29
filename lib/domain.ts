export type Role = 'player' | 'owner' | 'admin' | 'operator' | 'woreda' | 'subcity';
export interface Person { id:string; name:string; phone:string; role:Role; status:string; national_id:string; store_credit:number; subcity:string|null; woreda:string|null }
export interface Pitch { id:string; owner_id:string; name:string; subcity:string; address:string; description:string; format:string; price:number; peak_price:number; peak_start:number; opens:number; closes:number; amenities:string; rating:string; reviews:number; art:number; verified:number; active:number; woreda:string; sport:string; operator_id:string|null }
export interface Booking { id:string; user_id:string; field_id:string; date:string; hour:number; amount:number; credit_used:number; paid_amount:number; commission:number; status:string; payment_status:string; payment_method:string; team:string; reference:string; token:string; created_at:number; checked_at:number|null; field_name:string; subcity:string; art:number; format:string; player_name:string; phone:string; dispute_status?:string|null }
export interface Slot { hour:number; price:number; status:'available'|'booked'|'blocked'|'past'|'hold'; reason?:string }
export const DEMO_ACCOUNTS = [
 {role:'player',name:'Abel Tadesse',phone:'+251911000001',national_id:'1000000001',email:'player@meda.test'},
 {role:'operator',name:'Bole Gatekeeper',phone:'+251911000006',national_id:'1000000006',email:'operator@meda.test'},
 {role:'woreda',name:'Woreda 03 Supervisor',phone:'+251911000007',national_id:'1000000007',email:'woreda@meda.test'},
 {role:'subcity',name:'Bole Supervisor',phone:'+251911000008',national_id:'1000000008',email:'subcity@meda.test'},
 {role:'admin',name:'Meda Operations',phone:'+251911000003',national_id:'1000000003',email:'admin@meda.test'},
 {role:'owner',name:'Hana Bekele',phone:'+251911000002',national_id:'1000000002',email:'owner@meda.test'},
] as const;
export const timeLabel=(hour:number)=>String(Math.floor(hour)).padStart(2,'0')+':'+String(Math.round((hour%1)*60)).padStart(2,'0');
export const slotHours=(opens:number,closes:number)=>Array.from({length:Math.max(0,Math.floor(((closes-opens)*60-60)/75)+1)},(_,i)=>opens+i*1.25);
export class AppError extends Error { status:number; constructor(status:number, message:string) { super(message); this.status=status; } }
export function id() {
  if(typeof crypto.randomUUID==='function')return crypto.randomUUID();
  // getRandomValues also works in an HTTP local preview; randomUUID requires HTTPS.
  const bytes=crypto.getRandomValues(new Uint8Array(16)); bytes[6]=(bytes[6]&15)|64;bytes[8]=(bytes[8]&63)|128;
  const hex=Array.from(bytes,n=>n.toString(16).padStart(2,'0')).join('');
  return hex.slice(0,8)+'-'+hex.slice(8,12)+'-'+hex.slice(12,16)+'-'+hex.slice(16,20)+'-'+hex.slice(20);
}
export const money = (value:number) => new Intl.NumberFormat('en-ET',{maximumFractionDigits:0}).format(value);
export function addisDate(now=Date.now()) { return new Intl.DateTimeFormat('en-CA',{timeZone:'Africa/Addis_Ababa',year:'numeric',month:'2-digit',day:'2-digit'}).format(now); }
export function datePlus(date:string, days:number) { return new Date(Date.parse(date+'T12:00:00+03:00')+days*86400000).toISOString().slice(0,10); }
export const kickoff = (date:string,hour:number) => Date.parse(`${date}T${timeLabel(hour)}:00+03:00`);
export function validateSlot(date:unknown,hour:unknown,opens=6,closes=23,now=Date.now()) {
  if (typeof date!=='string' || !/^\d{4}-\d{2}-\d{2}$/.test(date) || typeof hour!=='number' || !Number.isFinite(hour)) throw new AppError(400,'Choose a valid date and play slot.');
  if (!Number.isFinite(Date.parse(date+'T12:00:00Z')) || new Date(date+'T12:00:00Z').toISOString().slice(0,10)!==date) throw new AppError(400,'Choose a real calendar date.');
  if (date<addisDate(now) || date>datePlus(addisDate(now),30)) throw new AppError(400,'Choose a date within the next 30 days.');
  if ((hour as number)<opens || (hour as number)+1>closes || !slotHours(opens,closes).includes(hour as number) || kickoff(date,hour as number)<=now) throw new AppError(400,'This slot has passed or is outside opening hours.');
}
export function normalizePhone(input:unknown) {
  let value=String(input??'').replace(/[\s()-]/g,'');
  if(/^0[79]\d{8}$/.test(value)) value='+251'+value.slice(1);
  if(/^251[79]\d{8}$/.test(value)) value='+'+value;
  if(!/^\+251[79]\d{8}$/.test(value)) throw new AppError(400,'Enter an Ethiopian mobile number, for example 0911 123 456.');
  return value;
}
export function cleanText(input:unknown,min:number,max:number,label:string) {
  if(typeof input!=='string' || input.trim().length<min || input.trim().length>max) throw new AppError(400,`${label} must be ${min}–${max} characters.`);
  return input.trim();
}
export function csvCell(value:unknown) { let s=String(value??''); if(/^[\s]*[=+@-]/.test(s))s="'"+s; return '"'+s.replaceAll('"','""')+'"'; }
export async function sha256(value:string) { return Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(value)))).map(n=>n.toString(16).padStart(2,'0')).join(''); }
