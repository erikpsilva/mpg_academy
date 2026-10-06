(function(){
const form=document.getElementById('enrollmentUniformForm');if(!form)return;const el=id=>document.getElementById(id),gender=el('enrollmentUniformGender'),model=el('enrollmentUniformModel'),shirt=el('enrollmentUniformShirt'),shorts=el('enrollmentUniformShorts'),name=el('enrollmentUniformName'),error=el('enrollmentUniformError'),measures=window.ENROLLMENT_UNIFORM_MEASURES,labels=window.ENROLLMENT_UNIFORM_LABELS;
function sizes(piece){const table=(measures[gender.value]||{})[piece]||{};return (table.linhas||[]).map(row=>row[0])}
function render(piece,container,input){input.value='';container.innerHTML=sizes(piece).map(size=>`<button type="button" class="uniformOrder__size" data-size="${size}">${size}</button>`).join('');container.querySelectorAll('button').forEach(button=>button.addEventListener('click',function(){container.querySelectorAll('button').forEach(item=>item.classList.remove('is-active'));this.classList.add('is-active');input.value=this.dataset.size;summary()}))}
function summary(){el('summaryGender').textContent=labels.genero[gender.value]||'—';el('summaryModel').textContent=labels.modelo[model.value]||'—';el('summaryName').textContent=name.value.trim().toUpperCase()||'—';el('summaryShirt').textContent=shirt.value||'—';el('summaryShorts').textContent=shorts.value||'—'}
function choose(radio){gender.value=radio.dataset.genero;model.value=radio.dataset.modelo;render('camisa',el('enrollmentUniformShirtSizes'),shirt);render('shorts',el('enrollmentUniformShortsSizes'),shorts);summary()}
form.querySelectorAll('[name="modelo_uniforme"]').forEach(r=>r.addEventListener('change',()=>choose(r)));name.addEventListener('input',summary);choose(form.querySelector('[name="modelo_uniforme"]:checked'));
document.querySelectorAll('[data-piece]').forEach(button=>button.addEventListener('click',function(){const piece=this.dataset.piece,table=(measures[gender.value]||{})[piece]||{};el('enrollmentMeasuresTitle').textContent=table.label||(piece==='camisa'?'Medidas da camisa':'Medidas do calção/bermuda');el('enrollmentMeasuresBody').innerHTML=`<div class="enrollmentUniform__measureTable"><table><thead><tr>${(table.colunas||[]).map(value=>`<th>${value}</th>`).join('')}</tr></thead><tbody>${(table.linhas||[]).map(row=>`<tr>${row.map(value=>`<td>${value}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;el('enrollmentMeasures').classList.add('is-open');el('enrollmentMeasures').setAttribute('aria-hidden','false');document.body.classList.add('modal-open')}));
el('enrollmentAllMeasures').addEventListener('click',()=>{const order=[['masculino','camisa'],['masculino','shorts'],['feminino','camisa'],['feminino','shorts'],['infantil','camisa'],['infantil','shorts']];el('enrollmentMeasuresTitle').textContent='Todas as medidas';el('enrollmentMeasuresBody').innerHTML=`<div class="uniformMeasures__grid">${order.map(([cut,piece])=>{const table=(measures[cut]||{})[piece];return `<section class="uniformMeasures__table"><h3>${table.label}</h3><div class="uniMedidas__scroll"><table><thead><tr>${table.colunas.map(value=>`<th>${value}</th>`).join('')}</tr></thead><tbody>${table.linhas.map(row=>`<tr>${row.map((value,index)=>index===0?`<th>${value}</th>`:`<td>${value}</td>`).join('')}</tr>`).join('')}</tbody></table></div></section>`}).join('')}</div>`;el('enrollmentMeasures').classList.add('is-open');el('enrollmentMeasures').setAttribute('aria-hidden','false');document.body.classList.add('modal-open')});
document.querySelectorAll('.js-measures-close').forEach(button=>button.addEventListener('click',()=>{el('enrollmentMeasures').classList.remove('is-open');el('enrollmentMeasures').setAttribute('aria-hidden','true');document.body.classList.remove('modal-open')}));
const numberPick=el('enrollmentNumberPick');if(numberPick){numberPick.addEventListener('click',async()=>{const grid=el('enrollmentNumbersGrid');grid.innerHTML='<p class="uniformNumbers__loading">Carregando números...</p>';el('enrollmentNumbers').classList.add('is-open');const response=await fetch(`${window.BASE_URL}/services/site/get_numeros_uniforme.php?turma_id=${el('enrollmentUniformClass').value}&genero=${gender.value}`,{credentials:'same-origin'}),data=await response.json();grid.innerHTML='';if(!data.success){grid.innerHTML=`<p>${data.message}</p>`;return}for(let n=data.min;n<=data.max;n++){const button=document.createElement('button');button.type='button';button.textContent=n;button.className='uniformNumbers__item';if((data.ocupados||[]).includes(n)){button.disabled=true;button.classList.add('is-taken')}else button.addEventListener('click',()=>{el('enrollmentUniformNumber').value=n;el('enrollmentNumberLabel').textContent='Número '+n;numberPick.classList.add('is-filled');el('enrollmentNumbers').classList.remove('is-open')});grid.appendChild(button)}});document.querySelectorAll('.js-enrollment-numbers-close').forEach(button=>button.addEventListener('click',()=>el('enrollmentNumbers').classList.remove('is-open')))}
function clearValidation(){
    error.textContent='';
    error.classList.remove('is-visible');
    form.querySelectorAll('.is-error').forEach(field=>field.classList.remove('is-error'));
}
function showValidation(message,field,focusTarget){
    error.textContent=message;
    error.classList.add('is-visible');
    if(field)field.classList.add('is-error');
    const target=focusTarget||field||error;
    target.scrollIntoView({behavior:'smooth',block:'center'});
    window.setTimeout(()=>{if(typeof target.focus==='function')target.focus({preventScroll:true})},350);
}
function showInvoiceTransition(redirect){
    const overlay=document.createElement('div');
    overlay.className='enrollmentUniformTransition';
    overlay.setAttribute('role','status');
    overlay.setAttribute('aria-live','polite');
    overlay.innerHTML=`<div class="enrollmentUniformTransition__card"><span class="enrollmentUniformTransition__check" aria-hidden="true">✓</span><strong>Uniforme confirmado!</strong><h2>Preparando sua fatura</h2><p>Estamos organizando os itens da matrícula e levando você para o pagamento.</p><span class="enrollmentUniformTransition__loader" aria-hidden="true"></span><small>Não feche esta página.</small></div>`;
    document.body.classList.add('modal-open');
    document.body.appendChild(overlay);
    window.setTimeout(()=>{window.location.href=redirect},1400);
}
form.addEventListener('submit',async event=>{
    event.preventDefault();
    clearValidation();
    const number=el('enrollmentUniformNumber');
    if(!name.value.trim()){
        showValidation('Informe o nome que será estampado na camiseta.',name.closest('.uniformOrder__field'),name);
        return;
    }
    if(!shirt.value){
        const field=shirt.closest('.uniformOrder__field');
        showValidation('Escolha o tamanho da camisa para continuar.',field,field.querySelector('.uniformOrder__size'));
        return;
    }
    if(!shorts.value){
        const field=shorts.closest('.uniformOrder__field');
        showValidation('Escolha o tamanho do calção ou bermuda para continuar.',field,field.querySelector('.uniformOrder__size'));
        return;
    }
    if(numberPick&&!number.value){
        showValidation('Escolha o número da camiseta para continuar.',numberPick.closest('.uniformOrder__field'),numberPick);
        return;
    }
    const button=el('enrollmentUniformSubmit');
    button.disabled=true;
    button.textContent='Salvando sua escolha...';
    try{
        const response=await fetch(window.BASE_URL+'/services/site/criar_uniforme_matricula.php',{method:'POST',body:new FormData(form),credentials:'same-origin'}),data=await response.json();
        if(!response.ok||!data.success)throw new Error(data.message||'Não foi possível salvar.');
        showInvoiceTransition(data.redirect);
    }catch(exception){
        error.textContent=exception.message;
        error.classList.add('is-visible');
        error.scrollIntoView({behavior:'smooth',block:'center'});
        button.disabled=false;
        button.textContent='Confirmar meu uniforme';
    }
});
})();
