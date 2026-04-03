<?php
/**
 * Vista: Dashboard SSolutions
 * Panel de RESULTADOS primero → luego operación
 */
?>
<section class="content-header">
    <h1>SSolutions <small>Panel de Control</small></h1>
</section>

<section class="content">

    <!-- ============================================================ -->
    <!-- RESULTADO CLARO: Lo primero que ve el usuario (DINERO + TIEMPO) -->
    <!-- ============================================================ -->
    <div class="row">
        <div class="col-md-12">
            <div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);border-radius:12px;padding:25px 20px;margin-bottom:20px;color:#fff;">
                <div class="row">
                    <div class="col-sm-3 col-xs-6 text-center" style="border-right:1px solid rgba(255,255,255,0.15);">
                        <div style="font-size:36px;font-weight:700;" id="hero-recuperados">0</div>
                        <div style="font-size:13px;opacity:0.8;">Clientes Recuperados</div>
                        <div style="font-size:11px;color:#2ecc71;">este mes</div>
                    </div>
                    <div class="col-sm-3 col-xs-6 text-center" style="border-right:1px solid rgba(255,255,255,0.15);">
                        <div style="font-size:36px;font-weight:700;" id="hero-mensajes">0</div>
                        <div style="font-size:13px;opacity:0.8;">Mensajes Automaticos</div>
                        <div style="font-size:11px;color:#3498db;">enviados</div>
                    </div>
                    <div class="col-sm-3 col-xs-6 text-center" style="border-right:1px solid rgba(255,255,255,0.15);">
                        <div style="font-size:36px;font-weight:700;" id="hero-conversion">0%</div>
                        <div style="font-size:13px;opacity:0.8;">Tasa de Conversion</div>
                        <div style="font-size:11px;color:#f39c12;">30 dias</div>
                    </div>
                    <div class="col-sm-3 col-xs-6 text-center">
                        <div style="font-size:36px;font-weight:700;" id="hero-tiempo">0h</div>
                        <div style="font-size:13px;opacity:0.8;">Tiempo Ahorrado</div>
                        <div style="font-size:11px;color:#e74c3c;">estimado</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ACCION QUE GENERA DINERO: Boton grande + clientes calientes -->
    <!-- ============================================================ -->
    <div class="row">
        <div class="col-md-6">
            <div style="background:#27ae60;border-radius:12px;padding:20px;color:#fff;text-align:center;margin-bottom:20px;cursor:pointer;" onclick="recuperarClientesAhora()">
                <div style="font-size:24px;font-weight:700;margin-bottom:5px;">
                    <i class="fa fa-send"></i> RECUPERAR CLIENTES AHORA
                </div>
                <div style="font-size:14px;opacity:0.9;" id="btn-recuperar-desc">
                    Buscando clientes sin respuesta...
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div style="background:#e74c3c;border-radius:12px;padding:20px;color:#fff;margin-bottom:20px;">
                <div class="row">
                    <div class="col-xs-4 text-center">
                        <div style="font-size:30px;font-weight:700;" id="score-hot">0</div>
                        <div style="font-size:11px;">CALIENTES</div>
                    </div>
                    <div class="col-xs-4 text-center" style="border-left:1px solid rgba(255,255,255,0.2);border-right:1px solid rgba(255,255,255,0.2);">
                        <div style="font-size:30px;font-weight:700;" id="score-warm">0</div>
                        <div style="font-size:11px;">TIBIOS</div>
                    </div>
                    <div class="col-xs-4 text-center">
                        <div style="font-size:30px;font-weight:700;" id="score-cold">0</div>
                        <div style="font-size:11px;">FRIOS</div>
                    </div>
                </div>
                <div class="text-center" style="margin-top:10px;">
                    <a href="#" onclick="cargarContenido('soporte_automatizacion')" style="color:#fff;text-decoration:underline;font-size:12px;">Ver detalle de scores</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- MICRO-FEEDBACK + PRUEBA SOCIAL -->
    <!-- ============================================================ -->
    <div class="row">
        <!-- Micro-feedback de recuperación -->
        <div class="col-md-6" id="panel-feedback" style="display:none;">
            <div style="background:#fff;border-left:4px solid #27ae60;border-radius:8px;padding:18px 20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div id="feedback-content"></div>
            </div>
        </div>
        <!-- Último cliente recuperado (prueba social) -->
        <div class="col-md-6" id="panel-ultimo-recuperado" style="display:none;">
            <div style="background:#fff;border-left:4px solid #9b59b6;border-radius:8px;padding:18px 20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div style="font-size:12px;color:#999;margin-bottom:6px;">ULTIMO CLIENTE RECUPERADO</div>
                <div id="ultimo-recuperado-content"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TARJETAS OPERATIVAS -->
    <!-- ============================================================ -->
    <div class="row">
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3 id="stat-tickets-activos">0</h3>
                    <p>Tickets Activos</p>
                </div>
                <div class="icon"><i class="fa fa-ticket"></i></div>
                <a href="#" onclick="cargarContenido('soporte_tickets')" class="small-box-footer">Ver tickets <i class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3 id="stat-tickets-urgentes">0</h3>
                    <p>Urgentes / Criticos</p>
                </div>
                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                <a href="#" onclick="cargarContenido('soporte_tickets', 'critica')" class="small-box-footer">Ver urgentes <i class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3 id="stat-tickets-cerrados">0</h3>
                    <p>Cerrados (30 dias)</p>
                </div>
                <div class="icon"><i class="fa fa-check-circle"></i></div>
                <a href="#" class="small-box-footer">Detalle <i class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3 id="stat-ingresos">$0</h3>
                    <p>Ingresos (30 dias)</p>
                </div>
                <div class="icon"><i class="fa fa-dollar"></i></div>
                <a href="#" class="small-box-footer">Reporte <i class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- CONTENIDO OPERATIVO -->
    <!-- ============================================================ -->
    <div class="row">
        <!-- Diagnosticos recientes -->
        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-stethoscope"></i> Diagnosticos Recientes</h3>
                    <div class="box-tools pull-right">
                        <span class="label label-info" id="stat-diagnosticos">0</span>
                    </div>
                </div>
                <div class="box-body">
                    <table class="table table-striped table-condensed">
                        <thead><tr><th>Fecha</th><th>Cliente</th><th>Equipo</th><th>Urgencia</th><th></th></tr></thead>
                        <tbody id="tbody-diagnosticos"></tbody>
                    </table>
                </div>
                <div class="box-footer text-center">
                    <a href="#" onclick="cargarContenido('soporte_diagnosticos')">Ver todos</a>
                </div>
            </div>
        </div>

        <!-- Top tecnicos + WhatsApp -->
        <div class="col-md-6">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-users"></i> Tecnicos Activos</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped">
                        <thead><tr><th>Tecnico</th><th>Acciones</th><th>Horas</th></tr></thead>
                        <tbody id="tbody-tecnicos"></tbody>
                    </table>
                </div>
            </div>
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-whatsapp"></i> Canal WhatsApp</h3>
                </div>
                <div class="box-body"><div id="whatsapp-status"></div></div>
            </div>
        </div>
    </div>

    <!-- Rendimiento -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-clock-o"></i> Rendimiento</h3>
                    <div class="box-tools pull-right">
                        <a href="#" onclick="cargarContenido('soporte_automatizacion')" class="btn btn-xs btn-success"><i class="fa fa-bolt"></i> Centro de Automatizacion</a>
                    </div>
                </div>
                <div class="box-body">
                    <div class="col-md-4 text-center">
                        <h4 id="stat-tiempo-promedio">--</h4>
                        <p class="text-muted">Tiempo promedio resolucion</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <h4 id="stat-tasa-conversion">--</h4>
                        <p class="text-muted">Conversion a venta</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <h4 id="stat-diagnosticos-urgentes">--</h4>
                        <p class="text-muted">Diagnosticos urgentes</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
var _clientesSinResp = [];

$(document).ready(function() {
    cargarEstadisticas();
    cargarMetricasHero();
    cargarScoresDashboard();
    cargarClientesPendientes();
    cargarUltimoRecuperado();
});

// ============ HERO METRICS (lo primero que ve) ============
function cargarMetricasHero() {
    $.getJSON("" + AJAX + "AutomatizacionAjax.php?op=metricasVentas", function(d) {
        $("#hero-recuperados").text(d.clientes_recuperados || 0);
        $("#hero-mensajes").text(d.mensajes_enviados || 0);
        $("#hero-conversion").text((d.tasa_conversion || 0) + "%");

        // Tiempo ahorrado: ~3 min por mensaje automático
        var minAhorrados = (d.mensajes_enviados || 0) * 3;
        var horas = Math.round(minAhorrados / 60);
        $("#hero-tiempo").text("~" + horas + "h");
    });
}

// ============ SCORES DASHBOARD ============
function cargarScoresDashboard() {
    $.getJSON("" + AJAX + "AutomatizacionAjax.php?op=scoresResumen", function(d) {
        if (!d) return;
        $("#score-hot").text(d.calientes || 0);
        $("#score-warm").text(d.tibios || 0);
        $("#score-cold").text(d.frios || 0);
    });
}

// ============ CLIENTES SIN RESPUESTA (para boton recuperar) ============
function cargarClientesPendientes() {
    $.getJSON("" + AJAX + "AutomatizacionAjax.php?op=clientesSinRespuesta", function(data) {
        _clientesSinResp = data || [];
        var n = _clientesSinResp.length;
        if (n > 0) {
            $("#btn-recuperar-desc").text(n + " cliente" + (n > 1 ? "s" : "") + " sin respuesta — Click para enviar seguimiento");
        } else {
            $("#btn-recuperar-desc").text("Todos los clientes han respondido");
        }
    });
}

function recuperarClientesAhora() {
    if (!_clientesSinResp || _clientesSinResp.length === 0) {
        mostrarFeedback("success", "Todos los clientes han respondido. No hay pendientes.", "check-circle");
        return;
    }
    var n = _clientesSinResp.length;
    if (!confirm("Enviar mensaje de seguimiento a " + n + " cliente" + (n > 1 ? "s" : "") + "?")) return;

    // Feedback inmediato: "enviando..."
    mostrarFeedback("info", "<i class='fa fa-spinner fa-spin'></i> Programando " + n + " mensajes...", "");

    var lista = _clientesSinResp.map(function(c) { return {telefono: c.telefono, nombre: c.nombre || 'Cliente'}; });
    $.post("" + AJAX + "AutomatizacionAjax.php?op=recuperarClientes", {telefonos: JSON.stringify(lista)}, function(resp) {
        var r = JSON.parse(resp);
        if (r.success) {
            // Micro-feedback con expectativa psicológica
            var prob = n >= 5 ? "alta" : n >= 2 ? "media" : "baja";
            var probColor = prob === "alta" ? "#e74c3c" : prob === "media" ? "#f39c12" : "#3498db";
            var nombres = _clientesSinResp.slice(0, 3).map(function(c) { return c.nombre || 'Cliente'; }).join(", ");
            if (n > 3) nombres += " y " + (n - 3) + " mas";

            mostrarFeedback("success",
                "<div style='margin-bottom:8px;'><strong style='font-size:16px;'>&#10004; " + r.enviados + " mensajes programados</strong></div>"
                + "<div style='margin-bottom:6px;'><i class='fa fa-comments-o'></i> Enviando a: " + nombres + "</div>"
                + "<div style='margin-bottom:6px;'><i class='fa fa-clock-o'></i> Se enviaran en los proximos minutos</div>"
                + "<div><i class='fa fa-fire'></i> Probabilidad de recuperar: <strong style='color:" + probColor + ";'>" + prob + "</strong></div>",
                ""
            );

            cargarClientesPendientes();
            cargarMetricasHero();
        } else {
            mostrarFeedback("danger", "Error al programar mensajes. Intenta de nuevo.", "times");
        }
    });
}

function mostrarFeedback(tipo, html, icono) {
    var borderColor = {success:"#27ae60", info:"#3498db", danger:"#e74c3c", warning:"#f39c12"}[tipo] || "#999";
    var panel = $("#panel-feedback");
    var prefix = icono ? "<i class='fa fa-" + icono + "' style='margin-right:6px;color:" + borderColor + ";'></i>" : "";
    panel.find("div").first().css("border-left-color", borderColor);
    $("#feedback-content").html(prefix + html);
    panel.fadeIn(300);
}

// ============ PRUEBA SOCIAL: Último cliente recuperado ============
function cargarUltimoRecuperado() {
    $.getJSON("" + AJAX + "AutomatizacionAjax.php?op=ultimoRecuperado", function(d) {
        if (!d || !d.encontrado) return;

        var hace = formatearTiempo(d.hace_minutos);
        var html = '<div style="display:flex;align-items:center;gap:12px;">'
            + '<div style="width:42px;height:42px;border-radius:50%;background:#9b59b6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;"><i class="fa fa-user"></i></div>'
            + '<div style="flex:1;">'
            + '<strong>' + d.cliente + '</strong> <span style="color:#999;font-size:12px;">(hace ' + hace + ')</span>'
            + '<div style="background:#f5f5f5;border-radius:8px;padding:8px 12px;margin-top:4px;font-style:italic;color:#555;">"' + escapeHtml(d.respuesta) + '"</div>'
            + '</div></div>';

        $("#ultimo-recuperado-content").html(html);
        $("#panel-ultimo-recuperado").fadeIn(300);
    });
}

function formatearTiempo(minutos) {
    if (minutos < 60) return minutos + "min";
    if (minutos < 1440) return Math.floor(minutos / 60) + "h";
    return Math.floor(minutos / 1440) + "d";
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

// ============ ESTADISTICAS OPERATIVAS ============
function cargarEstadisticas() {
    $.getJSON("" + AJAX + "DashboardAjax.php?op=stats", function(data) {
        var t = data.tickets || {};
        var d = data.diagnosticos || {};

        $("#stat-tickets-activos").text(t.tickets_activos || 0);
        $("#stat-tickets-urgentes").text(t.tickets_urgentes || 0);
        $("#stat-tickets-cerrados").text(t.tickets_cerrados || 0);
        $("#stat-ingresos").text("$" + numberFormat(t.ingresos_total || 0));
        $("#stat-diagnosticos").text((d.total_diagnosticos || 0) + " diagnosticos");

        var horas = parseFloat(t.tiempo_promedio_horas || 0);
        if (horas > 0) $("#stat-tiempo-promedio").text(horas.toFixed(1) + " horas");

        var total = parseInt(t.total_tickets || 0);
        var convertidos = parseInt(t.convertidos_venta || 0);
        if (total > 0) $("#stat-tasa-conversion").text(((convertidos / total) * 100).toFixed(1) + "%");

        $("#stat-diagnosticos-urgentes").text(d.diagnosticos_urgentes || 0);

        var tbody = "";
        (data.tecnicos || []).forEach(function(tec) {
            tbody += "<tr><td>" + tec.tecnico + "</td><td>" + tec.acciones + "</td><td>" + (tec.minutos / 60).toFixed(1) + "h</td></tr>";
        });
        $("#tbody-tecnicos").html(tbody || "<tr><td colspan='3' class='text-center text-muted'>Sin datos</td></tr>");
    });

    $.getJSON("" + AJAX + "DiagnosticoAjax.php?op=list", function(data) {
        var tbody = "";
        (data.aaData || []).slice(0, 5).forEach(function(row) {
            tbody += "<tr><td>" + row[1] + "</td><td>" + row[2] + "</td><td>" + row[3] + "</td><td>" + row[8] + "</td><td>" + row[9] + "</td></tr>";
        });
        $("#tbody-diagnosticos").html(tbody || "<tr><td colspan='5' class='text-center text-muted'>Sin diagnosticos</td></tr>");
    });

    $.getJSON("" + AJAX + "WhatsAppAjax.php?op=configuracion", function(data) {
        if (data.configurado) {
            $("#whatsapp-status").html('<span class="label label-success"><i class="fa fa-check"></i> Conectado</span> <small>ID: ' + data.phone_id + '</small>');
        } else {
            $("#whatsapp-status").html('<span class="label label-danger"><i class="fa fa-times"></i> No configurado</span> <a href="#" onclick="cargarContenido(\'soporte_configuracion\')">Configurar</a>');
        }
    });
}

function numberFormat(num) {
    return parseInt(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
</script>
