// JavaScript Document

$(document).ready(function(){
   $('.button').click(function(){
     agregaritems($(this).attr('id'));
   });

   $('.elim').click(function(){
      eliminaritems($(this).attr('id'));
    });
    $('.limpiar').click(function(){
      eliminartodo();
    });
 });

function csrfToken()
{
	var m = document.querySelector('meta[name="csrf-token"]');
	return m ? m.getAttribute('content') : '';
}

function agregaritems(id)
{
	$.ajax({
            type: "POST",
            url: 'carrito.php',
            data: {op: 1, iditems: id, csrf: csrfToken()},
            success: function(response)
            {
				$('#myModal').modal('show');
            }
       });
}

function eliminaritems(pos)
{
	$.ajax({
            type: "POST",
            url: 'carrito.php',
            data: {op: 2, pos: pos, csrf: csrfToken()},
            success: function(response)
            {
				location.reload();
            }
       });
}

function eliminartodo()
{
	$.ajax({
            type: "POST",
            url: 'carrito.php',
            data: {op: 3, csrf: csrfToken()},
            success: function(response)
            {
				location.reload();
            }
       });
}