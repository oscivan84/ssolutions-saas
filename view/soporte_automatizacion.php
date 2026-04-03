<?php
/**
 * Vista: Centro de Automatizacion
 * Panel de visibilidad: eventos, jobs, scores, metricas de ventas, automatizaciones
 */
?>
<section class="content-header">
    <h1>Centro de Automatizacion <small>SSolutions</small></h1>
</section>

<section class="content">

    <!-- METRICAS DE VENTAS (lo que vende el producto) -->
    <div class="row">
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-purple">
                <div class="inner">
                    <h3 id="mv-mensajes">0</h3>
                    <p>Mensajes Enviados (30d)</p>
                </div>
                <div class="icon"><i class="fa fa-whatsapp"></i></div>
                <a href="#" class="small-box-footer" onclick="mostrarTab('tab-jobs')">Ver detalle <i class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3 id="mv-recuperados">0</h3>
                    <p>Clientes Recuperados</p>
                </div>
                <div class="icon"><i class="fa fa-user-plus"></i></div>
                <a href="#" class="small-box-footer" onclick="mostrarTab('tab-recuperar')">Recuperar mas <i class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3 id="mv-conversion">0%</h3>
                    <p>Tasa de Conversion</p>
                </div>
                <div class="icon"><i class="fa fa-line-chart"></i></div>
                <a href="#" class="small-box-footer">30 dias <i class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3 id="mv-ingresos-wa">$0</h3>
                    <p>Ingresos via WhatsApp</p>
                </div>
                <div class="icon"><i class="fa fa-dollar"></i></div>
                <a href="#" class="small-box-footer">30 dias <i class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
            <li class="active"><a href="#tab-scores" data-toggle="tab"><i class="fa fa-fire"></i> Scores de Clientes</a></li>
            <li><a href="#tab-automatizaciones" data-toggle="tab"><i class="fa fa-bolt"></i> Automatizaciones</a></li>
            <li><a href="#tab-jobs" data-toggle="tab"><i class="fa fa-cogs"></i> Cola de Trabajos</a></li>
            <li><a href="#tab-eventos" data-toggle="tab"><i class="fa fa-history"></i> Eventos</a></li>
            <li><a href="#tab-recuperar" data-toggle="tab"><i class="fa fa-user-plus"></i> Recuperar Clientes</a></li>
        </ul>
        <div class="tab-content">

            <!-- TAB: SCORES -->
            <div class="tab-pane active" id="tab-scores">
                <div class="row" style="margin-bottom:15px;">
                    <div class="col-md-4"><div class="info-box bg-red"><span class="info-box-icon"><i class="fa fa-fire"></i></span><div class="info-box-content"><span class="info-box-text">Calientes</span><span class="info-box-number" id="score-calientes">0</span></div></div></div>
                    <div class="col-md-4"><div class="info-box bg-yellow"><span class="info-box-icon"><i class="fa fa-thermometer-half"></i></span><div class="info-box-content"><span class="info-box-text">Tibios</span><span class="info-box-number" id="score-tibios">0</span></div></div></div>
                    <div class="col-md-4"><div class="info-box bg-blue"><span class="info-box-icon"><i class="fa fa-snowflake-o"></i></span><div class="info-box-content"><span class="info-box-text">Frios</span><span class="info-box-number" id="score-frios">0</span></div></div></div>
                </div>
                <table id="tbl-scores" class="table table-striped table-bordered">
                    <thead><tr><th>Fecha</th><th>Cliente</th><th>Telefono</th><th>Score</th><th>Razon</th><th>Accion Sugerida</th><th>Fuente</th></tr></thead>
                    <tbody id="tbody-scores"></tbody>
                </table>
            </div>

            <!-- TAB: AUTOMATIZACIONES -->
            <div class="tab-pane" id="tab-automatizaciones">
                <table class="table table-striped table-bordered">
                    <thead><tr><th>Nombre</th><th>Evento Trigger</th><th>Accion</th><th>Delay</th><th>Ejecutada</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="tbody-automatizaciones"></tbody>
                </table>
            </div>

            <!-- TAB: JOBS -->
            <div class="tab-pane" id="tab-jobs">
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-12">
                        <div class="btn-group">
                            <button class="btn btn-default btn-sm active" onclick="cargarJobs('')">Todos</button>
                            <button class="btn btn-warning btn-sm" onclick="cargarJobs('pendiente')">Pendientes</button>
                            <button class="btn btn-info btn-sm" onclick="cargarJobs('procesando')">Procesando</button>
                            <button class="btn btn-success btn-sm" onclick="cargarJobs('completado')">Completados</button>
                            <button class="btn btn-danger btn-sm" onclick="cargarJobs('fallido')">Fallidos</button>
                        </div>
                        <span class="pull-right text-muted" id="jobs-stats-text"></span>
                    </div>
                </div>
                <table class="table table-striped table-condensed">
                    <thead><tr><th>#</th><th>Tipo</th><th>Prioridad</th><th>Estado</th><th>Intentos</th><th>Creado</th><th>Resultado/Error</th></tr></thead>
                    <tbody id="tbody-jobs"></tbody>
                </table>
            </div>

            <!-- TAB: EVENTOS -->
            <div class="tab-pane" id="tab-eventos">
                <table class="table table-striped table-condensed">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Procesado</th><th>Payload</th></tr></thead>
                    <tbody id="tbody-eventos"></tbody>
                </table>
            </div>

            <!-- TAB: RECUPERAR CLIENTES -->
            <div class="tab-pane" id="tab-recuperar">
                <div class="callout callout-info">
                    <h4>Clientes sin respuesta (24h+)</h4>
                    <p>Estos clientes recibieron un mensaje pero no respondieron. Enviales un seguimiento automatico.</p>
                </div>
                <button class="btn btn-success btn-lg" onclick="recuperarTodos()" style="margin-bottom:15px;">
                    <i class="fa fa-send"></i> Enviar Seguimiento a Todos
                </button>
                <table class="table table-striped">
                    <thead><tr><th>Cliente</th><th>Telefono</th><th>Ultimo Mensaje</th><th>Hace</th><th></th></tr></thead>
                    <tbody id="tbody-recuperar"></tbody>
                </table>
            </div>

        </div>
    </div>
</section>

<script>
var BASE = "../landingV2/ajax/AutomatizacionAjax.php";

$(document).ready(function() {
    cargarMetricasVentas();
    cargarScores();
    cargarAutomatizaciones();
    cargarJobs('');
    cargarEventos();
    cargarClientesSinRespuesta();
});

// ============ METRICAS VENTAS ============
function cargarMetricasVentas() {
    $.getJSON(BASE + "?op=metricasVentas", function(d) {
        $("#mv-mensajes").text(d.mensajes_enviados || 0);
        $("#mv-recuperados").text(d.clientes_recuperados || 0);
        $("#mv-conversion").text((d.tasa_conversion || 0) + "%");
        $("#mv-ingresos-wa").text("$" + numberFormat(d.ingresos_whatsapp || 0));
    });
}

// ============ SCORES ============
function cargarScores() {
    $.getJSON(BASE + "?op=scoresResumen", function(d) {
        $("#score-calientes").text(d ? d.calientes || 0 : 0);
        $("#score-tibios").text(d ? d.tibios || 0 : 0);
        $("#score-frios").text(d ? d.frios || 0 : 0);
    });
    $.getJSON(BASE + "?op=scores", function(data) {
        var html = "";
        (data || []).forEach(function(s) {
            var badge = s.score === 'caliente' ? 'danger' : s.score === 'tibio' ? 'warning' : 'info';
            var icon = s.score === 'caliente' ? 'fire' : s.score === 'tibio' ? 'thermometer-half' : 'snowflake-o';
            html += "<tr><td>" + s.fecha + "</td><td>" + (s.cliente || 'N/A') + "</td><td>" + (s.telefono || '') + "</td>"
                + "<td><span class='label label-" + badge + "'><i class='fa fa-" + icon + "'></i> " + s.score.toUpperCase() + "</span></td>"
                + "<td>" + (s.razon || '-') + "</td>"
                + "<td><strong>" + (s.accion_sugerida || '-') + "</strong></td>"
                + "<td><span class='label label-default'>" + s.source + "</span></td></tr>";
        });
        $("#tbody-scores").html(html || "<tr><td colspan='7' class='text-center text-muted'>Sin scores aun</td></tr>");
    });
}

// ============ AUTOMATIZACIONES ============
function cargarAutomatizaciones() {
    $.getJSON(BASE + "?op=automatizaciones", function(data) {
        var html = "";
        (data || []).forEach(function(a) {
            var estadoBadge = a.activo == 1 ? '<span class="label label-success">ON</span>' : '<span class="label label-danger">OFF</span>';
            var delay = a.delay_minutos > 0 ? a.delay_minutos + " min" : "Inmediato";
            html += "<tr>"
                + "<td><strong>" + a.nombre + "</strong></td>"
                + "<td><code>" + a.trigger_evento + "</code></td>"
                + "<td><code>" + a.accion + "</code></td>"
                + "<td>" + delay + "</td>"
                + "<td>" + a.ejecutada_veces + " veces</td>"
                + "<td>" + estadoBadge + "</td>"
                + "<td>"
                + '<button class="btn btn-xs btn-' + (a.activo == 1 ? 'warning' : 'success') + '" onclick="toggleAuto(' + a.idautomatizacion + ',' + (a.activo == 1 ? 0 : 1) + ')"><i class="fa fa-power-off"></i> ' + (a.activo == 1 ? 'OFF' : 'ON') + '</button> '
                + '<button class="btn btn-xs btn-info" onclick="probarAuto(' + a.idautomatizacion + ')"><i class="fa fa-play"></i> Probar</button>'
                + "</td></tr>";
        });
        $("#tbody-automatizaciones").html(html || "<tr><td colspan='7' class='text-center text-muted'>Sin automatizaciones</td></tr>");
    });
}

function toggleAuto(id, activo) {
    $.post(BASE + "?op=toggleAutomatizacion", {id: id, activo: activo}, function() {
        cargarAutomatizaciones();
    });
}

function probarAuto(id) {
    $.post(BASE + "?op=probarAutomatizacion", {id: id}, function(resp) {
        var r = JSON.parse(resp);
        alert(r.message || "Ejecutado");
        cargarJobs('');
    });
}

// ============ JOBS ============
function cargarJobs(estado) {
    $.getJSON(BASE + "?op=jobs&estado=" + estado, function(data) {
        var html = "";
        (data || []).forEach(function(j) {
            var badge = {pendiente:'warning',procesando:'info',completado:'success',fallido:'danger'}[j.estado] || 'default';
            var info = j.estado === 'fallido' ? (j.error || '') : (j.resultado ? JSON.stringify(j.resultado).substring(0, 80) : '');
            html += "<tr><td>" + j.idjob + "</td><td><code>" + j.tipo + "</code></td>"
                + "<td>" + j.prioridad + "</td>"
                + "<td><span class='label label-" + badge + "'>" + j.estado + "</span></td>"
                + "<td>" + j.intentos + "/" + j.max_intentos + "</td>"
                + "<td>" + j.fecha_creacion + "</td>"
                + "<td><small>" + info + "</small></td></tr>";
        });
        $("#tbody-jobs").html(html || "<tr><td colspan='7' class='text-center text-muted'>Sin jobs</td></tr>");
    });
    $.getJSON(BASE + "?op=jobsStats", function(d) {
        if (d) $("#jobs-stats-text").text("24h: " + (d.completados||0) + " ok / " + (d.pendientes||0) + " pendientes / " + (d.fallidos||0) + " fallidos");
    });
}

// ============ EVENTOS ============
function cargarEventos() {
    $.getJSON(BASE + "?op=eventos", function(data) {
        var html = "";
        (data || []).forEach(function(e) {
            var proc = e.procesado == 1 ? '<span class="label label-success">Si</span>' : '<span class="label label-warning">No</span>';
            html += "<tr><td>" + e.fecha_creacion + "</td><td><code>" + e.tipo + "</code></td>"
                + "<td>" + proc + "</td>"
                + "<td><small><code>" + JSON.stringify(e.payload).substring(0, 120) + "</code></small></td></tr>";
        });
        $("#tbody-eventos").html(html || "<tr><td colspan='4' class='text-center text-muted'>Sin eventos</td></tr>");
    });
}

// ============ RECUPERAR CLIENTES ============
function cargarClientesSinRespuesta() {
    $.getJSON(BASE + "?op=clientesSinRespuesta", function(data) {
        var html = "";
        window._clientesSinResp = data || [];
        (data || []).forEach(function(c) {
            var hace = tiempoRelativo(c.fecha_ultimo_mensaje);
            html += "<tr><td>" + (c.nombre || 'Desconocido') + "</td><td>" + c.telefono + "</td>"
                + "<td><small>" + (c.ultimo_mensaje_enviado || '').substring(0, 60) + "...</small></td>"
                + "<td>" + hace + "</td>"
                + '<td><button class="btn btn-xs btn-success" onclick="recuperarUno(\'' + c.telefono + '\', \'' + (c.nombre||'Cliente') + '\')"><i class="fa fa-send"></i></button></td></tr>';
        });
        $("#tbody-recuperar").html(html || "<tr><td colspan='5' class='text-center text-muted'>Todos los clientes han respondido</td></tr>");
    });
}

function recuperarUno(tel, nombre) {
    $.post(BASE + "?op=recuperarClientes", {telefonos: JSON.stringify([{telefono: tel, nombre: nombre}])}, function(resp) {
        var r = JSON.parse(resp);
        alert(r.success ? "Mensaje programado" : "Error");
        cargarClientesSinRespuesta();
    });
}

function recuperarTodos() {
    if (!window._clientesSinResp || window._clientesSinResp.length === 0) { alert("No hay clientes para recuperar"); return; }
    if (!confirm("Enviar seguimiento a " + window._clientesSinResp.length + " clientes?")) return;

    var lista = window._clientesSinResp.map(function(c) { return {telefono: c.telefono, nombre: c.nombre || 'Cliente'}; });
    $.post(BASE + "?op=recuperarClientes", {telefonos: JSON.stringify(lista)}, function(resp) {
        var r = JSON.parse(resp);
        alert(r.success ? r.enviados + " mensajes programados" : "Error");
        cargarClientesSinRespuesta();
        cargarJobs('');
    });
}

// ============ HELPERS ============
function mostrarTab(tabId) {
    $('a[href="#' + tabId + '"]').tab('show');
}

function numberFormat(num) {
    return parseInt(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function tiempoRelativo(fecha) {
    var diff = Math.floor((new Date() - new Date(fecha)) / 60000);
    if (diff < 60) return diff + " min";
    if (diff < 1440) return Math.floor(diff / 60) + "h";
    return Math.floor(diff / 1440) + "d";
}
</script>
