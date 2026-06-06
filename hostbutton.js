(function(){
  const WWWROOT='/app/moodle';
  const CAN_URL = `${WWWROOT}/_tszoom/canlaunch.php`;
  const START_URL=`${WWWROOT}/_tszoom/start.php`;
  window.__tszoom_loaded='v12';

  // Estilos
  const css=`
    .ts-zoom-actions{display:inline-flex;gap:.5rem;align-items:center;margin-left:.5rem;flex-wrap:wrap;vertical-align:middle}
    .ts-btn{display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .65rem;border-radius:.5rem;text-decoration:none;font-weight:600;border:1px solid #1f2937;background:#111827;color:#fff;white-space:nowrap}
    .ts-btn:hover{filter:brightness(1.08)}
    .ts-host-btn{background:#0f766e;border-color:#0f766e}
  `;
  try{const st=document.createElement('style');st.textContent=css;document.head.appendChild(st);}catch{}

  // Regex
  const MID_RE=/(?:https?:\/\/)?[A-Za-z0-9.-]*zoom\.us\/(?:j|s)\/(\d+)/i;
  const URL_RE=/(https?:\/\/[^\s<"]*zoom\.us\/(?:j|s)\/\d+[^\s<"]*)/i;

  // Cache permiso
  const canCache=new Map();
  function canLaunch(mid){
    if(canCache.has(mid)) return canCache.get(mid);
    const p=fetch(`${CAN_URL}?mid=${encodeURIComponent(mid)}`,{credentials:'include'})
      .then(r=>r.ok?r.json():Promise.reject(r.status))
      .then(j=>!!(j&&j.ok)).catch(()=>false);
    canCache.set(mid,p); return p;
  }

  // Extrae mid y joinUrl a partir de un <a>
  function infoFromAnchor(a){
    const href=a.getAttribute('href')||'';
    const txt=(a.textContent||'').trim();
    let url=null, mid=null;

    // Preferimos la URL visible en el texto (suele ser la zoom completa)
    let m=txt.match(URL_RE);
    if(m){ url=m[1]; const mm=url.match(MID_RE); mid=mm?mm[1]:null; }

    // Si no, usa el href
    if(!mid){
      m=href.match(MID_RE);
      if(m){ mid=m[1]; url=URL_RE.test(href)?href:(`https://us02web.zoom.us/j/${mid}`); }
    }
    if(!mid) return null;
    return { anchor:a, mid, joinUrl:url };
  }

  // Inserta el botón Host junto al <a>, evitando duplicados
  function attachHost({anchor, mid}){
    if(anchor.dataset.tsHostbtn==='1') return false;
    const next=anchor.nextElementSibling;
    if(next && next.classList && next.classList.contains('ts-zoom-actions')) return false;

    const wrap=document.createElement('span');
    wrap.className='ts-zoom-actions';
    wrap.setAttribute('data-mid',mid);

    const host=document.createElement('a');
    host.className='ts-btn ts-host-btn';
    host.style.display='none';
    host.href=`${START_URL}?mid=${encodeURIComponent(mid)}`;
    host.target='_blank'; host.rel='noopener';
    host.textContent='Iniciar clase (host)';
    wrap.appendChild(host);

    // Inserta un espacio y el contenedor (sin errores de tipo)
    anchor.insertAdjacentText('afterend',' ');
    anchor.insertAdjacentElement('afterend',wrap);

    canLaunch(mid).then(ok=>{ if(ok) host.style.display='inline-flex'; });
    anchor.dataset.tsHostbtn='1';
    return true;
  }

  // Escaneo principal
  function scan(root){
    let tested=0, found=0, inserted=0;
    const anchors=root.querySelectorAll('a');
    anchors.forEach(a=>{
      tested++;
      const info=infoFromAnchor(a);
      if(!info) return;
      found++;
      if(attachHost(info)) inserted++;
    });
    window.__tszoom_lastReport={tested,found,inserted};
  }

  function run(){ try{ scan(document);}catch(e){ window.__tszoom_lastReport={tested:0,found:0,inserted:0,error:String(e)}; } }

  run();
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', run);

  // Reprocesa contenido que llegue por AJAX
  const obs=new MutationObserver(muts=>{
    for(const m of muts){
      for(const n of m.addedNodes||[]){
        if(n.nodeType===1) scan(n);
      }
    }
  });
  var __t=document.body||document.documentElement; if(__t){obs.observe(__t,{childList:true,subtree:true});} else {document.addEventListener('DOMContentLoaded',function(){obs.observe(document.body,{childList:true,subtree:true});});}
})();
