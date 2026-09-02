<?php
/**
 * Plantilla de configuración del conector a Prospecty.
 *
 * ESTE ARCHIVO NO LLEVA SECRETOS. Copiarlo en el servidor FUERA de la carpeta
 * pública, con el nombre prospecty-config.php, y llenar los valores reales:
 *
 *   <carpeta padre del DocumentRoot>/prospecty-config.php     (opción 1)
 *   /etc/prospecty-config.php                                 (opción 2)
 *
 * Ejemplo: si el sitio vive en /var/www/promocion/html, el archivo va en
 * /var/www/promocion/prospecty-config.php. Permisos recomendados: 640, dueño www-data.
 */
return [
    // Private Integration Token (Prospecty → Settings → Private Integrations)
    'token'           => 'pit-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',

    // ID de la subcuenta (aparece en la URL: app.prospecty.mx/v2/location/AQUI/...)
    'location_id'     => 'xxxxxxxxxxxxxxxxxxxx',

    // Solo si alguna landing vive en OTRO dominio. Si todas están en este mismo servidor, dejar vacío.
    'allowed_origins' => [],

    // Opcional: workflow al que se agrega cada registro
    'workflow_id'     => '',
];
