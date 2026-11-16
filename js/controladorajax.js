$(document).ready(function() {
    $(document).on('click', '.seleccionar-btn', function(e) {
        e.preventDefault();
        var itemId = $(this).data('id');
        
        $.ajax({
            url: 'carrito.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: itemId }),
            dataType: 'json',
            success: function(response) {
                if (response && response.ok) {
                    alert(response.mensaje || '¡Producto agregado con éxito!');
                } else {
                    alert('Aviso: ' + (response.error || 'No se pudo agregar el producto.'));
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                alert('Error de conexión al procesar el producto.');
            }
        });
    });
});
