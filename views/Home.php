<?php
$recipesPayload = json_encode(
    $recipes,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meu Caderno</title>
<link rel="stylesheet" href="<?= htmlspecialchars($stylesheetUrl, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body data-user-name="<?= htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8') ?>">
<header>
  <div class="logo serif" id="logo"></div>
  <nav id="nav"></nav>
  <button class="btn p new" id="btnNew"><span id="plus"></span><span>Nova receita</span></button>
  <div class="account">
    <span class="account-name"><?= htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8') ?></span>
    <form action="?acao=sair" method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <button class="btn ghost" type="submit">Sair</button>
    </form>
  </div>
</header>

<main>
  <section class="hero">
    <div>
      <small id="greet"></small>
      <h1 class="serif">Todas as suas receitas,<br>em um só caderno.</h1>
      <form class="search" id="searchForm" role="search" onsubmit="return false">
        <label><span id="ico-search"></span><input id="q" type="search" placeholder="Pesquisar receita pelo nome…" aria-label="Pesquisar receita pelo nome" autocomplete="off"></label>
        <button class="btn p" type="submit" style="padding:0 28px">Buscar</button>
      </form>
    </div>
    <div class="art" id="heroArt"></div>
    <svg class="leaf" width="120" height="120" viewBox="0 0 120 120"><path d="M20 100C20 50 55 20 100 20c0 45-30 80-80 80z" fill="#B9D99F"/><path d="M20 100L80 40" stroke="#E3F0D5" stroke-width="3"/></svg>
  </section>

  <div class="stats" id="stats"></div>
  <div class="chips" id="chips"></div>

  <div id="favSec">
    <div class="sec-h"><h2 class="serif">Minhas favoritas</h2><span class="count" id="favCount"></span></div>
    <div class="grid f" id="favGrid"></div>
  </div>

  <div class="sec-h"><h2 class="serif" id="allTitle">Todas as receitas</h2><span class="count" id="allCount"></span></div>
  <div class="grid a" id="allGrid"></div>
  <section class="detail-view" id="detailView" hidden>
    <button class="detail-back" id="detailBack">‹ &nbsp; Voltar às receitas</button>
    <div class="detail-hero">
      <div class="detail-art" id="detailArtwork"><div class="detail-cake"><span class="detail-cherry"></span></div></div>
      <div class="detail-info">
        <div class="detail-top"><span class="detail-tag" id="detailCategory">Bolos</span><div class="detail-tools"><button type="button" id="detailFav" aria-pressed="false">♡ Favoritar</button><button type="button" id="shareDetail">Compartilhar</button><button type="button" id="printDetail">Imprimir</button></div></div>
        <h1 class="serif" id="detailName">Bolo de cenoura da vovó</h1>
        <div class="detail-rating"><span id="detailStars" aria-hidden="true">★★★★☆</span> <small id="detailRating">Minha nota: 4,0</small></div>
        <div class="detail-metas"><div class="detail-meta">◷ &nbsp; Preparo<strong id="detailTime">50 min</strong></div><div class="detail-meta">♧ &nbsp; Rendimento<strong id="detailYield">8 porções</strong></div><div class="detail-meta">Salva em<strong id="detailDate">Hoje</strong></div></div>
        <p class="detail-description" id="detailDescription">Receita de família, perfeita para o lanche da tarde. Fica macia e úmida, com cobertura de chocolate.</p>
        <div class="detail-actions"><button class="btn p" id="editDetail" type="button">✎ Editar receita</button><button class="btn danger" id="deleteDetail" type="button">▤ Excluir</button></div>
      </div>
    </div>
    <div class="detail-lower">
      <section class="detail-panel"><h2 class="serif">Ingredientes <small id="ingredientProgress">0 de 6 separados</small></h2><div id="detailIngredients"></div></section>
      <section class="detail-panel"><h2 class="serif">Modo de preparo</h2><div id="detailSteps"></div></section>
      <aside class="family-note"><strong>✎ Minhas observações</strong><p id="detailNote">Dica da vovó: não abrir o forno nos primeiros 25 minutos. Reduzi o açúcar para 1½ xícara e ficou perfeito.</p><p>Meu neto adora com bastante cobertura!</p></aside>
    </div>
  </section>
</main>

<dialog id="dlg">
  <form id="newForm">
    <h2 class="serif" id="formTitle">Nova receita</h2>
    <p>Guarde os detalhes, ingredientes e etapas da receita.</p>
    <div class="fld"><label for="nName">Nome da receita *</label><input id="nName" required maxlength="60" placeholder="Ex.: Pão de queijo"></div>
    <div class="row">
      <div class="fld"><label for="nCat">Categoria</label><select id="nCat"></select></div>
      <div class="fld"><label for="nTime">Tempo (min)</label><input id="nTime" type="number" min="1" value="30"></div>
      <div class="fld"><label for="nServ">Porções</label><input id="nServ" type="number" min="1" value="4"></div>
    </div>
    <div class="row"><div class="fld"><label for="nRating">Minha nota (0 a 5)</label><input id="nRating" type="number" min="0" max="5" step="0.5" value="4"></div></div>
    <div class="fld"><label for="nDesc">Descrição</label><textarea id="nDesc" rows="3" maxlength="400" placeholder="Conte um pouco sobre a receita"></textarea></div>
    <section class="editor-section"><div class="editor-heading"><strong>Ingredientes</strong><button type="button" class="btn" id="addIngredient">+ Ingrediente</button></div><div id="editorIngredients"></div></section>
    <section class="editor-section"><div class="editor-heading"><strong>Modo de preparo</strong><button type="button" class="btn" id="addStep">+ Etapa</button></div><div id="editorSteps"></div></section>
    <div class="fld"><label for="nNote">Minhas observações</label><textarea id="nNote" rows="3" maxlength="400" placeholder="Dicas e alterações pessoais"></textarea></div>
    <div class="acts"><button type="button" class="btn" id="cancel">Cancelar</button><button class="btn p" type="submit">Salvar receita</button></div>
  </form>
</dialog>
<div class="toast" id="toast" role="status"></div>

<script type="application/json" id="recipe-data"><?= $recipesPayload ?></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const USER_NAME = <?= json_encode($usuario['nome'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script>
async function apiPost(action,data){
 const response=await fetch(`?acao=${encodeURIComponent(action)}`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},body:JSON.stringify(data)});
 const result=await response.json();
 if(!response.ok)throw new Error(result.error||'Não foi possível concluir a operação.');
 return result;
}

const $ = s => document.querySelector(s);
const CATS = ['Bolos','Doces','Salgados','Massas','Saladas','Bebidas'];
const KIND = {Bolos:'cake',Doces:'cookie',Salgados:'soup',Massas:'pasta',Saladas:'salad',Bebidas:'cup'};
const BG = ['#FBE3CC','#E3F0D5','#EAF4DD','#FFF6E3','#FDEBD6','#F6EBDD','#FFF1D6','#FBE4DC'];

let recipes = JSON.parse(document.getElementById('recipe-data').textContent);
let cat='Todas', query='', favoritesOnly=false;

/* ---------- ícones ---------- */
const P={
 clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 users:'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-4 3-6 6.5-6s6 2 6.5 6"/><path d="M16 5a3.2 3.2 0 0 1 0 6M18 14c2.2.6 3.2 2.6 3.5 6"/>',
 search:'<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
 plus:'<path d="M12 5v14M5 12h14"/>'};
const icon=(n,c='#667B64',s=16)=>`<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[n]}</svg>`;
const heart=s=>`<svg width="${s}" height="${s}" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-8-5.2-8-11a4.6 4.6 0 0 1 8-3 4.6 4.6 0 0 1 8 3c0 5.8-8 11-8 11z" fill="none" stroke="#667B64" stroke-width="2" stroke-linejoin="round"/></svg>`;
const logo=`<svg width="36" height="36" viewBox="0 0 38 38"><rect x="7" y="4" width="24" height="30" rx="4" fill="#4C8A4A"/><rect x="11" y="4" width="20" height="30" rx="3" fill="#7FB36B"/><path d="M5 10h5M5 17h5M5 24h5" stroke="#CFE5BC" stroke-width="2.4" stroke-linecap="round"/><path d="M21 14c-3 0-5 2-5 4.5 0 2.6 2 4.5 5 4.5s5-1.9 5-4.5C26 16 24 14 21 14z" fill="#FFF6E3"/><path d="M21 14c0-2 1-3.2 2.4-3.8" stroke="#FFF6E3" stroke-width="1.8" stroke-linecap="round" fill="none"/></svg> Meu Caderno`;

function food(kind,size=130){
 const plate='<ellipse cx="60" cy="78" rx="50" ry="14" fill="#fff" opacity=".9"/><ellipse cx="60" cy="74" rx="42" ry="10" fill="#fff"/>';
 const B={
  cake:'<rect x="30" y="42" width="60" height="32" rx="6" fill="#F4B77E"/><path d="M30 52c8 8 12-4 20 4s12-6 20 2 12-6 20 0V48a6 6 0 0 0-6-6H36a6 6 0 0 0-6 6z" fill="#7A4B2E"/><circle cx="60" cy="36" r="5" fill="#E88A7A"/>',
  pasta:'<ellipse cx="60" cy="64" rx="30" ry="12" fill="#F3DFA4"/><path d="M36 62c6-14 18-18 24-18s18 4 24 18" fill="#E9C46A"/><circle cx="52" cy="54" r="4" fill="#E88A7A"/><circle cx="68" cy="52" r="4" fill="#4C8A4A"/><circle cx="60" cy="48" r="3.4" fill="#E88A7A"/>',
  salad:'<path d="M26 58h68c0 16-14 22-34 22S26 74 26 58z" fill="#CFE5BC"/><circle cx="42" cy="52" r="10" fill="#9CCB82"/><circle cx="62" cy="48" r="12" fill="#7FB36B"/><circle cx="80" cy="53" r="9" fill="#E88A7A"/><circle cx="55" cy="56" r="6" fill="#E9B949"/>',
  pie:'<path d="M24 66l12-24h48l12 24z" fill="#F6E7A0"/><path d="M30 58h60" stroke="#E9C46A" stroke-width="5" stroke-linecap="round"/><path d="M36 46h48M42 50l-6 8M60 46v12M78 50l6 8" stroke="#E9C46A" stroke-width="3" stroke-linecap="round"/>',
  soup:'<path d="M26 54h68c0 18-14 28-34 28S26 72 26 54z" fill="#F2B266"/><ellipse cx="60" cy="54" rx="34" ry="8" fill="#F6C9A0"/><circle cx="52" cy="53" r="3" fill="#E88A7A"/><circle cx="68" cy="55" r="3" fill="#7FB36B"/><path d="M48 40c-3-4 3-6 0-10M62 40c-3-4 3-6 0-10M76 40c-3-4 3-6 0-10" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" opacity=".85"/>',
  cookie:'<circle cx="48" cy="58" r="16" fill="#D9A66B"/><circle cx="74" cy="56" r="16" fill="#D9A66B"/><circle cx="43" cy="54" r="2.5" fill="#6B4226"/><circle cx="52" cy="62" r="2.5" fill="#6B4226"/><circle cx="72" cy="50" r="2.5" fill="#6B4226"/><circle cx="78" cy="60" r="2.5" fill="#6B4226"/><circle cx="68" cy="62" r="2.5" fill="#6B4226"/>',
  cup:'<path d="M42 40h36l-4 36H46z" fill="#CFE5BC"/><path d="M44 52h32l-2 24H46z" fill="#7FB36B"/><path d="M60 38l10-14" stroke="#E88A7A" stroke-width="3" stroke-linecap="round"/>'
 };
 return `<svg width="${size}" height="${size*.83}" viewBox="0 0 120 100" aria-hidden="true">${plate}${B[kind]||B.cake}</svg>`;
}

/* ---------- render ---------- */
const esc=s=>s.replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const norm=s=>s.normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase();
const fmt=m=>m>=60?`${Math.floor(m/60)} h${m%60?' '+m%60:''}`:`${m} min`;

function card(r){
 return `<article class="card" data-id="${r.id}">
  <div class="photo" style="background:${r.bg}">${food(r.k||KIND[r.c])}
   <button class="heart ${r.f?'on':''}" data-fav="${r.id}" aria-pressed="${r.f}" aria-label="${r.f?'Remover dos favoritos':'Marcar como favorita'}: ${esc(r.n)}">${heart(18)}</button></div>
  <div class="body"><span class="tag">${esc(r.c)}</span><h3 class="serif">${esc(r.n)}</h3>
   <div class="meta"><span>${icon('clock')}${fmt(r.t)}</span><span>${icon('users')}${r.p} porções</span></div></div></article>`;
}

function render(){
 const q=norm(query.trim());
 const match=r=>(cat==='Todas'||r.c===cat)&&(!q||norm(r.n).includes(q))&&(!favoritesOnly||r.f);
 const shown=recipes.filter(match);
 const favs=shown.filter(r=>r.f);

 $('#stats').innerHTML=[
  [recipes.length,'receitas guardadas','#E3F0D5'],
  [new Set(recipes.map(r=>r.c)).size,'categorias','#FFF6E3'],
  [recipes.filter(r=>r.f).length,'favoritas','#FBE4DC'],
  [recipes.filter(r=>r.shared).length,'compartilhadas','#E8F2F6']
 ].map(([n,l,bg])=>`<div class="stat"><b style="background:${bg}">${n}</b>${l}</div>`).join('');

 $('#chips').innerHTML='<span>Categorias</span>'+['Todas',...CATS].map(c=>`<button class="chip ${c===cat?'on':''}" data-cat="${c}" aria-pressed="${c===cat}">${c}</button>`).join('');

 $('#favSec').style.display=favs.length&&!favoritesOnly?'':'none';
 $('#favCount').textContent=favs.length+(favs.length===1?' favorita':' favoritas');
 $('#favGrid').innerHTML=favs.map(card).join('');

 const filtering=cat!=='Todas'||q||favoritesOnly;
 $('#allTitle').textContent=favoritesOnly?'Minhas favoritas':filtering?'Resultados':'Todas as receitas';
 $('#allCount').textContent=shown.length+(shown.length===1?' receita':' receitas');
 $('#allGrid').innerHTML=shown.length?shown.map(card).join(''):
  `<div class="empty"><b>${favoritesOnly?'Nenhuma favorita encontrada':'Nenhuma receita encontrada'}</b>${favoritesOnly?'Marque uma receita com o coração para encontrá-la aqui.':'Tente outro nome ou categoria, ou cadastre uma nova receita.'}</div>`;
}

/* ---------- eventos ---------- */
document.addEventListener('click',async e=>{
 const fav=e.target.closest('[data-fav]');
 if(fav){
  e.stopPropagation();
  const r=recipes.find(x=>x.id===Number(fav.dataset.fav));
  if(!r)return;
  try{const result=await apiPost('favoritar',{id:r.id});Object.assign(r,result.recipe);render();toast(r.f?`“${r.n}” adicionada às favoritas`:`“${r.n}” removida das favoritas`)}
  catch(error){toast(error.message)}
  return;
 }
 const ch=e.target.closest('[data-cat]'); if(ch){favoritesOnly=false;cat=ch.dataset.cat;render();return}
 const cd=e.target.closest('.card');
 if(cd){const r=recipes.find(x=>x.id==cd.dataset.id); if(r) showDetails(r)}
});
function showDetails(r){
 selectedRecipe=r; $('#heroArt').parentElement.hidden=true; $('#stats').hidden=true; $('#chips').hidden=true; $('#favSec').hidden=true; $('#allTitle').parentElement.hidden=true; $('#allGrid').hidden=true; $('#detailView').hidden=false;
 const rating=Number.isFinite(Number(r.rating))?Number(r.rating):4;
 $('#detailArtwork').innerHTML=food(r.k||KIND[r.c],190); $('#detailArtwork').style.background=r.bg||BG[0];
 $('#detailCategory').textContent=r.c||'Receitas'; $('#detailName').textContent=r.n||'Minha receita'; $('#detailRating').textContent='Minha nota: '+rating.toFixed(1).replace('.',','); $('#detailStars').textContent='★'.repeat(Math.round(rating))+'☆'.repeat(5-Math.round(rating)); $('#detailTime').textContent=fmt(Number(r.t)||30); $('#detailYield').textContent=(r.p||4)+' porções'; $('#detailDescription').textContent=r.desc||'Uma receita especial para guardar no seu caderno.'; $('#detailNote').textContent=r.note||'Adicione suas dicas pessoais ao editar esta receita.';
 $('#detailDate').textContent=r.createdAt?new Date(r.createdAt).toLocaleDateString('pt-BR'):'Receita inicial';
 $('#detailFav').textContent=r.f?'♥ Favorita':'♡ Favoritar'; $('#detailFav').setAttribute('aria-pressed',String(Boolean(r.f)));
 const ingredients=Array.isArray(r.ingredients)&&r.ingredients.length?r.ingredients:[];
 $('#detailIngredients').innerHTML=ingredients.length?ingredients.map((it,i)=>{const name=Array.isArray(it)?it[0]:it.name;const qty=Array.isArray(it)?it[1]:(it.quantity||'');const checked=Array.isArray(r.checkedIngredients)&&r.checkedIngredients.includes(i);return `<label class="detail-ingredient ${checked?'done':''}"><input type="checkbox" data-ingredient="${i}" ${checked?'checked':''}><span>${esc(String(name||''))}</span><b>${esc(String(qty||''))}</b></label>`}).join(''):'<p class="count">Nenhum ingrediente cadastrado ainda.</p>';
 const steps=Array.isArray(r.steps)?r.steps:[]; $('#detailSteps').innerHTML=steps.length?steps.map((step,i)=>`<div class="detail-step"><b>${i+1}</b><span>${esc(String(step))}</span></div>`).join(''):'<p class="count">Nenhuma etapa de preparo cadastrada ainda.</p>';
 const updateProgress=()=>{const all=$$('#detailIngredients input[type=checkbox]');const done=$$('#detailIngredients input[type=checkbox]:checked');$('#ingredientProgress').textContent=`${done.length} de ${all.length} separados`;};
 $('#detailIngredients').querySelectorAll('input').forEach(input=>input.addEventListener('change',async()=>{const checked=input.checked;const index=Number(input.dataset.ingredient);try{await apiPost('ingrediente',{id:r.id,index,checked});const current=new Set(r.checkedIngredients||[]);if(checked)current.add(index);else current.delete(index);r.checkedIngredients=[...current];input.closest('.detail-ingredient').classList.toggle('done',checked);updateProgress()}catch(error){input.checked=!checked;toast(error.message)}})); updateProgress(); window.scrollTo({top:0,behavior:'smooth'});
}
let selectedRecipe=null; const $$=s=>Array.from(document.querySelectorAll(s));
$('#detailBack').addEventListener('click',()=>{ $('#detailView').hidden=true; $('#heroArt').parentElement.hidden=false; $('#stats').hidden=false; $('#chips').hidden=false; $('#favSec').hidden=false; $('#allTitle').parentElement.hidden=false; $('#allGrid').hidden=false; window.scrollTo({top:0,behavior:'smooth'}); });
let editingRecipe=null;

$('#detailBack').addEventListener('click',()=>{selectedRecipe=null});
$('#deleteDetail').addEventListener('click',async()=>{if(!selectedRecipe||!confirm(`Deseja excluir “${selectedRecipe.n}”?`))return;try{await apiPost('excluir',{id:selectedRecipe.id});recipes=recipes.filter(r=>r.id!==selectedRecipe.id);render();$('#detailBack').click();toast('Receita excluída do caderno.')}catch(error){toast(error.message)}});
$('#detailFav').addEventListener('click',async()=>{if(!selectedRecipe)return;try{const result=await apiPost('favoritar',{id:selectedRecipe.id});Object.assign(selectedRecipe,result.recipe);render();showDetails(selectedRecipe);toast(selectedRecipe.f?'Adicionada às favoritas.':'Removida das favoritas.')}catch(error){toast(error.message)}});
$('#printDetail').addEventListener('click',()=>window.print());

async function copyText(text){
 if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(text);return}
 const field=document.createElement('textarea');field.value=text;field.style.position='fixed';field.style.opacity='0';document.body.append(field);field.select();
 const copied=document.execCommand('copy');field.remove();if(!copied)throw new Error('Não foi possível copiar o conteúdo.');
}

$('#shareDetail').addEventListener('click',async()=>{
 if(!selectedRecipe)return;
 const ingredients=(selectedRecipe.ingredients||[]).map(item=>Array.isArray(item)?`${item[0]}${item[1]?`: ${item[1]}`:''}`:item.name).filter(Boolean);
 const steps=(selectedRecipe.steps||[]).map((step,index)=>`${index+1}. ${step}`);
 const text=[selectedRecipe.desc,ingredients.length?`Ingredientes: ${ingredients.join(', ')}`:'',steps.length?`Preparo:\n${steps.join('\n')}`:''].filter(Boolean).join('\n\n');
 try{
  if(navigator.share)await navigator.share({title:selectedRecipe.n,text:text||selectedRecipe.n});
  else{await copyText(`${selectedRecipe.n}\n\n${text||'Receita do Meu Caderno'}`);toast('Receita copiada para compartilhar.')}
  const result=await apiPost('compartilhar',{id:selectedRecipe.id});Object.assign(selectedRecipe,result.recipe);render();
 }catch(error){if(error.name!=='AbortError')toast(error.message)}
});

function addEditorRow(container,type,firstValue='',secondValue=''){
 const row=document.createElement('div');row.className='editor-row';
 if(type==='ingredient'){
  const name=document.createElement('input');name.placeholder='Ingrediente';name.value=firstValue;name.dataset.field='name';name.setAttribute('aria-label','Ingrediente');
  const quantity=document.createElement('input');quantity.placeholder='Quantidade';quantity.value=secondValue;quantity.dataset.field='quantity';quantity.setAttribute('aria-label','Quantidade');
  row.append(name,quantity);
 }else{
  const step=document.createElement('textarea');step.rows=2;step.placeholder='Descreva esta etapa';step.value=firstValue;step.dataset.field='step';step.setAttribute('aria-label','Etapa do preparo');row.append(step);
 }
 const remove=document.createElement('button');remove.type='button';remove.className='remove-row';remove.textContent='Remover';remove.setAttribute('aria-label','Remover item');remove.addEventListener('click',()=>row.remove());row.append(remove);container.append(row);
}

function fillEditor(recipe=null){
 editingRecipe=recipe;$('#formTitle').textContent=recipe?'Editar receita':'Nova receita';
 $('#nName').value=recipe?.n||'';$('#nCat').value=recipe?.c||CATS[0];$('#nTime').value=recipe?.t||30;$('#nServ').value=recipe?.p||4;
 $('#nRating').value=recipe?.rating??4;$('#nDesc').value=recipe?.desc||'';$('#nNote').value=recipe?.note||'';
 const ingredients=$('#editorIngredients');const steps=$('#editorSteps');ingredients.replaceChildren();steps.replaceChildren();
 (recipe?.ingredients||[]).forEach(item=>addEditorRow(ingredients,'ingredient',Array.isArray(item)?item[0]:item.name,Array.isArray(item)?item[1]:item.quantity));
 (recipe?.steps||[]).forEach(step=>addEditorRow(steps,'step',step));
 if(!ingredients.children.length)addEditorRow(ingredients,'ingredient');if(!steps.children.length)addEditorRow(steps,'step');
}

$('#addIngredient').addEventListener('click',()=>addEditorRow($('#editorIngredients'),'ingredient'));
$('#addStep').addEventListener('click',()=>addEditorRow($('#editorSteps'),'step'));
$('#editDetail').addEventListener('click',()=>{if(!selectedRecipe)return;fillEditor(selectedRecipe);dlg.showModal();$('#nName').focus()});

$('#q').addEventListener('input',e=>{query=e.target.value;render()});
$('#searchForm').addEventListener('submit',()=>{render();$('#allGrid').scrollIntoView({behavior:'smooth',block:'start'})});

const dlg=$('#dlg');
$('#btnNew').onclick=()=>{fillEditor();dlg.showModal();$('#nName').focus()};
$('#cancel').onclick=()=>dlg.close();
$('#newForm').addEventListener('submit',async event=>{
 event.preventDefault();
 const values={n:$('#nName').value.trim(),c:$('#nCat').value,t:Math.max(1,Number($('#nTime').value)||30),p:Math.max(1,Number($('#nServ').value)||4),rating:Math.min(5,Math.max(0,Number($('#nRating').value)||0)),desc:$('#nDesc').value.trim(),note:$('#nNote').value.trim(),ingredients:Array.from($('#editorIngredients').children).map(row=>[row.querySelector('[data-field="name"]').value.trim(),row.querySelector('[data-field="quantity"]').value.trim()]).filter(item=>item[0]),steps:Array.from($('#editorSteps').children).map(row=>row.querySelector('[data-field="step"]').value.trim()).filter(Boolean)};
 try{
  const result=await apiPost('salvar',{...values,id:editingRecipe?.id||null});
  const wasEditing=Boolean(editingRecipe);editingRecipe=null;dlg.close();
  if(wasEditing){const index=recipes.findIndex(r=>r.id===result.recipe.id);recipes[index]=result.recipe;render();showDetails(result.recipe)}
  else{recipes.unshift(result.recipe);cat='Todas';favoritesOnly=false;query='';$('#q').value='';render();toast(`“${values.n}” salva no caderno`)}
 }catch(error){toast(error.message)}
});

$('#nav').addEventListener('click',event=>{
 const button=event.target.closest('button');if(!button)return;
 const index=Array.from($('#nav').children).indexOf(button);
 $('#nav').querySelectorAll('button').forEach(item=>item.classList.toggle('on',item===button));
 if(!$('#detailView').hidden)$('#detailBack').click();
 if(index===0){favoritesOnly=false;cat='Todas';query='';$('#q').value='';render();window.scrollTo({top:0,behavior:'smooth'})}
 if(index===1){favoritesOnly=false;render();$('#allTitle').parentElement.scrollIntoView({behavior:'smooth'})}
 if(index===2){favoritesOnly=true;cat='Todas';query='';$('#q').value='';render();$('#allTitle').parentElement.scrollIntoView({behavior:'smooth'})}
 if(index===3){favoritesOnly=false;render();$('#chips').scrollIntoView({behavior:'smooth'})}
});

let tt;function toast(m){const t=$('#toast');t.textContent=m;t.classList.add('show');clearTimeout(tt);tt=setTimeout(()=>t.classList.remove('show'),2200)}

/* ---------- init ---------- */
$('#logo').innerHTML=logo;
$('#nav').innerHTML=['Início','Receitas','Favoritas','Categorias'].map((t,i)=>`<button class="${i?'':'on'}">${t}</button>`).join('');
$('#plus').innerHTML=icon('plus','#fff',18);
$('#ico-search').innerHTML=icon('search','#667B64',20);
$('#heroArt').innerHTML=food('cake',190);
$('#nCat').innerHTML=CATS.map(c=>`<option>${c}</option>`).join('');
const h=new Date().getHours();
$('#greet').textContent=(h<12?'Bom dia':h<18?'Boa tarde':'Boa noite')+', '+USER_NAME+' 🌿';
render();
</script>
</body>
</html>
