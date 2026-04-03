<?php
/**
 * Vista: Configuracion del modulo SSolutions
 * Gestiona API keys, tarifas, plantillas y parametros del sistema
 */
?>
<section class="content-header">
    <h1>Configuracion <small>SSolutions</small></h1>
</section>

<section class="content">
    <div class="row">
        <!-- WhatsApp -->
        <div class="col-md-6">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-whatsapp"></i> WhatsApp Cloud API</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Access Token</label>
                        <input type="password" class="form-control config-input" data-clave="whatsapp_token" placeholder="Token de WhatsApp Business">
                    </div>
                    <div class="form-group">
                        <label>Phone Number ID</label>
                        <input type="text" class="form-control config-input" data-clave="whatsapp_phone_id" placeholder="ID del numero de telefono">
                    </div>
                    <div class="form-group">
                        <label>Webhook Verify Token</label>
                        <input type="text" class="form-control config-input" data-clave="whatsapp_verify_token" placeholder="Token para verificar webhook">
                    </div>
                    <div class="form-group">
                        <label>URL del Webhook</label>
                        <input type="text" class="form-control" readonly value="<?php echo 'https://tu-dominio.com/landingV2/api/webhook_whatsapp.php'; ?>">
                        <small class="text-muted">Configura esta URL en Meta Business Suite</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- IA -->
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-magic"></i> Servicio de IA</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Proveedor</label>
                        <select class="form-control config-input" data-clave="ai_provider">
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic (Claude)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" class="form-control config-input" data-clave="ai_api_key" placeholder="API Key del servicio de IA">
                    </div>
                    <div class="form-group">
                        <label>Modelo</label>
                        <input type="text" class="form-control config-input" data-clave="ai_model" placeholder="gpt-4o-mini / claude-sonnet-4-6">
                    </div>
                    <p class="text-muted"><small>Sin API key, el sistema usa resumenes locales basados en reglas.</small></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Tarifas -->
        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-dollar"></i> Tarifas por Hora</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Soporte Remoto</label>
                        <div class="input-group">
                            <span class="input-group-addon">$</span>
                            <input type="number" class="form-control config-input" data-clave="costo_hora_remoto" min="0" step="1000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Soporte En Sitio</label>
                        <div class="input-group">
                            <span class="input-group-addon">$</span>
                            <input type="number" class="form-control config-input" data-clave="costo_hora_sitio" min="0" step="1000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Soporte Taller</label>
                        <div class="input-group">
                            <span class="input-group-addon">$</span>
                            <input type="number" class="form-control config-input" data-clave="costo_hora_taller" min="0" step="1000">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- General -->
        <div class="col-md-6">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-cogs"></i> General</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Prefijo de Tickets</label>
                        <input type="text" class="form-control config-input" data-clave="ticket_prefix" placeholder="TKT" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>API Key del Agente Python</label>
                        <div class="input-group">
                            <input type="text" class="form-control config-input" data-clave="api_key_agente" placeholder="Dejar vacio para desactivar autenticacion">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="generarApiKey()"><i class="fa fa-refresh"></i> Generar</button>
                            </span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nombre de Empresa</label>
                        <input type="text" class="form-control config-input" data-clave="empresa_nombre" placeholder="Mi Empresa">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Simbolo Moneda</label>
                                <input type="text" class="form-control config-input" data-clave="moneda_simbolo" placeholder="$" maxlength="5">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>IVA %</label>
                                <input type="number" class="form-control config-input" data-clave="iva_porcentaje" placeholder="19" min="0" max="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boton guardar -->
    <div class="row">
        <div class="col-md-12 text-center">
            <button class="btn btn-primary btn-lg" onclick="guardarConfiguracion()">
                <i class="fa fa-save"></i> Guardar toda la Configuracion
            </button>
        </div>
    </div>

    <br>

    <!-- Plantillas -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-file-text-o"></i> Plantillas de Mensajes</h3>
                </div>
                <div class="box-body">
                    <div id="plantillas-container">
                        <i class="fa fa-spinner fa-spin"></i> Cargando plantillas...
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    cargarConfiguracionActual();
    cargarPlantillas();
});

function cargarConfiguracionActual() {
    $(".config-input").each(function() {
        var input = $(this);
        var clave = input.data("clave");
        $.getJSON("" + AJAX + "ConfigAjax.php?op=get&clave=" + clave, function(data) {
            if (data && data.valor !== undefined) {
                input.val(data.valor);
            }
        });
    });
}

function guardarConfiguracion() {
    var configs = [];
    $(".config-input").each(function() {
        configs.push({
            clave: $(this).data("clave"),
            valor: $(this).val()
        });
    });

    $.ajax({
        url: "" + AJAX + "ConfigAjax.php?op=guardarMultiple",
        type: "POST",
        data: { configs: JSON.stringify(configs) },
        success: function(resp) {
            alert("Configuracion guardada exitosamente");
        },
        error: function() {
            alert("Error al guardar configuracion");
        }
    });
}

function generarApiKey() {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    var key = '';
    for (var i = 0; i < 32; i++) {
        key += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    $("[data-clave='api_key_agente']").val(key);
}

function cargarPlantillas() {
    $.getJSON("" + AJAX + "WhatsAppAjax.php?op=plantillas", function(data) {
        if (!data || data.length === 0) {
            $("#plantillas-container").html('<p class="text-muted">No hay plantillas configuradas</p>');
            return;
        }
        var html = '<table class="table table-bordered"><thead><tr><th>Nombre</th><th>Canal</th><th>Contenido</th><th>Activo</th></tr></thead><tbody>';
        data.forEach(function(p) {
            html += '<tr><td><strong>' + p.nombre + '</strong></td><td>' + p.canal + '</td><td><pre style="max-height:100px;overflow:auto;">' + p.contenido + '</pre></td><td>' + (p.activo == 1 ? '<span class="label label-success">Si</span>' : '<span class="label label-danger">No</span>') + '</td></tr>';
        });
        html += '</tbody></table>';
        $("#plantillas-container").html(html);
    });
}
</script>
