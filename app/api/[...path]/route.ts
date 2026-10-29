import { env } from 'cloudflare:workers';
import QRCode from 'qrcode';
import { TurfService } from '../../../lib/service.ts';
import { AppError, sha256 } from '../../../lib/domain.ts';

export const dynamic='force-dynamic';
const json=(body:unknown,status=200)=>Response.json(body,{status,headers:{'Cache-Control':'no-store','X-Content-Type-Options':'nosniff'}});
async function handle(request:Request) {
  try {
    const url=new URL(request.url),parts=url.pathname.replace(/^\/api\/?/,'').split('/').filter(Boolean).map(decodeURIComponent);
    const subject=request.headers.get('oai-authenticated-user-id');
    const local=['localhost','127.0.0.1','terminal.local'].includes(url.hostname);
    if(!subject&&!local)throw new AppError(401,'Sign in with ChatGPT to open your private Meda Pass sandbox.');
    if((env as {TURFPASS_MODE?:string}).TURFPASS_MODE && (env as {TURFPASS_MODE?:string}).TURFPASS_MODE!=='sandbox')throw new AppError(503,'Live providers are not configured. This build supports sandbox mode only.');
    if(!env.DB)throw new AppError(503,'The booking database is not connected.');
    let body:Record<string,any>={};
    if(request.method!=='GET') {
      if(request.headers.get('sec-fetch-site')==='cross-site')throw new AppError(403,'Cross-site requests are not allowed.');
      const origin=request.headers.get('origin');
      const hosts=[url.host,request.headers.get('host'),request.headers.get('x-forwarded-host')].filter(Boolean);
      if(origin&&!hosts.includes(new URL(origin).host))throw new AppError(403,'Request origin is not allowed.');
      if(!request.headers.get('content-type')?.includes('application/json'))throw new AppError(415,'Send JSON.');
      const raw=await request.text();
      if(raw.length>16384)throw new AppError(413,'Request is too large.');
      try { body=JSON.parse(raw||'{}'); } catch { throw new AppError(400,'Invalid JSON.'); }
      if(!body||typeof body!=='object'||Array.isArray(body))throw new AppError(400,'Expected a JSON object.');
    }
    const space='meda_v2_'+(await sha256(subject??'local-meda-pass-sandbox')).slice(0,24);
    const service=new TurfService(env.DB,space);
    await service.init();
    const route=parts.join('/'),get=request.method==='GET';
    if(get&&route==='bootstrap')return json(await service.bootstrap());
    if(!get&&route==='auth/signin')return json(await service.signIn(body));
    if(!get&&route==='holds')return json(await service.holdSlot(body));
    if(!get&&parts[0]==='holds'&&parts[2]==='release')return json(await service.releaseHold(parts[1]));
    if(get&&route==='ops')return json(await service.opsDashboard());
    if(!get&&route==='ops/check-in')return json(await service.checkIn(body));
    if(!get&&parts[0]==='ops'&&parts[1]==='fields')return json(await service.maintenance(parts[2],body));
    if(!get&&route==='auth/logout'){await service.statement('UPDATE spaces SET active_user_id=NULL WHERE id=?',space).run(); return json({ok:true});}
    if(get&&parts[0]==='fields'&&parts[2]==='slots')return json(await service.availability(parts[1],url.searchParams.get('date')??''));
    if(get&&route==='bookings')return json(await service.myBookings());
    if(!get&&route==='bookings')return json(await service.createBooking(body),201);
    if(parts[0]==='bookings'&&parts[1]) {
      if(!get&&parts[2]==='cancel')return json(await service.cancelBooking(parts[1]));
      if(!get&&parts[2]==='dispute')return json(await service.reportIssue(parts[1],body));
      if(get&&parts[2]==='qr') {
        const user=(await service.user(true))!,booking=await service.booking(parts[1]),field=await service.field(booking.field_id);
        if(booking.user_id!==user.id&&user.role!=='admin'&&field.owner_id!==user.id)throw new AppError(403,'This pass belongs to another player.');
        if(!['confirmed','checked_in'].includes(booking.status))throw new AppError(400,'This pass is no longer valid.');
        const svg=await QRCode.toString(booking.token,{type:'svg',errorCorrectionLevel:'M',margin:3,color:{dark:'#173d2f',light:'#ffffff'}});
        return new Response(svg,{headers:{'Content-Type':'image/svg+xml','Cache-Control':'no-store','Content-Disposition':'inline; filename="'+booking.reference+'.svg"','X-Content-Type-Options':'nosniff'}});
      }
    }
    if(get&&route==='owner')return json(await service.ownerDashboard());
    if(!get&&route==='owner/check-in')return json(await service.checkIn(body));
    if(!get&&parts[0]==='owner'&&parts[1]==='fields') {
      if(parts[3]==='slots')return json(await service.blockSlot(parts[2],body));
      return json(await service.editField(parts[2],body));
    }
    if(get&&route==='admin')return json(await service.adminDashboard());
    if(!get&&route==='admin/commission')return json(await service.setCommission(body));
    if(!get&&parts[0]==='admin'&&parts[1]==='users')return json(await service.setUserStatus(parts[2],body));
    if(!get&&parts[0]==='admin'&&parts[1]==='disputes')return json(await service.resolveDispute(parts[2],body));
    if(get&&route==='reports/bookings') {
      const month=url.searchParams.get('month')??'';
      return new Response(await service.exportBookings(month),{headers:{'Content-Type':'text/csv; charset=utf-8','Content-Disposition':'attachment; filename="meda-pass-'+month+'.csv"','Cache-Control':'no-store','X-Content-Type-Options':'nosniff'}});
    }
    throw new AppError(404,'This endpoint was not found.');
  }catch(error){
    if(error instanceof AppError)return json({error:error.message},error.status);
    console.error('Meda Pass request failed:',error instanceof Error?error.message:'Unknown error');
    return json({error:'Something went wrong. Please try again.'},500);
  }
}
export const GET=handle;
export const POST=handle;
