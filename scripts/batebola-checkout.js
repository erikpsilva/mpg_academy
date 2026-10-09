(() => {
    const root=document.querySelector('[data-endpoint].bbCheckout');if(!root)return;
    const form=document.getElementById('bbCheckoutSelect'),error=document.getElementById('bbCheckoutError');let busy=false,timer=null,polls=0;
    const money=cents=>(cents/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
    function showError(message){error.textContent=message;error.hidden=false;error.scrollIntoView({behavior:'smooth',block:'center'});error.focus({preventScroll:true});}
    function selected(){return form?[...form.querySelectorAll('input:checked:not(:disabled)')]:[];}
    function total(){const choices=selected();document.getElementById('bbCheckoutTotal').textContent=money(choices.reduce((n,e)=>n+Number(e.dataset.cents),0));document.getElementById('bbCheckoutCount').textContent=choices.length?`${choices.length} encontro(s) selecionado(s)`:'Selecione pelo menos um encontro.';document.getElementById('bbCheckoutContinue').disabled=!choices.length||busy;}
    async function send(action,silent=false,eventKey=''){
        if(busy)return;if(action==='preparar'&&!selected().length){showError('Selecione pelo menos um bate-bola.');return;}
        busy=true;const buttons=[...root.querySelectorAll('button')];buttons.forEach(b=>b.disabled=true);root.setAttribute('aria-busy','true');if(!silent)error.hidden=true;
        const body=new URLSearchParams({acao:action,csrf:root.dataset.csrf,pedido:root.dataset.pedido});selected().forEach(e=>body.append('eventos[]',e.value));
        if(eventKey)body.set('evento',eventKey);
        const active=action==='preparar'?document.getElementById('bbCheckoutContinue'):root.querySelector(`[data-checkout-action="${action}"]`),old=active?.textContent;
        if(active&&!silent)active.textContent=action==='pagar'?'Preparando seu PIX...':'Aguarde...';
        const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),45000);
        try{
            const response=await fetch(root.dataset.endpoint,{method:'POST',credentials:'same-origin',body,signal:controller.signal});const data=await response.json();
            if(!response.ok||!data.success)throw new Error(data.message||'Não foi possível continuar.');
            if(data.redirect){location.href=data.redirect;return;}
            if(data.status==='pago'){clearInterval(timer);location.reload();return;}
            if(data.status==='cancelado'){clearInterval(timer);location.reload();return;}
            if(data.qr_code){document.getElementById('bbCheckoutCode').value=data.qr_code;document.getElementById('bbCheckoutQr').src='data:image/png;base64,'+data.qr_code_base64;document.getElementById('bbCheckoutPix').hidden=false;root.querySelector('[data-checkout-action="cancelar"]')?.remove();if(!timer)timer=setInterval(()=>{if(++polls>100){clearInterval(timer);return;}send('status',true);},6000);}
            else if(!silent)showError('O pagamento ainda não foi confirmado. Aguarde alguns instantes e verifique novamente.');
        }catch(e){if(!silent)showError(e.name==='AbortError'?'A resposta demorou. Verifique o pagamento antes de tentar novamente.':e.message);}
        finally{clearTimeout(timeout);busy=false;root.removeAttribute('aria-busy');buttons.forEach(b=>b.disabled=false);if(active)active.textContent=old;if(form)total();}
    }
    if(form){form.addEventListener('change',total);form.addEventListener('submit',e=>{e.preventDefault();send('preparar');});total();}
    root.querySelectorAll('[data-checkout-action]').forEach(b=>b.addEventListener('click',()=>send(b.dataset.checkoutAction)));
    root.querySelectorAll('[data-cancel-individual]').forEach(b=>b.addEventListener('click',()=>{
        if(confirm('Cancelar este PIX individual para escolher os eventos novamente? Não pague o código antigo. Uma vaga já paga não será cancelada.'))send('cancelar_individual',false,b.dataset.cancelIndividual);
    }));
    document.getElementById('bbCheckoutCopy')?.addEventListener('click',async e=>{const code=document.getElementById('bbCheckoutCode');try{await navigator.clipboard.writeText(code.value);e.target.textContent='PIX copiado!';}catch(_){code.focus();code.select();e.target.textContent='Código selecionado — copie para pagar';}});
})();
