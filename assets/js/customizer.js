(function($){
  var $wrap = $('#neon-customizer');
  if(!$wrap.length) return;

  var base = parseFloat($wrap.data('base')) || 112;
  var max = parseInt($wrap.data('max'),10) || 21;

  var $textarea = $('#nf-text');
  var $count = $('#nf-count');
  var $price = $('#nf-price');
  var $preview = $('#nf-preview');
  var $warning = $('#nf-warning');
  var $wHidden = $('#neon_width_in');
  var $fHidden = $('#neon_font');
  var $cHidden = $('#neon_color');
  var $pHidden = $('#neon_estimated_price');

  function sanitize(str){
    return str.replace(/[^\x20-\x7E\n]/g,'');
  }

  function glow(c){
    return '0 0 5px '+c+',0 0 10px '+c+',0 0 20px '+c+',0 0 30px '+c+',0 0 40px '+c;
  }

  var fontsLoaded = {};
  function loadFont(font){
    var fam = font.split(',')[0].replace(/["']/g,'').trim();
    if(fontsLoaded[fam] || fam.toLowerCase()==='arial' || fam.toLowerCase()==='courier new') return;
    fontsLoaded[fam]=true;
    var link=document.createElement('link');
    link.rel='stylesheet';
    link.href='https://fonts.googleapis.com/css2?family='+encodeURIComponent(fam.replace(/ /g,'+'))+'&display=swap';
    document.head.appendChild(link);
  }

  function update(){
    var text = sanitize($textarea.val());
    if($textarea.val()!==text) $textarea.val(text);
    var len = text.length;
    $count.text((max-len)+' characters left');
    var longWord = text.split(/\s+/).some(function(w){return w.length>7;});
    if(longWord){ $warning.text('Long word may exceed line limit'); } else { $warning.text(''); }
    var inches = parseInt($wHidden.val(),10) || 0;
    var font = $fHidden.val();
    var color = $cHidden.val();
    var display = text.trim()?text:'Let\u2019s Create';
    loadFont(font);
    $preview.text(display).css({
      fontFamily: font,
      fontSize: (inches*2)+'px',
      color:'#fff',
      textShadow: glow(color)
    });
    var price = base + (inches*0.5) + (len*2);
    $price.text('$'+price.toFixed(2));
    $pHidden.val(price.toFixed(2));
  }

  var debounceTimer;
  function debouncedUpdate(){
    clearTimeout(debounceTimer);
    debounceTimer=setTimeout(update,80);
  }

  $('#nf-width').on('click','button',function(){
    $('#nf-width button').removeClass('is-active').attr('aria-pressed','false');
    $(this).addClass('is-active').attr('aria-pressed','true');
    $wHidden.val($(this).data('in'));
    update();
  });

  $('#nf-fonts').on('click','button',function(){
    $('#nf-fonts button').removeClass('is-active').attr('aria-pressed','false');
    $(this).addClass('is-active').attr('aria-pressed','true');
    var font=$(this).data('font');
    $fHidden.val(font);
    update();
  });

  $('#nf-colors').on('click','button',function(){
    $('#nf-colors button').removeClass('is-active').attr('aria-pressed','false');
    $(this).addClass('is-active').attr('aria-pressed','true');
    var color=$(this).data('color');
    $cHidden.val(color);
    update();
  });

  function navKeys(container, item){
    $(container).on('keydown', item, function(e){
      var $items=$(container).find(item);
      var i=$items.index(this);
      if(e.key==='ArrowRight'){ i=(i+1)%$items.length; $items.eq(i).focus(); e.preventDefault(); }
      if(e.key==='ArrowLeft'){ i=(i-1+$items.length)%$items.length; $items.eq(i).focus(); e.preventDefault(); }
      if(e.key==='Enter' || e.key===' '){ $(this).click(); e.preventDefault(); }
    });
  }
  navKeys('#nf-width','button');
  navKeys('#nf-fonts','button');
  navKeys('#nf-colors','button');

  $textarea.on('input', debouncedUpdate);

  // preload first two fonts
  loadFont($('#nf-fonts button').eq(0).data('font')||'');
  loadFont($('#nf-fonts button').eq(1).data('font')||'');
  update();
})(jQuery);
