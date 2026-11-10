'use strict';
(() => {
 const pen=document.getElementById('editing-pen'); if(!pen)return;
 const label=document.getElementById('editing-label'),exit=document.getElementById('editing-exit');let stage=0;
 const display=()=>{label.textContent=stage===0?'':stage===1?'Make changes':'Editing mode';pen.setAttribute('aria-expanded',String(stage===2));exit.hidden=stage!==2;document.querySelectorAll('.inline-editor').forEach(el=>el.hidden=stage!==2);};
 pen.addEventListener('click',()=>{stage=Math.min(2,stage+1);display();});exit.addEventListener('click',()=>{document.querySelectorAll('.inline-editor').forEach(f=>f.reset());stage=0;display();});
 document.querySelectorAll('[data-editor-cancel]').forEach(b=>b.addEventListener('click',()=>{b.form.reset();}));
})();
