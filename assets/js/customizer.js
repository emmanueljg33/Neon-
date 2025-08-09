// Vanilla JS neon sign customizer
(function(){
  const wrap = document.getElementById('neon-customizer');
  if(!wrap) return;

  const base = parseFloat(wrap.dataset.base || '112');
  const max = parseInt(wrap.dataset.max || '21', 10);
  const bg = wrap.dataset.bg;
  const preview = document.getElementById('nf-preview');
  const textarea = document.getElementById('nf-text');
  const priceEl = document.getElementById('nf-price');
  const countEl = document.getElementById('nf-count');
  const widthWrap = document.getElementById('nf-width');
  const fontWrap = document.getElementById('nf-fonts');
  const colorWrap = document.getElementById('nf-colors');
  const hiddenWidth = document.getElementById('neon_width_in');
  const hiddenFont = document.getElementById('neon_font');
  const hiddenColor = document.getElementById('neon_color');
  const hiddenPrice = document.getElementById('neon_estimated_price');

  if(bg){
    const mock = wrap.querySelector('.nf-mockup');
    mock.style.backgroundImage = `url(${bg})`;
  }

  function glow(c){return `0 0 5px ${c},0 0 10px ${c},0 0 20px ${c},0 0 40px ${c}`;}

  function updatePrice(){
    const inches = parseInt(hiddenWidth.value || '0',10);
    const text = textarea.value || '';
    const price = base + (inches * 0.5) + (text.length * 2);
    priceEl.textContent = '$' + price.toFixed(2);
    hiddenPrice.value = price.toFixed(2);
  }

  function updateCount(){
    const remaining = max - textarea.value.length;
    countEl.textContent = `${remaining} characters left`;
  }

  function updatePreview(){
    const inches = parseInt(hiddenWidth.value || '0',10);
    preview.textContent = textarea.value || "Let's Create";
    preview.style.fontFamily = hiddenFont.value;
    preview.style.fontSize = (inches/3) + 'px';
    preview.style.textShadow = glow(hiddenColor.value);
  }

  textarea.addEventListener('input', ()=>{updateCount();updatePreview();updatePrice();});

  function toggleButtons(container, hidden){
    const buttons = Array.from(container.querySelectorAll('button'));
    buttons.forEach((btn,i)=>{
      btn.addEventListener('click',()=>{
        buttons.forEach(b=>b.setAttribute('aria-pressed','false'));
        btn.setAttribute('aria-pressed','true');
        hidden.value = btn.dataset.in || btn.dataset.font || btn.dataset.color;
        if(btn.dataset.fontUrl && !document.querySelector(`link[href="${btn.dataset.fontUrl}"]`)){
          const l=document.createElement('link');l.rel='stylesheet';l.href=btn.dataset.fontUrl;document.head.appendChild(l);
        }
        updatePreview();
        updatePrice();
      });
      btn.addEventListener('keydown',e=>{
        if(['ArrowRight','ArrowDown','ArrowLeft','ArrowUp'].includes(e.key)){
          e.preventDefault();
          const idx = (e.key==='ArrowRight'||e.key==='ArrowDown')? (i+1)%buttons.length : (i-1+buttons.length)%buttons.length;
          buttons[idx].focus();
        }
      });
    });
  }

  toggleButtons(widthWrap, hiddenWidth);
  toggleButtons(fontWrap, hiddenFont);
  toggleButtons(colorWrap, hiddenColor);

  updateCount();
  updatePreview();
  updatePrice();
})();
