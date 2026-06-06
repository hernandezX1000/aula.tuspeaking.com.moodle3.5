(function(){
  if (window.__tsAcuityModal) return;
  window.__tsAcuityModal = 'v1';

var css = ''
    + '.ts-acuity-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);'
    + 'display:none;align-items:center;justify-content:center;z-index:99999}'
    + '.ts-acuity-overlay.open{display:flex}'
    + '.ts-acuity-box{background:#fff;width:min(1100px,96vw);height:min(900px,94vh);'
    + 'border-radius:12px;overflow:hidden;position:relative;box-shadow:0 10px 40px rgba(0,0,0,.4)}'
    + '.ts-acuity-box iframe{width:100%;height:100%;border:0;display:block}'
    + '.ts-acuity-close{position:absolute;top:12px;right:12px;z-index:2;background:#fff;'
    + 'color:#00bcd4;border:2px solid #00bcd4;border-radius:50%;font-size:22px;line-height:1;'
    + 'width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center;'
    + 'box-shadow:0 2px 8px rgba(0,0,0,.2);transition:all .15s}'
    + '.ts-acuity-close:hover{background:#00bcd4;color:#fff;transform:scale(1.08)}';
  try{var st=document.createElement('style');st.textContent=css;document.head.appendChild(st);}catch(e){}

  var overlay, frame;
  function build(){
    overlay=document.createElement('div');
    overlay.className='ts-acuity-overlay';
    var box=document.createElement('div');
    box.className='ts-acuity-box';
    var btn=document.createElement('button');
    btn.className='ts-acuity-close';btn.type='button';btn.innerHTML='&times;';
    btn.setAttribute('aria-label','Cerrar');
    frame=document.createElement('iframe');
    frame.setAttribute('title','Reserva');
    box.appendChild(btn);box.appendChild(frame);overlay.appendChild(box);
    document.body.appendChild(overlay);
    btn.addEventListener('click',close);
    overlay.addEventListener('click',function(ev){ if(ev.target===overlay) close(); });
    document.addEventListener('keydown',function(ev){ if(ev.key==='Escape') close(); });
  }
  function open(url){
    if(!overlay) build();
    frame.src=url;
    overlay.classList.add('open');
    document.body.style.overflow='hidden';
  }
  function close(){
    if(!overlay) return;
    overlay.classList.remove('open');
    frame.src='about:blank';
    document.body.style.overflow='';
  }

  document.addEventListener('click',function(ev){
    var a=ev.target.closest && ev.target.closest('a.acuity-embed-button');
    if(!a) return;
    var href=a.getAttribute('href')||'';
    if(!href) return;
    ev.preventDefault();
    open(href);
  },true);
})();
