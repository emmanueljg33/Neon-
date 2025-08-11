(function($){
  function glow(c){ return `0 0 5px ${c},0 0 10px ${c},0 0 20px ${c},0 0 40px ${c}`; }

  function update(){
    var $w = $('#neon-configurator');
    if(!$w.length) return;
    var text = $('#textInput').val() || 'Your Text';
    var inches = parseInt($('#sizeSelect').val() || 20, 10);
    var font = $('#fontSelect').val() || "'Arial', sans-serif";
    var color = $('#colorSelect').val() || '#ff00ff';
    var base = parseFloat($w.data('base') || 112);

    $('#previewText').text(text).css({
      fontFamily: font,
      fontSize: (inches/3)+'px',
      color: '#fff',
      textShadow: glow(color)
    });

    var price = base + (inches * 0.5) + (text.length * 2);
    $('#priceDisplay').text('$' + price.toFixed(2));
    $('#neon_estimated_price').val(price.toFixed(2));
  }

  $(document).on('input change', '#textInput,#sizeSelect,#fontSelect,#colorSelect', update);
  $(document).ready(function(){
    // Apply background if provided via data-bg attr
    var bg = $('#neon-configurator').data('bg');
    if(bg){ $('.preview').css('background-image','url('+bg+')'); }
    update();
  });
})(jQuery);
