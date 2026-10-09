(() => {
    document.querySelectorAll('[data-special-delete]').forEach(form=>form.addEventListener('submit',event=>{
        if(!confirm('Excluir esta confirmação e liberar a vaga? Isso não estorna pagamentos. O jogador será removido do sorteio e os ajustes manuais dos times serão limpos.'))event.preventDefault();
    }));
    const search=document.querySelector('[data-special-search]');
    if(search){
        const select=document.getElementById('jogadorEspecial');
        const options=Array.from(select.options).slice(1).map(o=>({value:o.value,text:o.text}));
        const normalize=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
        search.addEventListener('input',()=>{
            const previous=select.value,query=normalize(search.value.trim());
            select.replaceChildren(new Option('Selecione o jogador',''));
            options.filter(o=>normalize(o.text).includes(query)).forEach(o=>select.add(new Option(o.text,o.value)));
            if(Array.from(select.options).some(o=>o.value===previous))select.value=previous;
        });
    }
    const notice=document.querySelector('.bbEspecial__error:not([hidden])');
    if(notice){notice.scrollIntoView({block:'center'});notice.focus({preventScroll:true});}
    document.querySelectorAll('[data-special-form]').forEach(form=>{
        form.addEventListener('invalid',event=>{
            const field=event.target; field.classList.add('bbEspecial__invalid');
            if(!field.parentElement.querySelector('.bbEspecial__hint')){const hint=document.createElement('small');hint.className='bbEspecial__hint';hint.textContent=field.validationMessage;field.parentElement.appendChild(hint);}
            if(!form.dataset.focusing){form.dataset.focusing='1';field.scrollIntoView({block:'center',behavior:'smooth'});field.focus({preventScroll:true});setTimeout(()=>delete form.dataset.focusing,500);}
        },true);
        form.addEventListener('input',event=>{if(event.target.validity.valid){event.target.classList.remove('bbEspecial__invalid');event.target.parentElement.querySelector('.bbEspecial__hint')?.remove();}});
    });
    const root=document.getElementById('specialPayment'); if(!root)return;
    let busy=false,timer=null,polls=0;
    const error=document.getElementById('specialError');
    async function request(action,silent=false){
        if(busy)return;busy=true;
        const buttons=root.querySelectorAll('[data-special-pay],[data-special-status]');buttons.forEach(b=>b.disabled=true);
        if(!silent){error.hidden=true;root.setAttribute('aria-busy','true');}
        const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),40000);
        try{
            const response=await fetch(root.dataset.endpoint,{method:'POST',credentials:'same-origin',signal:controller.signal,body:new URLSearchParams({evento_id:root.dataset.id,csrf:root.dataset.csrf,acao:action})});
            const data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'Não foi possível concluir.');
            if(data.status==='pago'){clearInterval(timer);location.reload();return;}
            if(data.qr_code){document.getElementById('specialPix').hidden=false;document.getElementById('specialCode').value=data.qr_code;document.getElementById('specialQr').src='data:image/png;base64,'+data.qr_code_base64;document.getElementById('specialPix').scrollIntoView({behavior:'smooth',block:'start'});if(!timer)timer=setInterval(()=>{if(++polls>100){clearInterval(timer);return;}request('status',true);},6000);}
            else if(!silent){error.textContent='O pagamento ainda não foi confirmado. Aguarde alguns instantes e verifique novamente.';error.hidden=false;}
        }catch(e){if(!silent){error.textContent=e.name==='AbortError'?'A consulta demorou. Verifique o status antes de tentar novamente.':e.message;error.hidden=false;error.scrollIntoView({block:'center',behavior:'smooth'});error.focus({preventScroll:true});}}
        finally{clearTimeout(timeout);busy=false;root.removeAttribute('aria-busy');buttons.forEach(b=>b.disabled=false);}
    }
    root.querySelector('[data-special-pay]')?.addEventListener('click',()=>request('pagar'));
    root.querySelectorAll('[data-special-status]').forEach(b=>b.addEventListener('click',()=>request('status')));
    document.getElementById('specialCopy').addEventListener('click',async event=>{const code=document.getElementById('specialCode');try{await navigator.clipboard.writeText(code.value);event.target.textContent='Código copiado!';}catch(e){code.focus();code.select();event.target.textContent='Código selecionado — copie para pagar';}});
})();
