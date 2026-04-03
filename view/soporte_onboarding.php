<?php
/**
 * Vista: Onboarding — Tour inicial para nuevos negocios
 * Se muestra automáticamente si el onboarding no está completado
 */
?>
<section class="content-header">
    <h1>Bienvenido a SSolutions <small>Configuremos tu negocio</small></h1>
</section>

<section class="content">
    <!-- Barra de progreso -->
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="box box-solid box-primary">
                <div class="box-header"><h3 class="box-title"><i class="fa fa-rocket"></i> Progreso de Configuracion</h3></div>
                <div class="box-body">
                    <div class="progress progress-xxl active">
                        <div class="progress-bar progress-bar-success progress-bar-striped" id="ob-progress" style="width:0%">0%</div>
                    </div>
                    <p class="text-center text-muted" id="ob-status">Cargando...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pasos -->
    <div class="row">
        <!-- PASO 1: Datos del negocio -->
        <div class="col-md-6">
            <div class="box" id="box-paso-1">
                <div class="box-header with-border">
                    <span class="badge bg-blue pull-right" id="badge-1">Paso 1</span>
                    <h3 class="box-title"><i class="fa fa-building"></i> Datos del Negocio</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Nombre de tu empresa</label>
                        <input type="text" id="ob-empresa" class="form-control input-lg" placeholder="Ej: TechRepair Colombia">
                    </div>
                    <div class="form-group">
                        <label>Telefono</label>
                        <input type="text" id="ob-telefono" class="form-control" placeholder="573001234567">
                    </div>
                    <button class="btn btn-primary btn-lg btn-block" onclick="guardarPaso1()">
                        <i class="fa fa-check"></i> Guardar y Continuar
                    </button>
                </div>
            </div>
        </div>

        <!-- PASO 2: WhatsApp -->
        <div class="col-md-6">
            <div class="box" id="box-paso-2">
                <div class="box-header with-border">
                    <span class="badge bg-green pull-right" id="badge-2">Paso 2</span>
                    <h3 class="box-title"><i class="fa fa-whatsapp"></i> WhatsApp Business</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">Conecta tu WhatsApp Business para enviar mensajes automaticos.</p>
                    <div class="form-group">
                        <label>Access Token</label>
                        <input type="password" id="ob-wa-token" class="form-control" placeholder="Token de WhatsApp Cloud API">
                    </div>
                    <div class="form-group">
                        <label>Phone Number ID</label>
                        <input type="text" id="ob-wa-phone" class="form-control" placeholder="ID del numero">
                    </div>
                    <button class="btn btn-success btn-lg btn-block" onclick="guardarPaso2()">
                        <i class="fa fa-whatsapp"></i> Conectar WhatsApp
                    </button>
                    <p class="text-center" style="margin-top:8px"><a href="#" onclick="saltarPaso(3)">Saltar por ahora</a></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- PASO 3: IA -->
        <div class="col-md-6">
            <div class="box" id="box-paso-3">
                <div class="box-header with-border">
                    <span class="badge bg-purple pull-right" id="badge-3">Paso 3</span>
                    <h3 class="box-title"><i class="fa fa-magic"></i> Bot de IA</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">Activa Claude para responder clientes automaticamente.</p>
                    <div class="form-group">
                        <label>Proveedor</label>
                        <select id="ob-ai-provider" class="form-control">
                            <option value="anthropic">Anthropic (Claude) — Recomendado</option>
                            <option value="openai">OpenAI (GPT)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" id="ob-ai-key" class="form-control" placeholder="sk-...">
                    </div>
                    <button class="btn btn-primary btn-lg btn-block" onclick="guardarPaso3()">
                        <i class="fa fa-magic"></i> Activar IA
                    </button>
                    <p class="text-center" style="margin-top:8px"><a href="#" onclick="saltarPaso(4)">Saltar (usar modo local)</a></p>
                </div>
            </div>
        </div>

        <!-- PASO 4: Primer ticket -->
        <div class="col-md-6">
            <div class="box" id="box-paso-4">
                <div class="box-header with-border">
                    <span class="badge bg-yellow pull-right" id="badge-4">Paso 4</span>
                    <h3 class="box-title"><i class="fa fa-ticket"></i> Tu Primer Ticket</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">Crea un ticket de prueba para ver el sistema en accion.</p>
                    <button class="btn btn-warning btn-lg btn-block" onclick="cargarDatosDemo()">
                        <i class="fa fa-magic"></i> Cargar Datos Demo
                    </button>
                    <p class="text-center text-muted" style="margin-top:5px"><small>Crea clientes, tickets y conversaciones de ejemplo</small></p>
                    <hr>
                    <button class="btn btn-default btn-block" onclick="cargarContenido('soporte_tickets')">
                        <i class="fa fa-plus"></i> Crear Ticket Manual
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Boton finalizar -->
    <div class="row" id="ob-finalizar" style="display:none">
        <div class="col-md-8 col-md-offset-2 text-center">
            <div class="callout callout-success">
                <h4><i class="fa fa-check-circle"></i> Configuracion Completada!</h4>
                <p>Tu sistema esta listo. Ahora puedes gestionar tickets, enviar mensajes y cerrar ventas.</p>
                <button class="btn btn-success btn-lg" onclick="completarOnboarding()">
                    <i class="fa fa-rocket"></i> Ir al Dashboard
                </button>
            </div>
        </div>
    </div>
</section>

<script>
var BASE_CONF = "../landingV2/ajax/ConfigAjax.php";
var BASE_OB = "../landingV2/ajax/NotificacionAjax.php";

$(document).ready(function() { verificarProgreso(); });

function verificarProgreso() {
    $.getJSON(BASE_OB + "?op=onboardingEstado", function(d) {
        var pct = d.progreso || 0;
        $("#ob-progress").css("width", pct + "%").text(pct + "%");
        $("#ob-status").text(d.pasos_completados + " de " + d.total_pasos + " pasos completados");

        // Marcar pasos completados
        for (var i = 1; i <= 4; i++) {
            var paso = d.pasos[i];
            if (paso && paso.check) {
                $("#box-paso-" + i).addClass("box-success");
                $("#badge-" + i).removeClass("bg-blue bg-green bg-purple bg-yellow").addClass("bg-green").text("OK");
            }
        }

        if (pct >= 50) $("#ob-finalizar").show();
    });
}

function guardarPaso1() {
    var configs = [
        {clave: "empresa_nombre", valor: $("#ob-empresa").val()},
        {clave: "empresa_telefono", valor: $("#ob-telefono").val()}
    ];
    $.post(BASE_CONF + "?op=guardarMultiple", {configs: JSON.stringify(configs)}, function() {
        avanzarPaso(2);
    });
}

function guardarPaso2() {
    var configs = [
        {clave: "whatsapp_token", valor: $("#ob-wa-token").val()},
        {clave: "whatsapp_phone_id", valor: $("#ob-wa-phone").val()}
    ];
    $.post(BASE_CONF + "?op=guardarMultiple", {configs: JSON.stringify(configs)}, function() {
        avanzarPaso(3);
    });
}

function guardarPaso3() {
    var configs = [
        {clave: "ai_provider", valor: $("#ob-ai-provider").val()},
        {clave: "ai_api_key", valor: $("#ob-ai-key").val()},
        {clave: "ai_model", valor: $("#ob-ai-provider").val() === "anthropic" ? "claude-haiku-4-5-20251001" : "gpt-4o-mini"}
    ];
    $.post(BASE_CONF + "?op=guardarMultiple", {configs: JSON.stringify(configs)}, function() {
        avanzarPaso(4);
    });
}

function cargarDatosDemo() {
    // Los datos demo se cargan via SQL — aquí solo mostramos confirmación
    alert("Para cargar datos demo, ejecuta:\nmysql -u root -p dbsolventas17 < sql/datos_demo.sql\n\nO crea un ticket manual desde el panel.");
    avanzarPaso(5);
}

function saltarPaso(siguiente) { avanzarPaso(siguiente); }

function avanzarPaso(paso) {
    $.post(BASE_OB + "?op=onboardingAvanzar", {paso: paso}, function() {
        verificarProgreso();
    });
}

function completarOnboarding() {
    $.post(BASE_OB + "?op=onboardingCompletar", {}, function() {
        cargarContenido('soporte_dashboard');
    });
}
</script>
