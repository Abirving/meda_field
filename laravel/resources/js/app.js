import Alpine from 'alpinejs';
import htmx from 'htmx.org';
window.Alpine=Alpine;window.htmx=htmx;window.medaLive=false;
Alpine.data('holdTimer',seconds=>({seconds,timer:null,get label(){return Math.floor(this.seconds/60)+':'+String(this.seconds%60).padStart(2,'0');},init(){this.timer=setInterval(()=>{if(--this.seconds<=0){clearInterval(this.timer);location.reload();}},1000);},destroy(){clearInterval(this.timer);}}));
Alpine.start();
const slots=document.getElementById('slots');
const indicator=document.getElementById('live-state');
function live(active,text){if(indicator){indicator.classList.toggle('connected',active);indicator.textContent=text;}}
if(slots){
 live(false,'5s updates');
 if(import.meta.env.VITE_REVERB_APP_KEY){
  (async()=>{const [{default:Echo},{default:Pusher}]=await Promise.all([import('laravel-echo'),import('pusher-js')]);window.Pusher=Pusher;
  const echo=new Echo({broadcaster:'reverb',key:import.meta.env.VITE_REVERB_APP_KEY,wsHost:import.meta.env.VITE_REVERB_HOST||location.hostname,wsPort:Number(import.meta.env.VITE_REVERB_PORT||8080),wssPort:Number(import.meta.env.VITE_REVERB_PORT||443),forceTLS:import.meta.env.VITE_REVERB_SCHEME==='https',enabledTransports:['ws','wss']});
  echo.channel('fields.'+slots.dataset.fieldId).subscribed(()=>{window.medaLive=true;live(true,'Live updates');htmx.trigger(document.body,'slotsChanged');}).listen('.slots.changed',()=>htmx.trigger(document.body,'slotsChanged'));
  echo.connector.pusher.connection.bind('state_change',state=>{if(state.current!=='connected'){window.medaLive=false;live(false,'5s fallback');}});
  echo.connector.pusher.connection.bind('error',()=>{window.medaLive=false;live(false,'5s fallback');});
  window.addEventListener('pagehide',()=>echo.disconnect());
  })().catch(()=>{window.medaLive=false;live(false,'5s fallback');});
 }
 document.body.addEventListener('htmx:afterRequest',event=>{if(event.detail.target?.id==='slots')live(event.detail.successful,window.medaLive?'Live updates':event.detail.successful?'Live · 5s updates':'Offline · retrying');});
}
document.body.addEventListener('htmx:responseError',event=>{if(event.detail.target?.id!=='slots'){alert('The request could not be completed. Please refresh and retry.');}});
const scanButton=document.getElementById('scan-qr');let scanner=null;
scanButton?.addEventListener('click',async()=>{
 const status=document.getElementById('scan-status');
 if(scanner){await scanner.stop().catch(()=>{});scanner.clear();scanner=null;scanButton.textContent='Scan QR with camera';return;}
 try{const {Html5Qrcode}=await import('html5-qrcode');scanner=new Html5Qrcode('qr-reader');await scanner.start({facingMode:'environment'},{fps:8,qrbox:{width:220,height:220}},async text=>{document.getElementById('qr-token').value=text;status.textContent='Scanned. Confirm the field and press Validate.';await scanner.stop();scanner.clear();scanner=null;scanButton.textContent='Scan QR with camera';},()=>{});scanButton.textContent='Stop camera';status.textContent='Point the camera at the player’s QR pass.';}catch{status.textContent='Camera unavailable. Use HTTPS or localhost, allow camera access, or paste the entry code.';scanner=null;}
});
window.addEventListener('pagehide',()=>scanner?.stop().catch(()=>{}));
