'use client';
import {useEffect,useRef,type ReactNode} from 'react';
import {X,LoaderCircle,Check,MapPin,CalendarDays,ArrowUpRight,Ticket,ShieldCheck,Clock3} from 'lucide-react';
import type {Booking} from '../lib/domain.ts';
import {money,kickoff,timeLabel} from '../lib/domain.ts';

export const hourLabel=timeLabel;
export const prettyDate=(date:string,short=false)=>new Intl.DateTimeFormat('en-GB',{day:'numeric',month:short?'short':'long',...(short?{}:{weekday:'long' as const})}).format(new Date(date+'T12:00:00+03:00'));
export const statusLabel=(b:Booking)=>b.status==='confirmed'&&kickoff(b.date,b.hour)+3600000<Date.now()?'Completed':({confirmed:'Confirmed',checked_in:'Checked in',cancelled:'Cancelled',refunded:'Credited',payment_failed:'Payment failed'}[b.status]??b.status);
export async function api(path:string,body?:unknown){
  const response=await fetch('/api/'+path,{method:body===undefined?'GET':'POST',headers:body===undefined?{}:{'Content-Type':'application/json'},body:body===undefined?undefined:JSON.stringify(body)});
  const data=await response.json() as any;
  if(!response.ok)throw new Error(data.error??'The request failed. Please try again.');
  return data;
}
export function Modal({title,kicker,children,onClose,wide=false}:{title:string;kicker?:string;children:ReactNode;onClose:()=>void;wide?:boolean}){
  const ref=useRef<HTMLDialogElement>(null);
  useEffect(()=>{const dialog=ref.current!;dialog.showModal();const prev=document.body.style.overflow;document.body.style.overflow='hidden';return()=>{dialog.close();document.body.style.overflow=prev;};},[]);
  return <dialog ref={ref} className={'modal '+(wide?'modal-wide':'')} onCancel={e=>{e.preventDefault();onClose();}} onClick={e=>{if(e.target===e.currentTarget)onClose();}}>
    <div className="modal-head"><div>{kicker&&<p className="eyebrow">{kicker}</p>}<h2>{title}</h2></div><button aria-label="Close dialog" className="icon-button" onClick={onClose}><X size={20}/></button></div>
    {children}
  </dialog>;
}
export function Spinner(){return <span className="loading"><LoaderCircle size={22} className="spin"/> Loading your matchday…</span>;}
export function Empty({title,text,action}:{title:string;text:string;action?:ReactNode}){return <div className="empty"><div className="empty-icon"><Ticket size={28}/></div><h3>{title}</h3><p>{text}</p>{action}</div>;}
export function Badge({booking}:{booking:Booking}){return <span className={'status '+booking.status}>{['confirmed','checked_in'].includes(booking.status)&&<Check size={12}/>} {statusLabel(booking)}</span>;}
export function Metric({label,value,caption,icon}:{label:string;value:string;caption:string;icon:ReactNode}){return <div className="metric"><div className="metric-top"><span>{label}</span><i>{icon}</i></div><strong>{value}</strong><small>{caption}</small></div>;}
export function BookingTable({bookings,onTicket}:{bookings:Booking[];onTicket?:(b:Booking)=>void}){
  if(!bookings.length)return <Empty title="A quiet touchline" text="Bookings will appear here as players reserve your pitches."/>;
  return <div className="table-wrap"><table><thead><tr><th>Booking / team</th><th>Pitch</th><th>Kickoff · EAT</th><th>Status</th><th className="number">Amount</th>{onTicket&&<th><span className="sr-only">Actions</span></th>}</tr></thead><tbody>{bookings.map(b=><tr key={b.id}><td><strong>{b.team}</strong><small>{b.reference}</small></td><td>{b.field_name}<small>{b.player_name}</small></td><td>{prettyDate(b.date,true)}<small>{hourLabel(b.hour)} – {hourLabel(b.hour+1)}</small></td><td><Badge booking={b}/></td><td className="number">ETB {money(b.amount)}</td>{onTicket&&<td><button className="icon-button" aria-label={'View booking '+b.reference} onClick={()=>onTicket(b)}><ArrowUpRight size={17}/></button></td>}</tr>)}</tbody></table></div>;
}
export function TicketContent({booking,onCopy}:{booking:Booking;onCopy:(s:string)=>void}){
 const valid=['confirmed','checked_in'].includes(booking.status);
 return <><div className="ticket-banner"><img src={'/assets/pitch-'+booking.art+'.svg'} alt="Illustrated football pitch"/><div><span className="eyebrow">YOUR MATCHDAY PASS</span><h3>{booking.field_name}</h3><p><MapPin size={13}/>{booking.subcity}, Addis Ababa</p></div></div><div className="ticket-body"><Badge booking={booking}/><div className="ticket-details"><div><CalendarDays/><span>Date<strong>{prettyDate(booking.date,true)}</strong></span></div><div><Clock3/><span>Kickoff · EAT<strong>{hourLabel(booking.hour)} – {hourLabel(booking.hour+1)}</strong></span></div></div><div className="ticket-code">{valid?<img src={'/api/bookings/'+booking.id+'/qr'} alt={'QR entry pass for '+booking.reference} width="180" height="180"/>:<Ticket size={60}/>}<strong>{booking.reference}</strong><p>{valid?'Show this pass to the gatekeeper when you arrive.':'This booking does not have an active entry pass.'}</p></div><div className="receipt-line"><span>Team</span><strong>{booking.team}</strong></div><div className="receipt-line"><span>Payment</span><strong>{booking.payment_status.replaceAll('_',' ')}</strong></div><div className="receipt-line total"><span>Total</span><strong>ETB {money(booking.amount)}</strong></div><p className="notice"><ShieldCheck size={17}/>Sandbox pass · No real reservation or payment.</p>{valid&&<div className="button-row"><a className="button secondary" href={'/api/bookings/'+booking.id+'/qr'} download={booking.reference+'.svg'}>Download QR</a><button className="button primary" onClick={()=>onCopy(booking.token)}>Copy entry code</button></div>}</div></>;
}
